# Sidecar de firma digital legal

Este directorio contiene el pipeline de firma digital legal de boletas:

```
normalizar (Ghostscript -> PDF/A-2b)
    -> firmar (pyHanko, PAdES, firma incremental) con el certificado REAL
        -> [opcional] sellar con una TSA externa (RFC 3161)
            -> verificar la firma resultante
```

Hay tres archivos Python, con responsabilidades separadas:

- **`pipeline.py`**: la lógica reutilizable (normalizar/firmar/verificar), sin
  I/O de red ni impresión por consola. Lanza excepciones tipadas
  (`PipelineError` y subclases, cada una con `.stage`) ante cualquier fallo.
- **`app.py`**: la **API HTTP productiva** (FastAPI + uvicorn) que expone ese
  pipeline dentro de `miboleta_network`. Es lo que consume el backend Laravel
  (`App\Services\DocumentSigningService`, vía el Job `SignDocument`) para
  firmar boletas con el certificado ÚNICO de la plataforma
  (`App\Models\SignatureSettings`). Ver "API HTTP" más abajo para el
  contrato completo.
- **`spike_sign.py`**: una **CLI de diagnóstico manual**, útil para probar el
  pipeline a mano dentro del contenedor sin pasar por Laravel/HTTP. Si no se
  le pasa `--pfx`, genera al vuelo un certificado **autofirmado de prueba**
  (RSA 2048) — ver "Cómo interpretar el reporte" antes de sacar conclusiones
  al usarla así. `app.py` (la API real) SIEMPRE requiere un certificado real
  vía `certificate_path`; nunca genera uno de prueba.

## API HTTP (`app.py`)

Escucha en `0.0.0.0:8000` **solo** dentro de `miboleta_network` (el servicio
`signer` en `docker-compose.yml` usa `expose`, no `ports`: no hay puerto
publicado al host).

- `GET /health` -> `{"status": "ok"}`
- `POST /sign` -> firma un PDF con un certificado `.pfx/.p12` real. Body JSON:
  ```json
  {
    "input_path": "/var/www/html/storage/app/documents/.../boleta.pdf",
    "output_path": "/var/www/html/storage/app/documents/.../.signing-tmp/xxx.pdf",
    "certificate_path": "/var/www/html/storage/app/certificates/xxx.pfx",
    "certificate_password": "...",
    "tsa_url": null
  }
  ```
  Responde siempre `HTTP 200` con `{"success": true, "output_path", "signature": {...}}`
  o `{"success": false, "error": "...", "stage": "..."}` para fallos de
  negocio del pipeline (certificado inválido, Ghostscript falló, TSA no
  respondió, firma no íntegra). `422`/`500` quedan para errores de
  transporte (request malformado / excepción no manejada), con el mismo
  envelope `{success, error, stage}`.
- `POST /verify` -> verifica la(s) firma(s) embebidas en un PDF ya firmado
  (mismo envelope de respuesta, con `verification` en vez de `signature`).

Todas las rutas (`input_path`, `output_path`, `certificate_path`) son
absolutas dentro del volumen compartido `./backend:/var/www/html` (el mismo
bind mount que usa `app`/`horizon`); este servicio no conoce tenants,
usuarios ni el modelo de datos de Laravel, solo rutas de archivo.

**Trade-off de seguridad, deliberado**: la contraseña del certificado viaja
en el body JSON de `/sign`. Al no salir nunca de la red interna de Docker
(sin publicar el puerto al host), se considera un riesgo aceptable para este
caso de uso. Si en algún momento `signer` corriera en OTRO host físico, esto
debería revisarse (mTLS, o como mínimo un *shared secret* en un header).

## Por qué un contenedor aparte (Debian, no Alpine)

La app Laravel corre en `php:8.4-fpm-alpine` (musl). `pyHanko` depende de
`cryptography`, que en PyPI solo publica *wheels* `manylinux` (glibc); en
Alpine/musl no hay wheel y tocaría compilar `cryptography` + su backend Rust
en cada build de imagen, lo cual es lento y fragil. Por eso este sidecar usa
`python:3.12-slim` (Debian bookworm, glibc) — imagen y build totalmente
independientes de la imagen PHP de la app.

El sidecar comparte con la app el **mismo bind mount** `./backend:/var/www/html`
para poder leer las boletas ya existentes en `storage/app/documents/...` y,
más adelante, un certificado real en `storage/app/certificates/...`.

## 1. Construir la imagen

```bash
docker compose build signer
```

## 2. Correr el spike sobre una boleta real

Ya existen boletas reales de prueba en el repo, por ejemplo:

```
backend/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf
```

que dentro del contenedor (bind mount de `./backend` a `/var/www/html`) se ve
como:

```
/var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf
```

### Caso básico — sin TSA (sin depender de Internet)

