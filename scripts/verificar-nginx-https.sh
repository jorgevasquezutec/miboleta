#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Comprueba que el bloque HTTPS de nginx.conf se puede activar
# ---------------------------------------------------------------------------
# El bloque de HTTPS va comentado, y el cliente lo activa a mano quitando el
# "# " de cada linea entre dos marcas. Eso es fragil de una forma que no se ve
# leyendo el archivo: cualquier linea que no sea configuracion y quede entre
# las marcas —una linea de adorno, una nota— se descomenta tambien, y nginx
# muere al arrancar con `unknown directive`.
#
# Ya paso dos veces. Este script lo reproduce: descomenta igual que lo haria el
# cliente y valida el resultado con `nginx -t` de verdad.
#
#   ./scripts/verificar-nginx-https.sh
#
# Necesita Docker. Devuelve 0 si el bloque es activable.
# ---------------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "$0")/.."
CONF="entrega/produccion/nginx.conf"

verde() { printf '\033[0;32m%s\033[0m\n' "$1"; }
rojo()  { printf '\033[0;31m%s\033[0m\n' "$1"; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "== Comprobando que el bloque HTTPS de $CONF se puede activar =="
echo

# 1. Descomentar exactamente como dice la guia: quitar el "# " de cada linea
#    ENTRE las marcas, sin tocar las marcas.
python3 - "$CONF" "$TMP/nginx.conf" <<'PY'
import re, sys
src = open(sys.argv[1]).read().split('\n')
try:
    ini = next(i for i, l in enumerate(src) if 'DESCOMENTAR DESDE AQUI' in l)
    fin = next(i for i, l in enumerate(src) if 'DESCOMENTAR HASTA AQUI' in l)
except StopIteration:
    sys.exit('No encuentro las marcas DESCOMENTAR en el archivo.')
out = [re.sub(r'^# ?', '', l) if ini < i < fin else l for i, l in enumerate(src)]
# Y el paso opcional del 301, que tambien se documenta.
txt = '\n'.join(out).replace(
    '    listen 80;',
    '    listen 80;\n    return 301 https://$host$request_uri;', 1)
open(sys.argv[2], 'w').write(txt)
PY

# 2. Certificado de mentira: `nginx -t` no valida el contenido, pero sin los
#    archivos aborta con "cannot load certificate" y no llegaria a revisar la
#    sintaxis, que es lo que aqui interesa.
mkdir -p "$TMP/ssl"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
  -keyout "$TMP/ssl/privkey.pem" -out "$TMP/ssl/fullchain.pem" \
  -subj "/CN=prueba" >/dev/null 2>&1

# 3. Validar con el mismo nginx que se usa en produccion.
if docker run --rm \
     -v "$TMP/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
     -v "$TMP/ssl:/etc/nginx/ssl:ro" \
     nginx:1.27-alpine nginx -t >"$TMP/salida" 2>&1; then
  verde "El bloque HTTPS se activa sin errores de sintaxis."
else
  rojo "El bloque HTTPS NO se puede activar:"
  echo
  grep -E 'emerg|error' "$TMP/salida" | sed 's/^/    /' || cat "$TMP/salida"
  echo
  echo "  Causa habitual: entre las marcas DESCOMENTAR quedo una linea que no"
  echo "  es configuracion. Todo lo que hay en medio se descomenta."
  echo
  echo "  Config generada para inspeccionar:  $TMP/nginx.conf"
  trap - EXIT
  exit 1
fi

# 4. Las locations del bloque de 443 deben ser las mismas que las del de 80. Si
#    alguien anade una a HTTP y olvida HTTPS, al activar el cifrado deja de
#    funcionar algo que si iba sin cifrar. Paso con /api, /horizon y /health.
python3 - "$TMP/nginx.conf" <<'PY'
import re, sys
txt = open(sys.argv[1]).read()
# Partir por los server{} de primer nivel contando llaves.
bloques, prof, ini = [], 0, None
for m in re.finditer(r'server\s*\{|\{|\}', txt):
    t = m.group()
    if t.startswith('server'):
        if prof == 0: ini = m.start()
        prof += 1
    elif t == '{':
        if prof: prof += 1
    else:
        if prof:
            prof -= 1
            if prof == 0: bloques.append(txt[ini:m.end()])

http  = next((b for b in bloques if 'listen 80' in b), None)
https = next((b for b in bloques if 'listen 443' in b), None)
if not http or not https:
    sys.exit('  No encuentro los dos bloques server.')

loc = lambda b: {m.group(1).strip() for m in re.finditer(r'location\s+([^{]+)\{', b)}
faltan = loc(http) - loc(https)
if faltan:
    print('  Estas locations estan en HTTP pero NO en HTTPS:')
    for f in sorted(faltan): print(f'    {f}')
    sys.exit(1)
print(f'  Las {len(loc(http))} locations de HTTP estan tambien en HTTPS.')

# El fallback del SPA tiene que servir index.html: con index.php, todos los
# enlaces profundos (/vacaciones, /usuarios) devuelven 404 de Laravel.
m = re.search(r'location\s+/\s*\{(.*?)\}', https, re.S)
if m and 'index.html' not in m.group(1):
    sys.exit('  El "location /" de HTTPS no sirve index.html: los enlaces\n'
             '  profundos del SPA darian 404.')
print('  El "location /" de HTTPS sirve el SPA (index.html).')
PY

echo
verde "Comprobacion superada."
