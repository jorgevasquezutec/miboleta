#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# MiBoleta — detener la copia entregada
# ---------------------------------------------------------------------------
#   ./detener.sh          detiene los servicios y CONSERVA los datos
#   ./detener.sh --borrar detiene y BORRA la base (vuelve a los datos de fábrica
#                         en el siguiente arranque)
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")"

PROYECTO="miboleta_entrega"
COMPOSE="docker-compose.entrega.yml"

if [ "${1:-}" = "--borrar" ]; then
  echo "Deteniendo y borrando los datos..."
  docker compose -f "$COMPOSE" -p "$PROYECTO" down -v
  echo "Listo. El próximo arranque volverá a crear los datos de demostración."
else
  echo "Deteniendo los servicios (los datos se conservan)..."
  docker compose -f "$COMPOSE" -p "$PROYECTO" down
  echo "Listo. Vuelve a arrancar con ./levantar.sh"
fi
