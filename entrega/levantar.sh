#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# MiBoleta — levantar la aplicación entregada
# ---------------------------------------------------------------------------
# Arranca la plataforma completa en esta máquina, sin conexión a los servidores
# del proveedor. La primera vez tarda unos minutos: carga las imágenes desde el
# disco, crea la base y la puebla con datos de demostración.
#
#   ./levantar.sh
#
# Para detenerla:  ./detener.sh
# Para comprobar que arrancó bien:  ./verificar.sh
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")"

PROYECTO="miboleta_entrega"
COMPOSE="docker-compose.entrega.yml"
PUERTO="${MIBOLETA_HTTP_PORT:-9090}"

azul() { printf '\033[0;34m%s\033[0m\n' "$1"; }
verde() { printf '\033[0;32m%s\033[0m\n' "$1"; }
rojo() { printf '\033[0;31m%s\033[0m\n' "$1"; }

echo
azul "== MiBoleta — arranque de la copia entregada =="
echo

# --- 1. Requisitos ---------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
  rojo "No se encontró Docker."
  echo "   Instala Docker Desktop desde https://www.docker.com/products/docker-desktop"
  echo "   y vuelve a ejecutar este script."
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  rojo "Docker está instalado pero no está en ejecución."
  echo "   Abre Docker Desktop, espera a que diga 'Running' y reintenta."
  exit 1
fi

verde "Docker disponible."

# --- 2. Imágenes -----------------------------------------------------------
# Se cargan del disco y NO se descargan de internet: así el paquete arranca sin
# conexión, y sobre todo queda congelada la versión exacta que se entregó. Una
# etiqueta remota se puede mover o borrar; un archivo del disco, no.
if [ -d "imagenes" ] && compgen -G "imagenes/*.tar" > /dev/null; then
  azul "Cargando imágenes desde el disco (puede tardar unos minutos)..."
  for tar in imagenes/*.tar; do
    echo "   $(basename "$tar")"
    docker load -i "$tar" >/dev/null
  done
  verde "Imágenes cargadas."
else
  azul "No hay imágenes en ./imagenes: se descargarán del registro público."
fi

# --- 3. Arranque -----------------------------------------------------------
azul "Levantando los servicios..."
docker compose -f "$COMPOSE" -p "$PROYECTO" up -d

# --- 4. Espera -------------------------------------------------------------
# El primer arranque corre migraciones y siembra la base; hasta que termine, la
# aplicación responde 500. Se espera al endpoint de salud en vez de a un tiempo
# fijo, que en una máquina lenta se queda corto.
azul "Esperando a que la aplicación esté lista..."
LISTA=false
for i in $(seq 1 60); do
  if curl -fsS "http://localhost:${PUERTO}/api/health/check" >/dev/null 2>&1; then
    LISTA=true
    break
  fi
  sleep 5
  printf '.'
done
echo

if [ "$LISTA" != true ]; then
  rojo "La aplicación no respondió tras 5 minutos."
  echo "   Revisa los registros con:"
  echo "     docker compose -f $COMPOSE -p $PROYECTO logs app"
  exit 1
fi

# --- 5. Listo --------------------------------------------------------------
echo
verde "== La aplicación está funcionando =="
echo
echo "   Aplicación .......... http://localhost:${PUERTO}"
echo "   Correo (Mailpit) .... http://localhost:${MIBOLETA_MAILPIT_PORT:-9025}"
echo "   Base de datos ....... http://localhost:${MIBOLETA_ADMINER_PORT:-9091}"
echo
echo "   Usuarios de prueba (contraseña: password)"
echo "     Super Administrador ... admin@email.com"
echo "     Admin Clientes ........ admin.clientes@miboleta.demo"
echo "     Admin Empleados ....... admin@corporacionabc.com"
echo "     Aprobador ............. aprobador@miboleta.demo"
echo "     Empleado .............. juan.perez@corporacionabc.com"
echo
echo "   Los datos son ficticios, creados para esta demostración."
echo "   Los correos no salen a internet: se ven en Mailpit."
echo
