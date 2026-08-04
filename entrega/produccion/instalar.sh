#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# MiBoleta — instalación en un servidor
# ---------------------------------------------------------------------------
# Instala la plataforma desde cero en esta máquina. Es idempotente en lo que
# importa: si algo falla a mitad, se corrige y se vuelve a ejecutar sin dañar
# lo ya hecho.
#
#   1. cp .env.example .env  &&  nano .env      (completar la configuración)
#   2. ./instalar.sh
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")"

COMPOSE="docker-compose.produccion.yml"
# Sobreescribible: PROYECTO=miboleta2 ./instalar.sh
# Permite instalar dos entornos en la misma máquina (por ejemplo, pruebas junto
# a producción) y evita colisionar con otro proyecto de Docker que ya se llame
# igual. Los comandos posteriores necesitan el MISMO valor con -p.
PROYECTO="${PROYECTO:-miboleta}"

azul()  { printf '\033[0;34m%s\033[0m\n' "$1"; }
verde() { printf '\033[0;32m%s\033[0m\n' "$1"; }
rojo()  { printf '\033[0;31m%s\033[0m\n' "$1"; }
ama()   { printf '\033[1;33m%s\033[0m\n' "$1"; }

echo
azul "======================================================="
azul " MiBoleta — instalación en servidor"
azul "======================================================="
echo

# --- 1. Requisitos ---------------------------------------------------------
azul "1/7  Comprobando requisitos..."

command -v docker >/dev/null 2>&1 || {
  rojo "Falta Docker."
  echo "   Instálalo con:  curl -fsSL https://get.docker.com | sh"
  exit 1
}

docker compose version >/dev/null 2>&1 || {
  rojo "Falta el plugin 'docker compose' (v2)."
  echo "   En Debian/Ubuntu:  apt-get install docker-compose-plugin"
  exit 1
}

docker info >/dev/null 2>&1 || {
  rojo "Docker está instalado pero no responde."
  echo "   Arráncalo con:  systemctl start docker"
  echo "   Si no eres root, añade tu usuario al grupo:  usermod -aG docker \$USER"
  exit 1
}

verde "     Docker disponible."

# Las imágenes se publican solo para x86_64 (amd64). En un servidor ARM el
# `docker compose pull` muere con "no matching manifest for linux/arm64", que
# no le dice nada a quien instala. Mejor detenerse aquí con una explicación.
ARQ="$(uname -m)"
case "$ARQ" in
  x86_64|amd64)
    verde "     Arquitectura $ARQ compatible."
    ;;
  *)
    rojo "Arquitectura no compatible: $ARQ"
    echo
    echo "   Las imágenes de MiBoleta se publican para x86_64 (amd64)."
    echo "   Este servidor es $ARQ, así que la descarga fallaría."
    echo
    echo "   Opciones:"
    echo "     1. Instalar en un servidor x86_64 (la mayoría de VPS lo son)."
    echo "     2. Si su Docker admite emulación, forzarla y aceptar que irá"
    echo "        más lento:"
    echo "          FORZAR_AMD64=1 ./instalar.sh"
    echo
    if [ "${FORZAR_AMD64:-0}" != "1" ]; then
      exit 1
    fi
    ama "     FORZAR_AMD64=1: se continúa con emulación (rendimiento reducido)."
    export DOCKER_DEFAULT_PLATFORM=linux/amd64
    ;;
esac

# --- 2. Configuración ------------------------------------------------------
azul "2/7  Comprobando la configuración..."

[ -f .env ] || {
  rojo "No existe el archivo .env"
  echo "   Créalo y complétalo antes de instalar:"
  echo "     cp .env.example .env"
  echo "     nano .env"
  exit 1
}

# Un .env con los valores de ejemplo produce una instalación que arranca pero
# es insegura o no envía correo. Mejor parar aquí que descubrirlo en uso.
if grep -q 'CAMBIAME' .env; then
  rojo "El archivo .env todavía tiene valores sin completar:"
  grep -n 'CAMBIAME' .env | sed 's/=.*/= .../' | sed 's/^/     /'
  echo
  echo "   Complétalos y vuelve a ejecutar este script."
  exit 1
fi

set -a; . ./.env; set +a

if [ "${APP_URL:-}" = "http://localhost" ]; then
  ama "     Aviso: APP_URL sigue siendo http://localhost."
  ama "     Los enlaces de los correos apuntarán ahí y no funcionarán fuera del servidor."
fi

verde "     Configuración completa."

# --- 3. Clave de cifrado ---------------------------------------------------
azul "3/7  Clave de cifrado..."

if [ -z "${APP_KEY:-}" ]; then
  # openssl no está garantizado en una instalación mínima de Ubuntu, así que se
  # recurre a /dev/urandom si falta. Sin clave, la aplicación no arranca.
  if command -v openssl >/dev/null 2>&1; then
    ALEATORIO="$(openssl rand -base64 32)"
  else
    ALEATORIO="$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
  fi
  NUEVA="base64:${ALEATORIO}"
  # Se escribe en el .env para que sobreviva a las recreaciones del contenedor.
  # Si cambiara, dejarían de descifrarse las contraseñas de correo guardadas y
  # la del certificado de firma.
  if grep -q '^APP_KEY=' .env; then
    sed -i.bak "s|^APP_KEY=.*|APP_KEY=${NUEVA}|" .env && rm -f .env.bak
  else
    echo "APP_KEY=${NUEVA}" >> .env
  fi
  export APP_KEY="$NUEVA"
  verde "     Generada y guardada en .env"
  ama "     Haz copia de seguridad del .env: sin esta clave no se recuperan los datos cifrados."
