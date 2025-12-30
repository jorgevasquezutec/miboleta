#!/bin/bash

# =============================================================================
# Script para desarrollo móvil - Detecta IP y configura entorno automáticamente
# =============================================================================

set -e

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Obtener la IP local (funciona en macOS y Linux)
get_local_ip() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost"
    else
        # Linux
        hostname -I | awk '{print $1}' 2>/dev/null || echo "localhost"
    fi
}

# Directorio del script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Configuración para Desarrollo Móvil  ${NC}"
echo -e "${BLUE}========================================${NC}"

# Detectar IP
LOCAL_IP=$(get_local_ip)
echo -e "${GREEN}✓ IP Local detectada: ${YELLOW}$LOCAL_IP${NC}"

# ============================================
# 1. Actualizar .env.local (Frontend Vite)
# ============================================
cat > .env.local << EOF
# API Configuration - Auto-generado para desarrollo móvil
# IP: $LOCAL_IP | Generado: $(date)
VITE_API_URL=http://$LOCAL_IP/api
VITE_REVERB_APP_KEY="miboleta-key"
VITE_REVERB_HOST="$LOCAL_IP"
VITE_REVERB_PORT="8085"
VITE_REVERB_SCHEME="http"
EOF
echo -e "${GREEN}✓ .env.local actualizado${NC}"

# ============================================
# 2. Actualizar backend/.env
# ============================================
if [ -f "backend/.env" ]; then
    # Usar sed compatible con macOS (-i requiere backup extension)
    
    # APP_URL
    sed -i '' "s|^APP_URL=.*|APP_URL=http://$LOCAL_IP|" backend/.env
    echo -e "${GREEN}✓ APP_URL actualizado${NC}"
    
    # FRONTEND_URL
    sed -i '' "s|^FRONTEND_URL=.*|FRONTEND_URL=http://$LOCAL_IP:5173|" backend/.env
    echo -e "${GREEN}✓ FRONTEND_URL actualizado${NC}"
    
    # SANCTUM_STATEFUL_DOMAINS
    sed -i '' "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=$LOCAL_IP:5173,$LOCAL_IP|" backend/.env
    echo -e "${GREEN}✓ SANCTUM_STATEFUL_DOMAINS actualizado${NC}"
    
    # REVERB_HOST
    sed -i '' "s|^REVERB_HOST=.*|REVERB_HOST=$LOCAL_IP|" backend/.env
    echo -e "${GREEN}✓ REVERB_HOST actualizado${NC}"
    
    # VITE_REVERB_HOST
    sed -i '' "s|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"$LOCAL_IP\"|" backend/.env
    echo -e "${GREEN}✓ VITE_REVERB_HOST actualizado${NC}"
else
    echo -e "${YELLOW}⚠ backend/.env no encontrado${NC}"
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}  ¡Configuración completada!  ${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Accede desde tu celular a:"
echo -e "${YELLOW}  http://$LOCAL_IP:5173/${NC}"
echo ""
echo -e "${BLUE}Nota importante:${NC} Si el backend Docker ya está corriendo,"
echo -e "necesitas reiniciarlo para que tome los nuevos valores:"
echo -e "${YELLOW}  docker compose restart${NC}"
echo ""
