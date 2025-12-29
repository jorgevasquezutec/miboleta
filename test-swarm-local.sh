#!/bin/bash
# Script para probar Docker Swarm localmente

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${BLUE}→ $1${NC}"
}

print_header "PRUEBA LOCAL DE DOCKER SWARM - MIBOLETA"

# 1. Verificar que Docker está corriendo
print_header "[1/8] Verificando Docker"
if ! docker info >/dev/null 2>&1; then
    print_error "Docker no está corriendo. Por favor inicia Docker Desktop."
    exit 1
fi
print_success "Docker está corriendo"

# 2. Inicializar Docker Swarm si no está activo
print_header "[2/8] Inicializando Docker Swarm"
if docker info 2>/dev/null | grep -q "Swarm: active"; then
    print_success "Docker Swarm ya está activo"
else
    print_info "Inicializando Docker Swarm..."
    docker swarm init
    print_success "Docker Swarm inicializado"
fi

# 3. Verificar que existe el archivo .env.local
print_header "[3/8] Verificando archivo .env"
if [ ! -f config/.env.local ]; then
    print_error "No existe config/.env.local"
    print_info "Copiando desde .env.example..."
    cp config/.env.example config/.env.local
    print_warning "EDITA config/.env.local antes de continuar"
    exit 1
fi
print_success "Archivo config/.env.local existe"

# 4. Generar APP_KEY si no existe
print_header "[4/8] Generando APP_KEY"
if grep -q "APP_KEY=base64:GENERAR_CON_ARTISAN" config/.env.local; then
    print_info "Generando nueva APP_KEY..."

    # Generar key usando OpenSSL (funciona en todos los sistemas)
    APP_KEY="base64:$(openssl rand -base64 32)"

    # Reemplazar en el archivo
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|APP_KEY=base64:GENERAR_CON_ARTISAN|APP_KEY=$APP_KEY|g" config/.env.local
    else
        sed -i "s|APP_KEY=base64:GENERAR_CON_ARTISAN|APP_KEY=$APP_KEY|g" config/.env.local
    fi

    print_success "APP_KEY generada: $APP_KEY"
else
    print_success "APP_KEY ya existe"
fi

# 5. Copiar .env.local a .env para el stack
print_header "[5/8] Preparando archivos de configuración"
cp config/.env.local config/.env
print_success "config/.env creado desde .env.local"

# 6. Build de la imagen Docker
print_header "[6/8] Building imagen Docker"
print_info "Esto puede tomar varios minutos la primera vez..."
print_info "Usando config/.env.vite.swarm para variables de Vite"
docker build --build-arg VITE_ENV_FILE=config/.env.vite.swarm -t miboleta:local .
# Tag para que coincida con lo que espera docker-stack.yml
docker tag miboleta:local ghcr.io/jorgevasquezutec/miboleta:local
print_success "Imagen Docker construida y tageada: ghcr.io/jorgevasquezutec/miboleta:local"

# 7. Deploy del stack
print_header "[7/8] Deploying stack a Docker Swarm"
print_info "Cargando variables de entorno..."
export $(cat .env.stack | grep -v '^#' | xargs)
export IMAGE_TAG=local

print_info "Deploying stack 'miboleta'..."
docker stack deploy -c docker-stack.yml miboleta

print_success "Stack deployed!"

# 8. Esperar y verificar servicios
print_header "[8/8] Verificando servicios"
print_info "Esperando a que los servicios inicien (30s)..."
sleep 30

echo ""
print_info "Estado de los servicios:"
docker stack services miboleta

echo ""
print_info "Servicios en ejecución:"
docker stack ps miboleta --no-trunc

echo ""
print_header "✅ DEPLOY COMPLETO"
echo ""
echo -e "${GREEN}El stack está corriendo localmente${NC}"
echo ""
echo -e "${BLUE}URLs:${NC}"
echo "  • App:     http://localhost"
echo "  • API:     http://localhost/api"
echo "  • Horizon: http://localhost/horizon"
echo "  • Health:  http://localhost/health"
echo ""
echo -e "${BLUE}Comandos útiles:${NC}"
echo "  Ver servicios:    docker stack services miboleta"
echo "  Ver logs app:     docker service logs miboleta_app -f"
echo "  Ver logs nginx:   docker service logs miboleta_nginx -f"
echo "  Ver logs db:      docker service logs miboleta_db -f"
echo "  Escalar app:      docker service scale miboleta_app=2"
echo "  Remover stack:    docker stack rm miboleta"
echo "  Salir de Swarm:   docker swarm leave --force"
echo ""
echo -e "${YELLOW}Nota:${NC} Los contenedores pueden tardar unos segundos en estar listos"
echo ""

# Esperar a que el contenedor app esté listo
print_info "Esperando que el contenedor app esté listo..."
for i in {1..30}; do
    APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)
    if [ -n "$APP_CONTAINER" ]; then
        echo -e "${GREEN}✓ Contenedor app encontrado: $APP_CONTAINER${NC}"

        echo ""
        print_info "Verificando que el entrypoint haya completado..."
        sleep 5

        echo ""
        print_info "Verificando estado de la aplicación..."
        docker exec $APP_CONTAINER php artisan --version && print_success "Aplicación lista!" || print_warning "La app puede tardar unos segundos más"

        echo ""
        print_info "Recargando configuración de nginx..."
        NGINX_CONTAINER=$(docker ps -qf "name=miboleta_nginx" | head -1)
        if [ -n "$NGINX_CONTAINER" ]; then
            docker exec $NGINX_CONTAINER nginx -s reload 2>/dev/null && print_success "Nginx recargado" || true
        fi

        echo ""
        print_success "¡Todo listo! Visita http://localhost"
        echo ""
        print_info "Nota: El entrypoint ejecutó automáticamente:"
        echo "  • php artisan storage:link"
        echo "  • php artisan migrate"
        echo "  • php artisan db:seed (si RUN_SEEDERS=true)"
        echo ""
        print_info "Ver logs del entrypoint:"
        echo "  docker service logs miboleta_app | grep '🚀\\|✅\\|⏳'"
        break
    fi
    echo "Esperando contenedor app... ($i/30)"
    sleep 3
done

if [ -z "$APP_CONTAINER" ]; then
    print_warning "El contenedor app tardó mucho en iniciar. Verifica los logs:"
    echo "  docker service logs miboleta_app"
fi
