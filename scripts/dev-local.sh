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

# ============================================
# Puertos HOST (fuente única de verdad)
# Overridables por entorno: MIBOLETA_HTTP_PORT=9000 npm run dev:local
# Los mismos defaults viven en docker-compose.yml (${VAR:-default}).
# Se exportan para (a) sustitución en docker compose y (b) leerlos en vite.config.ts.
# ============================================
export MIBOLETA_HTTP_PORT="${MIBOLETA_HTTP_PORT:-8090}"
export MIBOLETA_HTTPS_PORT="${MIBOLETA_HTTPS_PORT:-8443}"
export MIBOLETA_MYSQL_PORT="${MIBOLETA_MYSQL_PORT:-3307}"
export MIBOLETA_REDIS_PORT="${MIBOLETA_REDIS_PORT:-6399}"
export MIBOLETA_REVERB_PORT="${MIBOLETA_REVERB_PORT:-8085}"
export MIBOLETA_ADMINER_PORT="${MIBOLETA_ADMINER_PORT:-8091}"

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
VITE_API_URL=http://localhost:${MIBOLETA_HTTP_PORT}/api
VITE_REVERB_APP_KEY="miboleta-key"
VITE_REVERB_HOST="localhost"
VITE_REVERB_PORT="${MIBOLETA_REVERB_PORT}"
VITE_REVERB_SCHEME="http"
VITE_SHOW_TEST_USERS=false
EOF
echo -e "${GREEN}  ✓ .env.local configurado${NC}"

# ============================================
# 2. Restaurar backend/.env
# ============================================
if [ -f "backend/.env" ]; then
    sed -i '' "s|^APP_URL=.*|APP_URL=http://localhost:${MIBOLETA_HTTP_PORT}|" backend/.env
    sed -i '' "s|^FRONTEND_URL=.*|FRONTEND_URL=http://localhost:5173|" backend/.env
    sed -i '' "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:${MIBOLETA_HTTP_PORT},localhost|" backend/.env
    sed -i '' "s|^REVERB_HOST=.*|REVERB_HOST=localhost|" backend/.env
    sed -i '' "s|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"localhost\"|" backend/.env
    sed -i '' "s|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=\"${MIBOLETA_REVERB_PORT}\"|" backend/.env
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

# Levantar/actualizar contenedores. `up -d` es idempotente: solo recrea los
# servicios cuya definición cambió (p. ej. si cambiaste los puertos MIBOLETA_*),
# y deja intactos los que ya estén corriendo correctamente.
echo -e "${YELLOW}  → Levantando/actualizando contenedores...${NC}"
docker compose up -d

echo ""

# ============================================
# 4. Esperar a que el backend esté listo
# ============================================
echo -e "${BLUE}[3/4] Esperando a que el backend esté listo...${NC}"

MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -s http://localhost:${MIBOLETA_HTTP_PORT}/api/health > /dev/null 2>&1 || \
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
echo -e "  ${BLUE}API:${NC}       http://localhost:${MIBOLETA_HTTP_PORT}/api"
echo -e "  ${BLUE}WebSocket:${NC} ws://localhost:${MIBOLETA_REVERB_PORT}"
echo -e "  ${BLUE}Adminer:${NC}   http://localhost:${MIBOLETA_ADMINER_PORT}"
echo ""
echo -e "${YELLOW}Presiona Ctrl+C para detener${NC}"
echo ""

# ============================================
# 6. Iniciar Vite
# ============================================
exec npm run dev:vite
