#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Arma el paquete de entrega al cliente
# ---------------------------------------------------------------------------
# Uso:  ./scripts/armar-entrega.sh v1.1.0
#
# Produce una carpeta lista para copiar a un disco externo, con:
#   fuentes/         código de la versión exacta, exportado del TAG
#   imagenes/        imágenes Docker de esa misma versión
#   documentacion/   manual y documentación técnica en PDF
#   *.sh / *.bat     scripts de arranque y verificación
#   VERSION.txt      versión, fecha, commit y digests
#   MANIFIESTO-SHA256.txt   huella de todos los archivos
#
# POR QUÉ ASÍ (cada decisión responde a un requisito del entregable):
#
# - Las fuentes salen de `git archive` del TAG, no de copiar la carpeta. Copiar
#   arrastraría .env con credenciales, node_modules, vendor y basura del
#   directorio de trabajo. El archivo exportado contiene exactamente lo que está
#   versionado en ese commit, ni más ni menos.
#
# - Las imágenes se guardan con `docker save` y se referencian por DIGEST. Una
#   etiqueta como :latest se puede mover o borrar; dentro de dos años no prueba
#   nada. El .tar del disco congela el contenido exacto y además hace que el
#   paquete arranque sin internet.
#
# - El manifiesto SHA-256 permite demostrar que el disco no se alteró después de
#   la entrega. Su propio hash se transcribe en el acta firmada: eso es lo que
#   ancla el contenido digital a un documento en papel.
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")/.."
RAIZ="$PWD"

TAG="${1:-}"
if [ -z "$TAG" ]; then
  echo "Uso: $0 <tag>   (ejemplo: $0 v1.1.0)"
  exit 1
fi

IMAGEN_APP="${IMAGEN_APP:-ghcr.io/jorgevasquezutec/miboleta:latest}"
IMAGEN_SIGNER="${IMAGEN_SIGNER:-ghcr.io/jorgevasquezutec/miboleta-signer:latest}"
FECHA="$(date +%Y-%m-%d)"
# Sin la fecha en el nombre: con ella, cada día que se rearmaba dejaba una
# carpeta nueva de 2 GB junto a las anteriores y había que adivinar cuál era la
# buena a la hora de entregar. El nombre es estable y se reemplaza en el sitio;
# la fecha de armado queda registrada dentro, en VERSION.txt y en el acta.
DESTINO="${DESTINO:-$RAIZ/dist/MIBOLETA-ENTREGA-${TAG}}"

azul() { printf '\033[0;34m%s\033[0m\n' "$1"; }
rojo() { printf '\033[0;31m%s\033[0m\n' "$1"; }

# --- Comprobaciones previas ------------------------------------------------
# Un entregable tiene que ser reproducible: si se arma desde un árbol sucio o
# desde un commit sin etiquetar, nadie puede volver a construir lo entregado.
if ! git rev-parse "$TAG" >/dev/null 2>&1; then
  rojo "El tag '$TAG' no existe. Créalo primero:"
  echo "   git tag -a $TAG -m 'Entrega al cliente'"
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  rojo "Hay cambios sin commitear. El paquete debe salir de un árbol limpio."
  git status --short
  exit 1
fi

COMMIT="$(git rev-parse "$TAG^{commit}")"

azul "== Armando entrega ${TAG} (${COMMIT:0:12}) =="
rm -rf "$DESTINO"
mkdir -p "$DESTINO"/{fuentes,imagenes,documentacion,evidencia}

# --- 1. Fuentes ------------------------------------------------------------
azul "1/6  Exportando fuentes del tag..."
git archive --format=zip --prefix="miboleta-${TAG}/" -o "$DESTINO/fuentes/miboleta-src-${TAG}.zip" "$TAG"