```bash
docker compose run --rm signer python /opt/signer/spike_sign.py \
  /var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf
```

### Con sello de tiempo (TSA pública, requiere Internet desde el contenedor)

```bash
docker compose run --rm signer python /opt/signer/spike_sign.py \
  /var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf \
  --tsa-url https://freetsa.org/tsr
```

Otras TSA públicas alternativas si `freetsa.org` no responde:
`http://timestamp.digicert.com`, `http://timestamp.sectigo.com`.

### Con apariencia de firma visible (sello de texto en la última página)

```bash
docker compose run --rm signer python /opt/signer/spike_sign.py \
  /var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf \
  --visible
```

### Probando ya con un certificado real (.pfx/.p12) en vez del autofirmado

Útil más adelante, cuando el cliente entregue su certificado real. El
certificado debe estar accesible dentro del contenedor (p.ej. montado junto
con `./backend`, o copiado a `storage/app/certificates/`):

```bash
docker compose run --rm \
  -e SIGNER_PFX_PASSWORD='la-contraseña-real' \
  signer python /opt/signer/spike_sign.py \
  /var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf \
  --pfx /var/www/html/storage/app/certificates/empresa.pfx
```

(Usar la variable de entorno `SIGNER_PFX_PASSWORD` en vez de `--pfx-password`
evita dejar la contraseña real en el historial de shell / `docker compose run`.)

### Todas las opciones

```bash
docker compose run --rm signer python /opt/signer/spike_sign.py --help
```

## Qué valida cada etapa

| Etapa | Qué hace | Qué confirma si sale OK | Qué significa si falla (FAIL real) |
|---|---|---|---|
| 0. Validar entrada | Revisa que el archivo exista y tenga cabecera `%PDF-` | El PDF de la boleta es legible | Ruta equivocada o archivo corrupto |
| 1. Normalizar a PDF/A-2b | Ejecuta Ghostscript (`gs -dPDFA=2 ... -sDEVICE=pdfwrite`) con un `PDFA_def.ps` generado al vuelo que referencia el perfil ICC sRGB del sistema | Ghostscript puede reescribir la boleta como PDF/A-2b con `OutputIntent` embebido | Ghostscript no está en el PATH, el PDF de origen tiene algo que Ghostscript no puede reescribir, o falta el perfil ICC |
| 2. Preparar firmante | Genera un certificado autofirmado RSA 2048 (o carga un `.pfx` real si se pasó `--pfx`) y lo deja utilizable por pyHanko | `cryptography` + `pyHanko` pueden generar/cargar material de firma en este entorno | Falla `cryptography`/`pyHanko`, o el `.pfx`/contraseña real están mal |
| 3. Firmar (PAdES) | `pyHanko` agrega una firma incremental PAdES (`SubFilter=ETSI.CAdES.detached`) al PDF/A normalizado, con TSA opcional | El pipeline completo puede producir un PDF firmado a partir de una boleta real | Error real de pyHanko, o la TSA no respondió (ver mensaje específico) |
| 4. Verificar | `pyHanko` relee el PDF firmado y valida la firma (integridad + validez criptográfica), **sin** agregar el cert autofirmado como raíz de confianza | La firma quedó **íntegra y criptográficamente válida** | La firma está rota/corrupta — esto sí sería un problema real del pipeline |

## Cómo interpretar el reporte (importante)

Con el certificado **autofirmado de prueba**, la Etapa 4 va a reportar:

- `Firma intacta: True`
- `Firma criptográficamente válida: True`
- `Cadena de confianza: False`

Eso es el resultado **esperado y correcto**, no un fallo. Es exactamente lo
que vería alguien abriendo el PDF en Adobe Reader: *"la firma es válida, pero
el certificado del firmante no es de confianza"* — porque es un certificado
de prueba autofirmado, no el del cliente emitido por una entidad reconocida.

Lo que hay que mirar para juzgar la **viabilidad técnica** del pipeline es:

1. ¿Llegó sin `FAIL` hasta la Etapa 4? (Ghostscript corrió, pyHanko firmó, el
   archivo de salida existe y pesa algo razonable.)
2. ¿La Etapa 4 reporta `intacta=True` y `válida=True`? (La firma en sí es
   correcta, independientemente de la confianza en el certificado.)

Cuando se pruebe con el certificado real del cliente (`--pfx`), si ese
certificado encadena a una CA que el sistema reconozca, `Cadena de
confianza` podría salir `True`; si no, seguirá en `False` hasta que se
configure el `ValidationContext` con los certificados intermedios/raíz
correctos — eso es trabajo de integración, fuera del alcance de este spike.

## Dónde quedan los archivos generados

Por defecto (sin pasar `--work-dir`), los artefactos del spike quedan en:

