#!/bin/bash
# Script para limpiar el stack local de Docker Swarm

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

print_header "LIMPIEZA DE DOCKER SWARM LOCAL - MIBOLETA"

# 1. Remover el stack
print_header "[1/4] Removiendo stack 'miboleta'"
if docker stack ls | grep -q miboleta; then
    docker stack rm miboleta
    print_success "Stack removido"
    print_info "Esperando a que los contenedores se detengan..."
    sleep 10
else
    print_warning "Stack 'miboleta' no existe"
fi

# 2. Limpiar volúmenes (OPCIONAL - pregunta al usuario)
print_header "[2/4] Limpieza de volúmenes"
echo -e "${YELLOW}¿Deseas eliminar los volúmenes (se perderán TODOS los datos)?${NC}"
echo "  - miboleta_swarm_mysql_data (Base de datos)"
echo "  - miboleta_swarm_redis_data (Redis)"
echo "  - miboleta_swarm_storage_data (Archivos subidos)"
echo "  - miboleta_swarm_cache_data (Cache de Laravel)"
echo "  - miboleta_swarm_public_files (Archivos públicos/frontend)"
echo ""
read -p "Eliminar volúmenes? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Eliminando volúmenes..."
    docker volume rm miboleta_swarm_mysql_data 2>/dev/null && print_success "miboleta_swarm_mysql_data eliminado" || print_warning "miboleta_swarm_mysql_data no existe"
    docker volume rm miboleta_swarm_redis_data 2>/dev/null && print_success "miboleta_swarm_redis_data eliminado" || print_warning "miboleta_swarm_redis_data no existe"
    docker volume rm miboleta_swarm_storage_data 2>/dev/null && print_success "miboleta_swarm_storage_data eliminado" || print_warning "miboleta_swarm_storage_data no existe"
    docker volume rm miboleta_swarm_cache_data 2>/dev/null && print_success "miboleta_swarm_cache_data eliminado" || print_warning "miboleta_swarm_cache_data no existe"
    docker volume rm miboleta_swarm_nginx_logs 2>/dev/null && print_success "miboleta_swarm_nginx_logs eliminado" || print_warning "miboleta_swarm_nginx_logs no existe"
    docker volume rm miboleta_swarm_mysql_backups 2>/dev/null && print_success "miboleta_swarm_mysql_backups eliminado" || print_warning "miboleta_swarm_mysql_backups no existe"
    docker volume rm miboleta_swarm_public_files 2>/dev/null && print_success "miboleta_swarm_public_files eliminado" || print_warning "miboleta_swarm_public_files no existe"
else
    print_info "Volúmenes mantenidos (los datos persisten)"
fi

# 3. Limpiar la red
print_header "[3/4] Limpieza de redes"
if docker network ls | grep -q miboleta_swarm_network; then
    docker network rm miboleta_swarm_network 2>/dev/null && print_success "Red eliminada" || print_warning "La red se eliminará automáticamente"
else
    print_info "Red no existe o ya fue eliminada"
fi

# 4. Salir de Swarm (OPCIONAL)
print_header "[4/4] Docker Swarm"
echo -e "${YELLOW}¿Deseas salir de Docker Swarm mode?${NC}"
read -p "Salir de Swarm? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker swarm leave --force
    print_success "Has salido de Docker Swarm mode"
else
    print_info "Permaneces en Docker Swarm mode"
fi

echo ""
print_header "✅ LIMPIEZA COMPLETA"
echo ""
print_success "El stack local ha sido limpiado"
echo ""
echo -e "${BLUE}Estado actual:${NC}"
docker stack ls
echo ""
echo -e "${BLUE}Volúmenes restantes:${NC}"
docker volume ls | grep miboleta || echo "  (ninguno)"
echo ""
