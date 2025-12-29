#!/bin/bash
# ===========================================
# Script para obtener VPN_CERT y probar SSH
# ===========================================

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}=== Obtener VPN_CERT para GitHub Actions ===${NC}"
echo ""
echo "Este script te ayuda a obtener el certificado de la VPN"
echo "que necesitas configurar en GitHub Secrets como VPN_CERT"
echo ""

# Configuración (cambiar estos valores)
VPN_HOST="190.223.20.42"
VPN_PORT="43443"

echo -e "${GREEN}Paso 1: Obtener el certificado VPN${NC}"
echo "Ejecutando: openssl s_client -connect ${VPN_HOST}:${VPN_PORT}"
echo ""

# Obtener el certificado y calcular SHA256
CERT_OUTPUT=$(echo | openssl s_client -connect ${VPN_HOST}:${VPN_PORT} 2>/dev/null | openssl x509 -fingerprint -sha256 -noout 2>/dev/null)

if [ -z "$CERT_OUTPUT" ]; then
    echo -e "${RED}No se pudo obtener el certificado.${NC}"
    echo "Intenta manualmente:"
    echo "  echo | openssl s_client -connect ${VPN_HOST}:${VPN_PORT} 2>/dev/null | openssl x509 -fingerprint -sha256 -noout"
else
    # Extraer solo el hash SHA256
    SHA256_HASH=$(echo "$CERT_OUTPUT" | sed 's/sha256 Fingerprint=//i' | tr -d ':' | tr '[:upper:]' '[:lower:]')
    
    echo -e "${GREEN}✓ Certificado obtenido${NC}"
    echo ""
    echo -e "${YELLOW}VPN_CERT para GitHub Secrets:${NC}"
    echo ""
    echo "$SHA256_HASH"
    echo ""
    echo -e "${YELLOW}Copia este valor y actualiza el secret VPN_CERT en GitHub${NC}"
fi

echo ""
echo "=========================================="
echo ""

echo -e "${GREEN}Paso 2: Probar conexión SSH (debes estar conectado a la VPN)${NC}"
echo ""
echo "¿Estás conectado a la VPN con FortiClient? (y/n)"
read -r CONNECTED

if [ "$CONNECTED" = "y" ]; then
    SSH_HOST="10.18.10.200"
    SSH_PORT="5022"
    SSH_USER="usuario_externo"
    
    echo "Probando SSH a ${SSH_HOST}:${SSH_PORT}..."
    echo "Ejecutando: ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
    echo ""
    
    # Probar ping
    echo "Probando ping..."
    ping -c 3 ${SSH_HOST}
    
    echo ""
    echo "Ahora intenta conectar manualmente:"
    echo "  ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
else
    echo "Conéctate primero a la VPN con FortiClient y vuelve a ejecutar este script."
fi