else
  verde "     Ya definida."
fi

# --- 4. Imágenes -----------------------------------------------------------
azul "4/7  Obteniendo las imágenes..."

if [ -d imagenes ] && compgen -G "imagenes/*.tar" > /dev/null; then
  for tar in imagenes/*.tar; do
    echo "     $(basename "$tar")"
    docker load -i "$tar" >/dev/null
  done
  verde "     Cargadas desde el paquete."
else
  # Si la descarga falla no se aborta de inmediato: puede que las imágenes ya
  # estén en la máquina (reinstalación, o cargadas a mano). Solo se para si
  # falta alguna de verdad, y explicando cuál.
  if docker compose -f "$COMPOSE" -p "$PROYECTO" pull 2>/dev/null; then
    verde "     Descargadas del registro."
  else
    ama "     No se pudieron descargar. Comprobando si ya están en el equipo..."
    FALTAN=""
    for img in "${MIBOLETA_IMAGE:-}" "${MIBOLETA_SIGNER_IMAGE:-}" mysql:8.0 redis:7.4-alpine nginx:1.27-alpine; do
      [ -n "$img" ] || continue
      docker image inspect "$img" >/dev/null 2>&1 || FALTAN="$FALTAN $img"
    done

    if [ -n "$FALTAN" ]; then
      rojo "Faltan imágenes y no se pudieron descargar:"
      for img in $FALTAN; do echo "     - $img"; done
      echo
      echo "   Causas habituales:"
      echo "     · El servidor no tiene salida a internet o un cortafuegos"
      echo "       bloquea ghcr.io."
      echo "     · El registro está temporalmente caído."
      echo
      echo "   Solución: pida al proveedor el paquete CON IMÁGENES incluidas,"
      echo "   colóquelas en ./imagenes/ y vuelva a ejecutar este script."
      exit 1
    fi
    verde "     Ya estaban en el equipo."
  fi
fi

# --- 5. Arranque -----------------------------------------------------------
azul "5/7  Arrancando los servicios..."
mkdir -p ssl backups
docker compose -f "$COMPOSE" -p "$PROYECTO" up -d

azul "     Esperando a la base de datos y las migraciones..."
LISTA=false
for i in $(seq 1 60); do
  if docker compose -f "$COMPOSE" -p "$PROYECTO" exec -T app php artisan migrate:status >/dev/null 2>&1; then
    LISTA=true; break
  fi
  sleep 5; printf '.'
done
echo

[ "$LISTA" = true ] || {
  rojo "La aplicación no arrancó."
  echo "   Revisa:  docker compose -f $COMPOSE -p $PROYECTO logs app"
  exit 1
}
verde "     Servicios en marcha."

# --- 6. Datos base ---------------------------------------------------------
azul "6/7  Datos base..."

# Roles y tipos de documento: son catálogos que el sistema necesita para
# funcionar, no datos de demostración. Se siembran solo si faltan, para que
# reejecutar el script no los duplique.
ROLES=$(docker compose -f "$COMPOSE" -p "$PROYECTO" exec -T app \
  php artisan tinker --execute='echo App\Models\Role::count();' 2>/dev/null | tr -dc '0-9' || echo 0)

if [ "${ROLES:-0}" -eq 0 ]; then
  docker compose -f "$COMPOSE" -p "$PROYECTO" exec -T app php artisan db:seed --class=RoleSeeder --force
  docker compose -f "$COMPOSE" -p "$PROYECTO" exec -T app php artisan db:seed --class=DocumentTypeSeeder --force
  verde "     Roles y tipos de documento creados."
else
  verde "     Ya existían."
fi

# --- 7. Administrador ------------------------------------------------------
azul "7/7  Usuario administrador..."
echo
echo "     Crea ahora el Super Administrador de la plataforma."
echo "     Es la cuenta con la que darás de alta las empresas y sus usuarios."
echo

docker compose -f "$COMPOSE" -p "$PROYECTO" exec app php artisan miboleta:crear-root || {
  ama "     No se creó el administrador. Puedes hacerlo después con:"
  echo "       docker compose -f $COMPOSE -p $PROYECTO exec app php artisan miboleta:crear-root"
}

# --- Listo -----------------------------------------------------------------
echo
verde "======================================================="
verde " Instalación terminada"
verde "======================================================="
echo
echo "   Accede en:  ${APP_URL}"
echo
echo "   Comandos útiles:"
echo "     Ver el estado ...... docker compose -f $COMPOSE -p $PROYECTO ps"
echo "     Ver los registros .. docker compose -f $COMPOSE -p $PROYECTO logs -f app"
echo "     Detener ............ docker compose -f $COMPOSE -p $PROYECTO down"
echo "     Arrancar ........... docker compose -f $COMPOSE -p $PROYECTO up -d"
echo
ama "   Antes de usarlo en producción, lee la sección"
ama "   'Después de instalar' de INSTALACION.md: copias de seguridad, HTTPS"
ama "   y certificado de firma."
echo
