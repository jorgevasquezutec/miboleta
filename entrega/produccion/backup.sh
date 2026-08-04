#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# MiBoleta — copia de seguridad
# ---------------------------------------------------------------------------
# Respalda la base de datos y los documentos almacenados.
#
#   ./backup.sh                 guarda en ./backups
#   DESTINO=/mnt/nas ./backup.sh
#
# Automatícelo con cron, por ejemplo cada noche a las 2:00:
#   0 2 * * * cd /opt/miboleta/produccion && ./backup.sh >> backups/cron.log 2>&1
#
# IMPORTANTE: copie los respaldos FUERA del servidor. Una copia que vive en la
# misma máquina no protege de la avería de esa máquina.
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE="docker-compose.produccion.yml"
PROYECTO="${PROYECTO:-miboleta}"
DESTINO="${DESTINO:-$PWD/backups}"
FECHA="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$DESTINO"

echo "== Copia de seguridad de MiBoleta =="
echo "   Destino: $DESTINO"
echo

# --- Base de datos ---------------------------------------------------------
# Las credenciales se leen DENTRO del contenedor: no pasan por la línea de
# comandos ni quedan en el historial del shell.
echo "1/3  Base de datos..."
# MYSQL_PWD y no `-p<clave>`: evita el aviso "Using a password on the command
# line interface can be insecure" en cada ejecución, que en un cron nocturno
# acaba llenando el log de ruido y ocultando los errores de verdad.
docker compose -f "$COMPOSE" -p "$PROYECTO" exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --single-transaction --quick \
             --routines --no-tablespaces "$MYSQL_DATABASE" | gzip' \
  > "$DESTINO/miboleta-db-${FECHA}.sql.gz"
echo "     $(du -h "$DESTINO/miboleta-db-${FECHA}.sql.gz" | cut -f1)"

# --- Documentos ------------------------------------------------------------
echo "2/3  Documentos almacenados..."
VOLUMEN="${PROYECTO}_storage_data"
docker run --rm -v "${VOLUMEN}:/data:ro" -v "$DESTINO:/out" alpine \
  tar czf "/out/miboleta-storage-${FECHA}.tgz" -C /data app 2>/dev/null
echo "     $(du -h "$DESTINO/miboleta-storage-${FECHA}.tgz" | cut -f1)"

# --- Configuración ---------------------------------------------------------
# El .env lleva la APP_KEY. Sin ella no se descifran las contraseñas de correo
# ni la del certificado de firma, aunque se tenga la base de datos intacta.
echo "3/3  Configuración (.env, incluye APP_KEY)..."
cp .env "$DESTINO/miboleta-env-${FECHA}.txt"
chmod 600 "$DESTINO/miboleta-env-${FECHA}.txt"
echo "     guardado con permisos restringidos"

echo
echo "Copia terminada: $DESTINO"
echo "Recuerde llevarla fuera de este servidor."
