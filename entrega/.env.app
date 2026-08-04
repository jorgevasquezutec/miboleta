# Configuración del backend para el stack de ENTREGA / DEMOSTRACIÓN.
# Todos los valores son de demostración: esta copia no toca la infraestructura
# del proveedor ni contiene datos reales de ninguna empresa.

APP_NAME=MiBoleta
APP_ENV=production
# APP_KEY propia de esta copia, generada al armar el paquete. NO es la de
# producción: el entrypoint no ejecuta key:generate, así que sin una clave
# válida aquí la aplicación no arranca.
APP_KEY=base64:wk2Xqx5ulgGw4USrov1zrk1q4i1R5zoBTHTSPM2vMfE=
APP_DEBUG=false
APP_TIMEZONE=America/Lima
APP_URL=http://localhost:9090
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miboleta
DB_USERNAME=miboleta
DB_PASSWORD=demo_pass

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=reverb

# Mailpit: los correos NO salen a internet, se quedan en un buzón local que se
# abre en http://localhost:9025. Imprescindible para demostrar la firma de
# documentos, que manda un código de verificación por correo.
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=no-responder@miboleta.demo
MAIL_FROM_NAME=MiBoleta

LOG_CHANNEL=stack
LOG_LEVEL=warning

REVERB_APP_ID=miboleta
REVERB_APP_KEY=miboleta-key
REVERB_APP_SECRET=miboleta-secret-demo
# Destino al que app y horizon PUBLICAN los eventos: el nombre del servicio.
# Con 0.0.0.0 se conectaban a si mismos y ningun evento llegaba a Reverb, asi
# que la campana nunca avisaba. El bind del servidor no se toca: viene del
# flag --host=0.0.0.0 del compose.
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Servicio de firma PAdES (contenedor `signer` de este mismo stack).
SIGNER_BASE_URL=http://signer:8000
SIGNER_TIMEOUT=120
