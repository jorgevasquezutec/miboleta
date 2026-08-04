#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Empaqueta el proyecto en un .tar.gz instalable en cualquier servidor
# ---------------------------------------------------------------------------
# Uso:  ./scripts/empaquetar-proyecto.sh v1.1.0
#       CON_IMAGENES=1 ./scripts/empaquetar-proyecto.sh v1.1.0
#
# Produce  dist/miboleta-<tag>.tar.gz  con:
#
#   codigo/            fuentes completas de la versión (git archive del tag)
#   produccion/        compose, .env.example, instalar.sh y nginx.conf
#   documentacion/     manual, documentación técnica e instalación (PDF y MD)
#   imagenes/          (opcional) imágenes Docker, para instalar sin internet
#   LEEME.md           resumen de 3 pasos
#   VERSION.txt        versión, fecha y commit
#   SHA256SUMS.txt     huellas del contenido
#
# Sin CON_IMAGENES el paquete pesa unos 90 MB y descarga las imágenes del
# registro público al instalar. Con imágenes ronda los 2 GB y se instala sin
# conexión, que es lo que hace falta en servidores aislados.
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")/.."
RAIZ="$PWD"

TAG="${1:-}"
[ -n "$TAG" ] || { echo "Uso: $0 <tag>   (ejemplo: $0 v1.1.0)"; exit 1; }

git rev-parse "$TAG" >/dev/null 2>&1 || {
  echo "El tag '$TAG' no existe. Créalo con:  git tag -a $TAG -m 'Version $TAG'"
  exit 1
}

[ -z "$(git status --porcelain)" ] || {
  echo "Hay cambios sin commitear. El paquete debe salir de un árbol limpio."
  git status --short
  exit 1
}

IMAGEN_APP="${IMAGEN_APP:-ghcr.io/jorgevasquezutec/miboleta:latest}"
IMAGEN_SIGNER="${IMAGEN_SIGNER:-ghcr.io/jorgevasquezutec/miboleta-signer:latest}"
COMMIT="$(git rev-parse "$TAG^{commit}")"
FECHA="$(date +%Y-%m-%d)"
NOMBRE="miboleta-${TAG}"
TRABAJO="$RAIZ/dist/$NOMBRE"

azul() { printf '\033[0;34m%s\033[0m\n' "$1"; }

azul "== Empaquetando ${TAG} (${COMMIT:0:12}) =="
rm -rf "$TRABAJO" "$RAIZ/dist/${NOMBRE}.tar.gz"
mkdir -p "$TRABAJO"/{codigo,produccion,documentacion}

# --- Código ----------------------------------------------------------------
# `git archive` del tag y no una copia de la carpeta: copiar arrastraría .env
# con credenciales, node_modules, vendor y basura del directorio de trabajo.
azul "1/5  Exportando el código..."
git archive --format=tar "$TAG" | tar -x -C "$TRABAJO/codigo"

# --- Producción ------------------------------------------------------------
azul "2/5  Preparando los archivos de instalación..."
cp entrega/produccion/docker-compose.produccion.yml "$TRABAJO/produccion/"
cp entrega/produccion/.env.example "$TRABAJO/produccion/"
cp entrega/produccion/instalar.sh "$TRABAJO/produccion/"
cp entrega/produccion/nginx.conf "$TRABAJO/produccion/"
chmod +x "$TRABAJO/produccion/instalar.sh"

# --- Documentación ---------------------------------------------------------
azul "3/5  Copiando la documentación..."
cp docs/INSTALACION.md "$TRABAJO/documentacion/"
for f in docs/MiBoleta-Manual-de-Usuario.pdf docs/MiBoleta-Documentacion-Tecnica.pdf; do
  [ -f "$f" ] && cp "$f" "$TRABAJO/documentacion/" \
    || echo "     falta $(basename "$f") — regenéralo en docs/pdf-generator"
done

# --- Imágenes (opcional) ---------------------------------------------------
if [ "${CON_IMAGENES:-0}" = "1" ]; then
  azul "4/5  Guardando las imágenes Docker (para instalar sin internet)..."
  mkdir -p "$TRABAJO/produccion/imagenes"
  for img in "$IMAGEN_APP" "$IMAGEN_SIGNER" "mysql:8.0" "redis:7.4-alpine" "nginx:1.27-alpine"; do
    nombre="$(echo "$img" | tr '/:' '__')"
    printf '     %-55s' "$img"
    docker image inspect "$img" >/dev/null 2>&1 \
      || docker pull -q "$img" >/dev/null 2>&1 \
      || docker pull -q --platform linux/amd64 "$img" >/dev/null 2>&1 || true
    if docker save "$img" -o "$TRABAJO/produccion/imagenes/${nombre}.tar" 2>/dev/null; then
      echo "ok"
    else
      echo "NO DISPONIBLE"
      rm -f "$TRABAJO/produccion/imagenes/${nombre}.tar"
      echo "     El paquete no podrá instalarse sin internet."
    fi
  done
else
  azul "4/5  Sin imágenes (CON_IMAGENES=1 para incluirlas)."
fi

# --- Metadatos y empaquetado ----------------------------------------------
azul "5/5  Generando metadatos y comprimiendo..."

cat > "$TRABAJO/VERSION.txt" <<EOF
MiBoleta
========
Versión ... ${TAG}
Fecha ..... ${FECHA}
Commit .... ${COMMIT}

El contenido de codigo/ corresponde exactamente a ese commit.
EOF

cat > "$TRABAJO/LEEME.md" <<'EOF'
# MiBoleta

Plataforma de gestión documental y de vacaciones.

## Instalación en tres pasos

```bash
cd produccion
cp .env.example .env && nano .env     # 1. configurar
./instalar.sh                          # 2. instalar
```

3. Abra en el navegador la dirección que puso en `APP_URL`.

El procedimiento completo —requisitos del servidor, HTTPS, copias de seguridad
y resolución de problemas— está en **`documentacion/INSTALACION.md`**.

## Contenido

| Carpeta | Qué es |
| --- | --- |
| `codigo/` | Código fuente completo de esta versión |
| `produccion/` | Todo lo necesario para instalar en un servidor |
| `documentacion/` | Manual de usuario, documentación técnica e instalación |
| `VERSION.txt` | Versión, fecha y commit exacto |
| `SHA256SUMS.txt` | Huellas para comprobar que nada se alteró |

## Comprobar la integridad

```bash
sha256sum -c SHA256SUMS.txt      # Linux
shasum -a 256 -c SHA256SUMS.txt  # macOS
```
EOF

cd "$TRABAJO"
find . -type f ! -name 'SHA256SUMS.txt' -print0 | sort -z | xargs -0 shasum -a 256 > SHA256SUMS.txt

cd "$RAIZ/dist"
tar -czf "${NOMBRE}.tar.gz" "$NOMBRE"
rm -rf "$NOMBRE"

HASH="$(shasum -a 256 "${NOMBRE}.tar.gz" | cut -d' ' -f1)"

echo
azul "== Paquete listo =="
echo "   $RAIZ/dist/${NOMBRE}.tar.gz"
echo "   $(du -h "${NOMBRE}.tar.gz" | cut -f1)"
echo
echo "   SHA-256:"
echo "   ${HASH}"
echo
echo "   Instalación en el servidor:"
echo "     scp dist/${NOMBRE}.tar.gz usuario@servidor:/opt/"
echo "     ssh usuario@servidor"
echo "     cd /opt && tar -xzf ${NOMBRE}.tar.gz && cd ${NOMBRE}/produccion"
echo "     cp .env.example .env && nano .env"
echo "     ./instalar.sh"
