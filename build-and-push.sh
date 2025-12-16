#!/bin/bash
# Build and Push to Docker Hub
# Este script construye la imagen con el frontend incluido y la sube a Docker Hub

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Load DOCKER_USERNAME from .env.production if exists
if [ -f ".env.production" ]; then
    export $(grep "^DOCKER_USERNAME=" .env.production | xargs)
fi

# Configuration
DOCKER_USERNAME="${DOCKER_USERNAME:-tu-usuario-dockerhub}"
IMAGE_NAME="miboleta"
VERSION="${1:-latest}"
FULL_IMAGE="${DOCKER_USERNAME}/${IMAGE_NAME}:${VERSION}"

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

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    print_error "Docker no está instalado"
    exit 1
fi

# Check if logged in to Docker Hub
if ! docker info | grep -q "Username: ${DOCKER_USERNAME}"; then
    print_error "No estás logueado en Docker Hub"
    print_info "Ejecuta: docker login"
    exit 1
fi

print_header "MIBOLETA - BUILD & PUSH"

print_info "Imagen: ${FULL_IMAGE}"
print_info "Versión: ${VERSION}"

# Build image
print_header "Construyendo imagen Docker"
docker build -t "${FULL_IMAGE}" .
print_success "Imagen construida: ${FULL_IMAGE}"

# Tag as latest if not already
if [ "${VERSION}" != "latest" ]; then
    print_info "Taggeando también como latest..."
    docker tag "${FULL_IMAGE}" "${DOCKER_USERNAME}/${IMAGE_NAME}:latest"
fi

# Push to Docker Hub
print_header "Subiendo imagen a Docker Hub"
docker push "${FULL_IMAGE}"

if [ "${VERSION}" != "latest" ]; then
    docker push "${DOCKER_USERNAME}/${IMAGE_NAME}:latest"
fi

print_success "Imagen publicada en Docker Hub"

# Show image info
print_header "Información de la imagen"
docker images | grep "${IMAGE_NAME}"

print_header "¡BUILD & PUSH COMPLETADO!"
print_info ""
print_success "Imagen disponible en: ${FULL_IMAGE}"
print_info "Para desplegar en producción:"
print_info "  ssh usuario@servidor"
print_info "  cd miboleta"
print_info "  ./deploy.sh pull"
