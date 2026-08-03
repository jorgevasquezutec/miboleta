#!/bin/bash
# MiBoleta - Deploy Rápido con Imágenes Pre-built
# Este script es para deploys frecuentes usando imágenes de Docker Hub

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.production"

# Functions
print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${BLUE}→ $1${NC}"
}

# Check if .env.production exists
check_env() {
    if [ ! -f "${ENV_FILE}" ]; then
        print_error "Archivo ${ENV_FILE} no encontrado"
        print_info "Por favor, copia .env.production.example a ${ENV_FILE} y configúralo"
        exit 1
    fi
}

# Pull latest images
pull_images() {
    print_header "Descargando última imagen desde Docker Hub"
    docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" pull
    print_success "Imagen descargada"
}

# Update deployment (quick)
quick_update() {
    print_header "MIBOLETA - ACTUALIZACIÓN RÁPIDA"

    check_env
    pull_images

    print_info "Recreando contenedores con nueva imagen..."
    docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" up -d --force-recreate app horizon reverb

    print_info "Limpiando cachés de Laravel..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan optimize:clear
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan config:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan route:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan view:cache

    print_header "¡ACTUALIZACIÓN COMPLETADA!"
    docker compose -f "${COMPOSE_FILE}" ps

    print_info ""
    print_success "Deploy completado en ~30 segundos 🚀"
}

# Update with migrations
update_with_migrations() {
    print_header "MIBOLETA - ACTUALIZACIÓN CON MIGRACIONES"

    check_env
    pull_images

    print_info "Recreando contenedores con nueva imagen..."
    docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" up -d --force-recreate app horizon reverb

    print_info "Ejecutando migraciones..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan migrate --force

    print_info "Limpiando cachés de Laravel..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan optimize:clear
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan config:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan route:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan view:cache

    print_header "¡ACTUALIZACIÓN CON MIGRACIONES COMPLETADA!"
    docker compose -f "${COMPOSE_FILE}" ps
}

# Initial setup
initial_setup() {
    print_header "MIBOLETA - SETUP INICIAL"

    check_env
    pull_images

    print_info "Iniciando todos los servicios..."
    docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" up -d

    print_info "Esperando a que la base de datos esté lista..."
    sleep 10

    print_info "Ejecutando migraciones..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan migrate --force

    print_info "Creando enlace simbólico de storage..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan storage:link || true

    print_info "Optimizando Laravel..."
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan config:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan route:cache
    docker compose -f "${COMPOSE_FILE}" exec -T app php artisan view:cache

    print_header "¡SETUP INICIAL COMPLETADO!"
    docker compose -f "${COMPOSE_FILE}" ps

    print_info ""
    print_success "Aplicación lista en: https://tudominio.com"
}

# Commands
case "${1:-help}" in
    deploy|update)
        quick_update
        ;;
    migrate)
        update_with_migrations
        ;;
    init|setup)
        initial_setup
        ;;
    pull)
        check_env
        pull_images
        ;;
    logs)
        docker compose -f "${COMPOSE_FILE}" logs -f
        ;;
    status)
        docker compose -f "${COMPOSE_FILE}" ps
        ;;
    restart)
        docker compose -f "${COMPOSE_FILE}" restart app nginx horizon reverb
        ;;
    stop)
        docker compose -f "${COMPOSE_FILE}" down
        ;;
    help|*)
        echo -e "${BLUE}MiBoleta - Deploy Rápido (con imágenes pre-built)${NC}"
        echo ""
        echo "Uso: $0 {comando}"
        echo ""
        echo -e "${GREEN}Comandos principales:${NC}"
        echo "  deploy    - Actualización rápida (SIN migraciones) ~30seg"
        echo "  migrate   - Actualización CON migraciones"
        echo "  init      - Setup inicial (primera vez)"
        echo ""
        echo -e "${GREEN}Comandos de utilidad:${NC}"
        echo "  pull      - Solo descargar última imagen"
        echo "  restart   - Reiniciar servicios"
        echo "  logs      - Ver logs en tiempo real"
        echo "  status    - Ver estado de contenedores"
        echo "  stop      - Detener todo"
        echo ""
        echo -e "${YELLOW}Workflow recomendado:${NC}"
        echo "  1. En local: ./build-and-push.sh v1.0.0"
        echo "  2. En servidor: ./deploy-simple.sh deploy"
        echo ""
        exit 1
        ;;
esac
