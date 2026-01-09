#!/bin/bash

# =============================================================================
# Script para desarrollo local completo (Docker + Vite)
# Ejecuta: npm run dev:local
# =============================================================================

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  MiBoleta - Desarrollo Local          ${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# ============================================
# 1. Restaurar .env.local (Frontend Vite)
# ============================================
echo -e "${BLUE}[1/4] Configurando variables de entorno...${NC}"

cat > .env.local << EOF
# API Configuration - Desarrollo local
VITE_API_URL=http://localhost/api
VITE_REVERB_APP_KEY="miboleta-key"
VITE_REVERB_HOST="localhost"
VITE_REVERB_PORT="8085"
VITE_REVERB_SCHEME="http"
VITE_SHOW_TEST_USERS=false
EOF
echo -e "${GREEN}  ✓ .env.local configurado${NC}"

# ============================================
# 2. Restaurar backend/.env
# ============================================
if [ -f "backend/.env" ]; then
    sed -i '' "s|^APP_URL=.*|APP_URL=http://localhost|" backend/.env
    sed -i '' "s|^FRONTEND_URL=.*|FRONTEND_URL=http://localhost:5173|" backend/.env
    sed -i '' "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost|" backend/.env
    sed -i '' "s|^REVERB_HOST=.*|REVERB_HOST=localhost|" backend/.env
    sed -i '' "s|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"localhost\"|" backend/.env
    echo -e "${GREEN}  ✓ backend/.env configurado${NC}"
else
    echo -e "${YELLOW}  ⚠ backend/.env no encontrado - copiando de .env.example${NC}"
    if [ -f "backend/.env.example" ]; then
        cp backend/.env.example backend/.env
        echo -e "${GREEN}  ✓ backend/.env creado desde .env.example${NC}"
    else
        echo -e "${RED}  ✗ No se encontró backend/.env.example${NC}"
        exit 1
    fi
fi

echo ""

# ============================================
# 3. Levantar Docker Compose
# ============================================
echo -e "${BLUE}[2/4] Iniciando contenedores Docker...${NC}"

# Verificar si Docker está corriendo
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}  ✗ Docker no está corriendo. Por favor inicia Docker Desktop.${NC}"
    exit 1
fi

# Verificar si los contenedores ya están corriendo
if docker compose ps 2>/dev/null | grep -q "miboleta"; then
    RUNNING=$(docker compose ps --format "{{.State}}" 2>/dev/null | grep -c "running" || echo "0")
    if [ "$RUNNING" -gt 0 ]; then
        echo -e "${GREEN}  ✓ Contenedores ya están corriendo ($RUNNING servicios)${NC}"
    else
        echo -e "${YELLOW}  → Reiniciando contenedores...${NC}"
        docker compose up -d
    fi
else
    echo -e "${YELLOW}  → Levantando contenedores...${NC}"
    docker compose up -d
fi

echo ""

# ============================================
# 4. Esperar a que el backend esté listo
# ============================================
echo -e "${BLUE}[3/4] Esperando a que el backend esté listo...${NC}"

MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -s http://localhost/api/health > /dev/null 2>&1 || \
       docker compose exec -T app php artisan --version > /dev/null 2>&1; then
        echo -e "${GREEN}  ✓ Backend está listo!${NC}"
        break
    fi
    
    ATTEMPT=$((ATTEMPT + 1))
    echo -e "  Esperando... ($ATTEMPT/$MAX_ATTEMPTS)"
    sleep 2
done

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo -e "${YELLOW}  ⚠ Timeout esperando backend. Revisa: docker compose logs app${NC}"
fi

echo ""

# ============================================
# 5. Mostrar información
# ============================================
echo -e "${BLUE}[4/4] Iniciando Vite (Frontend)...${NC}"
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  ✓ Ambiente de desarrollo listo!      ${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "  ${BLUE}Frontend:${NC}  http://localhost:5173"
echo -e "  ${BLUE}API:${NC}       http://localhost/api"
echo -e "  ${BLUE}WebSocket:${NC} ws://localhost:8085"
echo -e "  ${BLUE}Adminer:${NC}   http://localhost:8080"
echo ""
echo -e "${YELLOW}Presiona Ctrl+C para detener${NC}"
echo ""

# ============================================
# 6. Iniciar Vite
# ============================================
exec npm run dev:vite
