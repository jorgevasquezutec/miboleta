#!/bin/bash

# =============================================================================
# Script para volver a desarrollo local (localhost)
# =============================================================================

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Restaurando configuración localhost   ${NC}"
echo -e "${BLUE}========================================${NC}"

# ============================================
# 1. Restaurar .env.local (Frontend Vite)
# ============================================
cat > .env.local << EOF
# API Configuration - Desarrollo local
VITE_API_URL=http://localhost/api
VITE_REVERB_APP_KEY="miboleta-key"
VITE_REVERB_HOST="localhost"
VITE_REVERB_PORT="8085"
VITE_REVERB_SCHEME="http"
EOF
echo -e "${GREEN}✓ .env.local restaurado${NC}"

# ============================================
# 2. Restaurar backend/.env
# ============================================
if [ -f "backend/.env" ]; then
    # APP_URL
    sed -i '' "s|^APP_URL=.*|APP_URL=http://localhost|" backend/.env
    echo -e "${GREEN}✓ APP_URL restaurado${NC}"
    
    # FRONTEND_URL
    sed -i '' "s|^FRONTEND_URL=.*|FRONTEND_URL=http://localhost:5173|" backend/.env
    echo -e "${GREEN}✓ FRONTEND_URL restaurado${NC}"
    
    # SANCTUM_STATEFUL_DOMAINS
    sed -i '' "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost|" backend/.env
    echo -e "${GREEN}✓ SANCTUM_STATEFUL_DOMAINS restaurado${NC}"
    
    # REVERB_HOST
    sed -i '' "s|^REVERB_HOST=.*|REVERB_HOST=localhost|" backend/.env
    echo -e "${GREEN}✓ REVERB_HOST restaurado${NC}"
    
    # VITE_REVERB_HOST
    sed -i '' "s|^VITE_REVERB_HOST=.*|VITE_REVERB_HOST=\"localhost\"|" backend/.env
    echo -e "${GREEN}✓ VITE_REVERB_HOST restaurado${NC}"
else
    echo -e "${YELLOW}⚠ backend/.env no encontrado${NC}"
fi

echo ""
echo -e "${GREEN}✓ Configuración restaurada a localhost${NC}"
echo ""
echo -e "${BLUE}Nota:${NC} Si el backend Docker ya está corriendo,"
echo -e "necesitas reiniciarlo:"
echo -e "${YELLOW}  docker compose restart${NC}"
echo ""
