#!/bin/bash
# ==============================================
# MiBoleta - Setup Inicial del Servidor
# Ejecutar UNA SOLA VEZ antes del primer deploy
# ==============================================

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

print_info() {
    echo -e "${BLUE}→ $1${NC}"
}

# Si el script corre desde un checkout del repo, config/ esta un nivel arriba.
# Ejecutado suelto (scp del .sh a /tmp, como decia la guia vieja) no existe.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_CONFIG="${SCRIPT_DIR}/../config"

# Instala un archivo de configuracion en /opt/miboleta/config/.
#
# ANTES este script llevaba los conf embebidos en heredocs, y se congelaron: el
# nginx.conf inline no tenia el bloque `location ~* \.mjs$` (visor de PDF roto),
# ni `^~ /storage` (imagenes 404), ni los `resolver` que evitan el 502 permanente
# cuando el contenedor de PHP cambia de IP, ni el proxy de Reverb. Una instalacion
# nueva arrancaba con cuatro bugs ya arreglados en el repo. La fuente de verdad es
# config/ del repositorio — de ahi se copia, no se reinventa.
#
# Si no hay repo a mano se crea el archivo VACIO a proposito: el bind-mount de
# docker-stack.yml apunta a un ARCHIVO, y si la ruta no existe Docker crea un
# DIRECTORIO en su lugar y el contenedor no levanta. Vacio, el primer deploy lo
# sobrescribe con el bueno.
instalar_config() {
    local nombre="$1"
    local destino="/opt/miboleta/config/${nombre}"

    if [ -f "${REPO_CONFIG}/${nombre}" ]; then
        cp "${REPO_CONFIG}/${nombre}" "${destino}"
        print_success "${nombre} copiado desde el repositorio"
    elif [ -s "${destino}" ]; then
        print_info "${nombre} ya existe (el deploy lo sobrescribe con el del repo)"
    else
        : > "${destino}"
        print_warning "${nombre}: sin repo a mano, se dejo un placeholder VACIO"
        print_warning "  cópialo antes del primer deploy:  scp config/${nombre} SERVIDOR:${destino}"
    fi
}

print_header "MIBOLETA - SETUP INICIAL DEL SERVIDOR"

# 1. Verificar/Instalar Docker
print_header "[1/6] Docker"
if command -v docker &> /dev/null; then
    print_success "Docker ya está instalado: $(docker --version)"
else
    print_info "Instalando Docker..."
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker $USER
    print_success "Docker instalado"
    print_warning "Debes cerrar sesión y volver a entrar para que surta efecto el grupo docker"
fi

# 2. Inicializar Docker Swarm
print_header "[2/6] Docker Swarm"
if docker info 2>/dev/null | grep -q "Swarm: active"; then
    print_success "Docker Swarm ya está activo"
else
    print_info "Inicializando Docker Swarm..."
    docker swarm init --advertise-addr $(hostname -I | awk '{print $1}') || docker swarm init
    print_success "Docker Swarm inicializado"
fi

# 3. Crear estructura de directorios
print_header "[3/6] Estructura de Directorios"
sudo mkdir -p /opt/miboleta/{config,ssl,backups,secrets}
sudo chown -R $USER:$USER /opt/miboleta
print_success "Directorios creados en /opt/miboleta/"

# 4. Crear archivos de configuración base
print_header "[4/6] Archivos de Configuración"

# .env: se siembra desde config/.env.example del repo, que es lo unico que se
# mantiene al dia. El heredoc que habia aqui tampoco traia APP_KEY (Laravel ni
# arranca sin ella), ni MAIL_*, ni REVERB_*. A diferencia de los otros conf, este
# NO se sobrescribe nunca: lleva las claves reales del servidor.
if [ -s /opt/miboleta/config/.env ]; then
    print_info "Archivo .env ya existe (no se toca: tiene las claves reales)"
elif [ -f "${REPO_CONFIG}/.env.example" ]; then
    cp "${REPO_CONFIG}/.env.example" /opt/miboleta/config/.env
    print_success ".env creado desde config/.env.example"
    print_warning "EDITAR con valores reales antes del deploy (APP_KEY incluida)"
else
    print_warning ".env no existe y no hay repo a mano; cópialo antes del deploy:"
    print_warning "  scp config/.env.example SERVIDOR:/opt/miboleta/config/.env"
fi

# nginx.conf y my.cnf: se copian del repo, NO se generan aqui (ver instalar_config)
instalar_config nginx.conf

instalar_config my.cnf

# 5. Login a GitHub Container Registry
print_header "[5/6] GitHub Container Registry"
print_info "Para autenticarte con GHCR, ejecuta:"
echo ""
echo -e "${YELLOW}  docker login ghcr.io -u TU_USUARIO_GITHUB${NC}"
echo ""
print_info "Usa un Personal Access Token con permisos 'read:packages'"

# 6. Resumen
print_header "[6/6] SETUP COMPLETADO"
echo ""
print_success "Servidor listo para recibir deploys automáticos"
echo ""
echo -e "${BLUE}Estructura creada:${NC}"
echo "  /opt/miboleta/"
echo "  ├── config/"
echo "  │   ├── .env          <- EDITAR con valores reales"
echo "  │   ├── nginx.conf"
echo "  │   └── my.cnf"
echo "  ├── ssl/              <- Agregar certificados SSL"
echo "  ├── secrets/"
echo "  └── backups/"
echo ""
echo -e "${YELLOW}Próximos pasos:${NC}"
echo "  1. Editar /opt/miboleta/config/.env con valores de producción"
echo "  2. Agregar certificados SSL en /opt/miboleta/ssl/ (cert.pem, key.pem)"
echo "  3. Ejecutar: docker login ghcr.io -u TU_USUARIO"
echo "  4. Hacer push a main en GitHub para trigger del deploy"
echo ""
