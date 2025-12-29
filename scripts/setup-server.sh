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

# Crear .env de ejemplo si no existe
if [ ! -f /opt/miboleta/config/.env ]; then
    cat << 'ENVFILE' > /opt/miboleta/config/.env
# MiBoleta Production Environment
# IMPORTANTE: Cambiar todos los valores por defecto

APP_NAME=MiBoleta
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=CAMBIAR_PASSWORD_DB

REDIS_HOST=redis
REDIS_PASSWORD=CAMBIAR_PASSWORD_REDIS
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
ENVFILE
    print_success "Archivo .env creado en /opt/miboleta/config/.env"
    print_warning "EDITAR con valores reales antes del deploy"
else
    print_info "Archivo .env ya existe"
fi

# Crear nginx.conf
if [ ! -f /opt/miboleta/config/nginx.conf ]; then
    cat << 'NGINXCONF' > /opt/miboleta/config/nginx.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.html index.php;

    client_max_body_size 20M;

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri /index.php?$query_string;
    }

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Horizon
    location /horizon {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handler
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60s;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }
}
NGINXCONF
    print_success "Archivo nginx.conf creado"
else
    print_info "Archivo nginx.conf ya existe"
fi

# Crear my.cnf para MySQL
if [ ! -f /opt/miboleta/config/my.cnf ]; then
    cat << 'MYCNF' > /opt/miboleta/config/my.cnf
[mysqld]
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
max_connections=200
innodb_buffer_pool_size=256M
MYCNF
    print_success "Archivo my.cnf creado"
fi

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
