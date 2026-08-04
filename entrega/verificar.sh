#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# MiBoleta — comprobar que la copia entregada funciona
# ---------------------------------------------------------------------------
# Ejecuta comprobaciones reales contra la aplicación levantada y deja
# constancia escrita del resultado, con fecha, versión y commit.
#
# Ese registro es el punto del entregable: no basta con entregar un disco, hay
# que poder demostrar que ESA copia funcionaba en ESA fecha. El archivo que
# genera se adjunta al acta de entrega.
#
#   ./verificar.sh
# ---------------------------------------------------------------------------
set -uo pipefail

cd "$(dirname "$0")"

PUERTO="${MIBOLETA_HTTP_PORT:-9090}"
BASE="http://localhost:${PUERTO}"
SALIDA="evidencia/ejecucion-$(date +%Y%m%d-%H%M%S).log"

mkdir -p evidencia

verde() { printf '\033[0;32m%s\033[0m\n' "$1"; }
rojo() { printf '\033[0;31m%s\033[0m\n' "$1"; }

TOTAL=0
OK=0

registrar() { echo "$1" | tee -a "$SALIDA"; }

comprobar() {
  local descripcion="$1"; shift
  TOTAL=$((TOTAL + 1))
  if "$@" >/dev/null 2>&1; then
    OK=$((OK + 1))
    registrar "  [OK]    ${descripcion}"
  else
    registrar "  [FALLA] ${descripcion}"
  fi
}

{
  echo "==========================================================="
  echo " MiBoleta — verificación de la copia entregada"
  echo "==========================================================="
  echo " Fecha ....... $(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo " Equipo ...... $(uname -s) $(uname -m) — $(hostname)"
  [ -f VERSION.txt ] && sed 's/^/ /' VERSION.txt
  echo "==========================================================="
  echo
} | tee "$SALIDA"

registrar "Servicios en ejecución:"
docker compose -f docker-compose.entrega.yml -p miboleta_entrega ps \
  --format '  {{.Service}}: {{.Status}}' 2>/dev/null | tee -a "$SALIDA"
registrar ""

registrar "Comprobaciones:"

# Salud de la aplicación y sus dependencias (base de datos y cache).
comprobar "La aplicación responde" \
  curl -fsS "${BASE}/api/health/check"

comprobar "La aplicación está lista para atender peticiones" \
  curl -fsS "${BASE}/api/health/ready"

# Que el frontend se sirva, no solo la API.
comprobar "El frontend carga" \
  curl -fsS "${BASE}/"

# Una autenticación real: prueba de extremo a extremo (frontend, API, base de
# datos y hash de contraseñas). Si esto pasa, la aplicación funciona de verdad.
comprobar "Un usuario puede iniciar sesión" \
  curl -fsS -X POST "${BASE}/api/login" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"login":"aprobador@miboleta.demo","password":"password"}'

# Que la base traiga los datos de demostración sembrados.
comprobar "La base tiene datos de demostración" \
  bash -c "curl -fsS -X POST '${BASE}/api/login' \
      -H 'Content-Type: application/json' -H 'Accept: application/json' \
      -d '{\"login\":\"aprobador@miboleta.demo\",\"password\":\"password\"}' \
    | grep -q 'Corporaci'"

registrar ""
registrar "-----------------------------------------------------------"
registrar " Resultado: ${OK}/${TOTAL} comprobaciones correctas"
registrar "-----------------------------------------------------------"

echo
if [ "$OK" -eq "$TOTAL" ]; then
  verde "La aplicación entregada funciona correctamente."
  echo "Constancia guardada en: ${SALIDA}"
  echo "Adjunta este archivo al acta de entrega."
  exit 0
else
  rojo "Hay comprobaciones que fallaron. Revisa ${SALIDA}"
  echo "Si la aplicación acaba de arrancar, espera un minuto y reintenta."
  exit 1
fi
