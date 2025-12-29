#!/bin/bash
# ===========================================
# Script para probar VPN + SSH localmente
# Igual que en GitHub Actions
# ===========================================

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Cargar variables desde .env.deploy si existe
if [ -f "scripts/.env.deploy" ]; then
    source scripts/.env.deploy
else
    echo -e "${RED}Error: No existe scripts/.env.deploy${NC}"
    echo "Crea el archivo con:"
    echo "  cp scripts/.env.deploy.example scripts/.env.deploy"
    echo "  nano scripts/.env.deploy"
    exit 1
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   PROBAR DEPLOY LOCALMENTE${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Verificar que openfortivpn esté instalado
if ! command -v openfortivpn &> /dev/null; then
    echo -e "${YELLOW}openfortivpn no está instalado. Instalando...${NC}"
    brew install openfortivpn
fi

# Crear archivo de config VPN temporal
echo -e "${GREEN}[1/4] Creando configuración VPN...${NC}"
cat << EOF > /tmp/vpn_config_test
host = ${VPN_HOST}
port = ${VPN_PORT}
username = ${VPN_USER}
password = ${VPN_PASS}
trusted-cert = ${VPN_CERT}
set-dns = 0
EOF

echo "Config VPN creada en /tmp/vpn_config_test"

# Conectar VPN
echo ""
echo -e "${GREEN}[2/4] Conectando a VPN...${NC}"
echo "Ejecutando: sudo openfortivpn -c /tmp/vpn_config_test"
echo ""
echo -e "${YELLOW}Se requiere password de sudo${NC}"

# Ejecutar en background y capturar logs
sudo openfortivpn -c /tmp/vpn_config_test > /tmp/vpn_test.log 2>&1 &
VPN_PID=$!
echo "VPN PID: $VPN_PID"

# Esperar conexión
echo ""
echo -e "${GREEN}[3/4] Esperando conexión VPN (60s)...${NC}"
CONNECTED=false
for i in {1..12}; do
    sleep 5
    if ifconfig | grep -qE "ppp|tun|utun"; then
        echo -e "${GREEN}✓ Interfaz VPN detectada${NC}"
        CONNECTED=true
        break
    fi
    echo "Esperando interfaz VPN... ($((i*5))s)"
    # Mostrar últimas líneas del log
    tail -3 /tmp/vpn_test.log 2>/dev/null || true
done

# Mostrar log de VPN
echo ""
echo "=== LOG DE VPN ==="
cat /tmp/vpn_test.log || true
echo "=================="

if [ "$CONNECTED" = false ]; then
    echo ""
    echo -e "${RED}❌ VPN no se conectó${NC}"
    echo "Revisa el log arriba para ver el error"
    sudo kill $VPN_PID 2>/dev/null || true
    rm -f /tmp/vpn_config_test
    exit 1
fi

# Probar conectividad
echo ""
echo -e "${GREEN}[4/4] Probando conectividad SSH...${NC}"
echo "Ping a ${SSH_HOST}..."
ping -c 3 ${SSH_HOST} || echo "⚠ Ping falló (puede estar bloqueado)"

echo ""
echo "Probando puerto SSH ${SSH_PORT}..."
nc -zv -w 5 ${SSH_HOST} ${SSH_PORT} 2>&1 || echo "⚠ Puerto SSH no responde"

echo ""
echo "Probando SSH..."
sshpass -p "${SSH_PASS}" ssh \
    -o ConnectTimeout=10 \
    -o StrictHostKeyChecking=no \
    -p ${SSH_PORT} \
    ${SSH_USER}@${SSH_HOST} "echo '✓ SSH conectado exitosamente!' && hostname"

# Limpiar
echo ""
echo -e "${GREEN}Desconectando VPN...${NC}"
sudo kill $VPN_PID 2>/dev/null || true
rm -f /tmp/vpn_config_test

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}   ✓ PRUEBA COMPLETADA${NC}"
echo -e "${GREEN}========================================${NC}"
