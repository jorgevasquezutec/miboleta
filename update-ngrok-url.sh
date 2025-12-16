#!/bin/bash
# Helper script para actualizar la URL de ngrok en .env.production

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ -z "$1" ]; then
    echo -e "${YELLOW}Uso: $0 <ngrok-url>${NC}"
    echo ""
    echo "Ejemplos:"
    echo "  $0 https://abc123.ngrok.io"
    echo "  $0 abc123.ngrok.io"
    echo ""
    exit 1
fi

# Extract domain from URL
NGROK_URL="$1"
NGROK_DOMAIN=$(echo "$NGROK_URL" | sed -E 's|https?://||')

echo -e "${BLUE}Actualizando archivos de configuración con ngrok URL...${NC}"
echo "Domain: $NGROK_DOMAIN"

# Update .env.production (backend Laravel)
sed -i.bak \
    -e "s|APP_URL=.*|APP_URL=https://${NGROK_DOMAIN}|" \
    -e "s|SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${NGROK_DOMAIN}|" \
    -e "s|SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" \
    .env.production

echo -e "${GREEN}✓ .env.production actualizado (backend)${NC}"

# Update .env.production.local (frontend Vite)
sed -i.bak \
    -e "s|VITE_API_URL=.*|VITE_API_URL=https://${NGROK_DOMAIN}/api|" \
    -e "s|VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"${NGROK_DOMAIN}\"|" \
    -e "s|VITE_REVERB_SCHEME=.*|VITE_REVERB_SCHEME=\"https\"|" \
    .env.production.local

echo -e "${GREEN}✓ .env.production.local actualizado (frontend)${NC}"
echo ""
echo -e "${BLUE}Siguiente paso:${NC}"
echo "  1. ./build-and-push.sh v1.0.x"
echo "  2. ./deploy.sh deploy"
echo ""
echo -e "${YELLOW}Tu app estará en: https://${NGROK_DOMAIN}${NC}"