# NO se incluye el historial de git (antes iba un `git bundle --all` de 62 MB).
#
# El bundle llevaba TODAS las ramas con TODA su historia, y de ahí se recupera
# cualquier archivo que alguna vez estuvo versionado aunque hoy no esté en el
# árbol: bastaba `git clone` del bundle y un `git show` para leer la cotización
# con nuestras tarifas. Excluirla con export-ignore no servía de nada, porque
# eso solo afecta a `git archive`, no al historial.
#
# El .zip de arriba es `git archive` del tag: exactamente el código entregado,
# sin historia y sin nada que no deba salir. Para el acta es incluso mejor,
# porque su contenido corresponde al commit que figura en VERSION.txt y se
# puede comprobar. Quien continúe el desarrollo parte de ahí.

# --- 2. Imágenes -----------------------------------------------------------
azul "2/6  Guardando imágenes Docker..."
IMAGENES=("$IMAGEN_APP" "$IMAGEN_SIGNER" "mysql:8.0" "redis:7.4-alpine" "nginx:1.27-alpine" "adminer:4.8.1" "axllent/mailpit:latest")
FALTANTES=()

for img in "${IMAGENES[@]}"; do
  nombre="$(echo "$img" | tr '/:' '__')"
  printf '     %-55s' "$img"

  # Si no está en local, se intenta bajar. Y si tampoco así, se reintenta
  # forzando amd64: la imagen del signer se construye solo para esa
  # arquitectura, así que en un Mac con Apple Silicon no baja por defecto.
  if ! docker image inspect "$img" >/dev/null 2>&1; then
    docker pull -q "$img" >/dev/null 2>&1 \
      || docker pull -q --platform linux/amd64 "$img" >/dev/null 2>&1 \
      || true
  fi

  if docker save "$img" -o "$DESTINO/imagenes/${nombre}.tar" 2>/dev/null; then
    echo "ok"
  else
    echo "NO DISPONIBLE"
    rm -f "$DESTINO/imagenes/${nombre}.tar"
    FALTANTES+=("$img")
  fi
done