```
/var/www/html/storage/app/private/signing-spike/<timestamp>/
  ├── PDFA_def.ps            # definición PDF/A generada para Ghostscript
  ├── normalized.pdfa.pdf    # boleta normalizada a PDF/A-2b
  ├── self-signed-test.pfx   # certificado de prueba (solo si no se pasó --pfx)
  └── signed.pdf             # boleta firmada final
```

Es decir, en el host: `backend/storage/app/private/signing-spike/<timestamp>/`.
Bórralo cuando termines de revisar el resultado; no son documentos reales.

## Limitaciones y pendientes conocidos (léelo antes de decidir)

- **Perfil ICC / conformidad PDF/A estricta**: el paquete Debian
  `ghostscript` **no** trae perfiles ICC propios (a diferencia de los
  instaladores oficiales de Artifex). Este spike instala también
  `icc-profiles-free` (Debian) para tener `/usr/share/color/icc/sRGB.icc` y
  poder generar un `OutputIntent` real. Si por algún motivo ese paquete no
  estuviera disponible, el script sigue corriendo pero avisa con `WARN`: el
  PDF queda reescrito por `pdfwrite` con `-dPDFA=2`, pero sin `OutputIntent`
  no calificaría como PDF/A-2b estrictamente conforme (un validador tipo
  veraPDF lo marcaría como no conforme). Para el propósito de este spike
  (viabilidad de firmar) esto no bloquea nada, pero **antes de producción
  conviene correr veraPDF** contra `normalized.pdfa.pdf` para confirmar
  conformidad completa.
- **TSA requiere Internet** desde el contenedor `signer`. Si el servidor no
  tiene salida a Internet (o solo permite ciertos dominios), `--tsa-url`
  fallará con un mensaje explícito distinguiéndolo de un error de firma. En
  producción probablemente se quiera una TSA de pago con SLA en vez de
  `freetsa.org`.
- **Tamaño de imagen**: `python:3.12-slim` + Ghostscript + pyHanko + deps da
  una imagen de varios cientos de MB (no optimizada en este spike; no se
  intentó usar `--platform` ni compresión de capas).
- **Arquitectura del host**: si se corre en Mac Apple Silicon, Docker
  construye/usa la imagen `linux/arm64`; en el servidor real (probablemente
  `linux/amd64`) hay que reconstruir ahí — no se probaron ambas arquitecturas
  en este spike.
- **Certificado autofirmado ≠ certificado real**: esto solo aplica a
  `spike_sign.py` sin `--pfx` (diagnóstico manual). La API productiva
  (`app.py`) siempre exige un `certificate_path` real; nunca genera uno de
  prueba.
- **Sin pruebas de carga/lote**: el pipeline firma un PDF a la vez por
  request; no se han hecho pruebas de carga con miles de boletas concurrentes.

## Ya implementado (S3-C) vs. pendiente (S3-D)

Implementado:

- API HTTP productiva (`app.py` + `pipeline.py`), consumida por
  `App\Services\DocumentSigningService` (Laravel) vía `Http::` client, con el
  certificado ÚNICO de la plataforma (`App\Models\SignatureSettings`, NO uno
  por tenant/empresa).
- Disparo automático: `App\Jobs\ProcessDocumentChunk` despacha
  `App\Jobs\SignDocument` (cola `signing`) cuando el documento requiere firma
  y `SignatureSettings::current()->signature_enabled` es `true`.
- Disparo on-demand: `POST /api/documents/{id}/sign-digital` (root/admin),
  para reintentar manualmente un documento puntual.
- Reemplazo seguro del archivo: el original en el disco `documents` solo se
  sobreescribe después de que el sidecar confirma éxito Y el archivo firmado
  existe; un fallo en cualquier paso previo deja el original intacto.
- Metadata persistida en `Document::signature` (método, sujeto del firmante,
  hora de firma, si se aplicó TSA, digest, sha256, etc.) y `Document::signed_at`.

Pendiente (fuera de alcance de esta iteración, ver S3-D):

- Endpoint/UI de verificación de un documento ya firmado desde el frontend
  (usar `POST /verify` del sidecar, ya implementado y listo para consumir).
- Validación de conformidad PDF/A-2b estricta con una herramienta externa
  tipo veraPDF (Ghostscript ya embebe el `OutputIntent`, pero no se corrió
  un validador dedicado sobre boletas reales firmadas en producción).
- `ValidationContext` con la cadena de confianza real del certificado del
  cliente (CA intermedia/raíz), para que `trusted` pueda dar `true` con un
  certificado real (hoy pyHanko valida `intact`/`valid` correctamente pero
  no se le pasó ninguna cadena de confianza adicional).
- PAdES-LTA / re-sellado de tiempo a largo plazo (hoy el TSA, si se
  configura, se aplica de forma síncrona dentro del mismo request de firma).
- Pruebas de carga con volúmenes reales (miles de boletas por carga masiva).