# Un paquete al que le falta una imagen no arranca en casa del cliente. Se
# avisa al final y con código de error, en vez de entregar algo roto en
# silencio: es preferible descubrirlo aquí que delante del cliente.
if [ ${#FALTANTES[@]} -gt 0 ]; then
  echo
  rojo "No se pudieron guardar estas imágenes:"
  printf '   - %s\n' "${FALTANTES[@]}"
  echo
  echo "   El paquete quedaría incompleto y no arrancaría sin internet."
  echo "   Construye o descarga esas imágenes y vuelve a ejecutar este script."
  exit 1
fi

azul "3/6  Anotando digests..."
{
  echo "# Digests de las imágenes entregadas — ${FECHA}"
  echo "# Fijan el contenido EXACTO: una etiqueta se puede mover, un digest no."
  echo
  for img in "$IMAGEN_APP" "$IMAGEN_SIGNER" "mysql:8.0" "redis:7.4-alpine" "nginx:1.27-alpine" "adminer:4.8.1" "axllent/mailpit:latest"; do
    d="$(docker image inspect "$img" --format '{{index .RepoDigests 0}}' 2>/dev/null || echo 'sin digest (imagen local)')"
    printf '%-55s %s\n' "$img" "$d"
  done
} > "$DESTINO/imagenes/DIGESTS.txt"

# --- 3. Documentación ------------------------------------------------------
azul "4/6  Copiando documentación..."
# Los documentos salen de dist/documentacion, que es donde los dejan los
# generadores. Cada uno va en PDF y en Word: el PDF es lo que se imprime y se
# firma, y el .docx lo pide el cliente para poder editarlo por su cuenta.
# Si falta alguno se ABORTA en vez de avisar y seguir: antes el aviso se perdía
# entre el resto de la salida y el disco se entregaba sin manual, que es
# justo uno de los documentos que el cliente pidió.
FALTAN_DOC=""
for doc in MiBoleta-Manual-de-Usuario.pdf \
           MiBoleta-Documentacion-Tecnica.pdf \
           MiBoleta-Guia-de-Instalacion.pdf \
           MiBoleta-Manual-de-Usuario.docx \
           MiBoleta-Documentacion-Tecnica.docx \
           MiBoleta-Guia-de-Instalacion.docx; do
  if [ -f "dist/documentacion/$doc" ]; then
    cp "dist/documentacion/$doc" "$DESTINO/documentacion/"
  else
    FALTAN_DOC="$FALTAN_DOC $doc"
  fi
done

if [ -n "$FALTAN_DOC" ]; then
  echo
  echo "ERROR: faltan documentos en dist/documentacion/:"
  for p in $FALTAN_DOC; do echo "    - $p"; done
  echo
  echo "  Genéralos y vuelve a ejecutar:"
  echo "    (cd docs/pdf-generator && npm run generate:all)"
  rm -rf "$DESTINO"
  exit 1
fi

# --- 4. Scripts de arranque ------------------------------------------------
azul "5/6  Copiando scripts de arranque..."
cp entrega/*.sh entrega/*.yml entrega/nginx.conf entrega/.env.app "$DESTINO/" 2>/dev/null || true
cp entrega/LEEME.md "$DESTINO/"
[ -f entrega/levantar.bat ] && cp entrega/*.bat "$DESTINO/" 2>/dev/null || true

# El acta se copia con version, fecha y commit ya rellenados. El hash del
# manifiesto NO se sustituye aquí: se calcula después y se transcribe a mano al
# firmar, que es justamente el gesto que ancla lo digital al papel.
sed -e "s/__VERSION__/${TAG}/g" \
    -e "s/__FECHA__/${FECHA}/g" \
    -e "s/__COMMIT__/${COMMIT}/g" \
    -e "s/__HASH_MANIFIESTO__/(ver HUELLA-DEL-MANIFIESTO.txt y transcribir aquí al firmar)/g" \
    entrega/ACTA-DE-ENTREGA.md > "$DESTINO/ACTA-DE-ENTREGA.md"

cat > "$DESTINO/VERSION.txt" <<EOF
MiBoleta — versión entregada
=============================
Versión ......... ${TAG}
Fecha ........... ${FECHA}
Commit .......... ${COMMIT}
Imagen app ...... ${IMAGEN_APP}
Imagen signer ... ${IMAGEN_SIGNER}

El código de fuentes/ corresponde exactamente a este commit.
Las huellas de todos los archivos están en MANIFIESTO-SHA256.txt.
EOF

# --- 5. Manifiesto ---------------------------------------------------------
azul "6/6  Calculando manifiesto SHA-256..."
cd "$DESTINO"
find . -type f ! -name 'MANIFIESTO-SHA256.txt' -print0 \
  | sort -z \
  | xargs -0 shasum -a 256 > MANIFIESTO-SHA256.txt

HASH_MANIFIESTO="$(shasum -a 256 MANIFIESTO-SHA256.txt | cut -d' ' -f1)"

# El hash del manifiesto va en su PROPIO archivo, no dentro de VERSION.txt.
# Escribirlo dentro sería circular: VERSION.txt está cubierto por el manifiesto,
# así que modificarlo después de calcularlo hace que la verificación falle
# justo en ese archivo. Y un manifiesto que no valida no sirve como prueba.
cat > HUELLA-DEL-MANIFIESTO.txt <<EOF
SHA-256 de MANIFIESTO-SHA256.txt
=================================
${HASH_MANIFIESTO}

Este valor debe transcribirse en el acta de entrega firmada: es lo que ancla el
contenido completo de este disco a un documento en papel. El manifiesto cubre
todos los archivos; este hash cubre el manifiesto.

Comprobar el contenido del disco:
  shasum -a 256 -c MANIFIESTO-SHA256.txt

Comprobar que el propio manifiesto no fue alterado:
  shasum -a 256 MANIFIESTO-SHA256.txt
EOF

echo
azul "== Paquete listo =="
echo "   $DESTINO"
echo
du -sh "$DESTINO"
echo
echo "   SHA-256 del manifiesto:"
echo "   ${HASH_MANIFIESTO}"
echo
echo "   Siguiente paso: copiar a DOS discos (uno por parte), arrancar delante"
echo "   del cliente con ./levantar.sh y ./verificar.sh, y adjuntar al acta el"
echo "   archivo que quede en evidencia/."
