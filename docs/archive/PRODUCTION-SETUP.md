# 🚀 Guía de Deploy a Producción - Docker Swarm

Esta guía explica qué archivos modificar cuando tengas un dominio para producción.

---

## 📋 Checklist de Configuración

Cuando tengas tu dominio (ejemplo: `miboleta.tuempresa.com`), deberás modificar:

| # | Archivo | Qué cambiar |
|---|---------|-------------|
| 1 | `.env.production` | Variables de Vite (frontend) |
| 2 | `config/.env` (servidor) | Variables de Laravel (backend) |
| 3 | `.env.stack` (servidor) | Credenciales de Docker |
| 4 | `config/nginx.conf` | Dominio y SSL |
| 5 | Certificados SSL | Agregar en `/opt/miboleta/ssl/` |

---

## 1️⃣ Frontend: `.env.production`

Este archivo se usa durante el build del frontend (`npm run build`).

```env
# .env.production

# URL de tu API
VITE_API_URL=https://miboleta.tuempresa.com/api

# WebSocket (Reverb) via Nginx proxy
VITE_REVERB_APP_KEY="tu-reverb-key-seguro"
VITE_REVERB_HOST="miboleta.tuempresa.com"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
```

**⚠️ Importante**: Después de cambiar este archivo, debes hacer **rebuild** de la imagen Docker.

---

## 2️⃣ Backend: `config/.env` (en el servidor)

Este archivo va en `/opt/miboleta/config/.env` en tu servidor de producción.

```env
# /opt/miboleta/config/.env

APP_NAME=MiBoleta
APP_ENV=production
APP_DEBUG=false
APP_URL=https://miboleta.tuempresa.com

# Generar con: php artisan key:generate --show
APP_KEY=base64:TU_APP_KEY_GENERADA

# Base de datos
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=TU_PASSWORD_SEGURO_DB

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=TU_PASSWORD_SEGURO_REDIS
REDIS_PORT=6379

# Cache y Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Reverb (WebSocket)
REVERB_APP_ID=miboleta
REVERB_APP_KEY=tu-reverb-key-seguro
REVERB_APP_SECRET=tu-reverb-secret-seguro
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Variables de Vite (para SSR si lo usas)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="miboleta.tuempresa.com"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
```

---

## 3️⃣ Docker: `.env.stack` (en el servidor)

Este archivo va en `/opt/miboleta/.env.stack` en tu servidor.

```env
# /opt/miboleta/.env.stack

IMAGE_TAG=latest

# Database - DEBEN COINCIDIR con config/.env
DB_ROOT_PASSWORD=TU_PASSWORD_ROOT_MYSQL
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=TU_PASSWORD_SEGURO_DB

# Redis - DEBE COINCIDIR con config/.env
REDIS_PASSWORD=TU_PASSWORD_SEGURO_REDIS
```

**⚠️ IMPORTANTE**: Las credenciales `DB_PASSWORD` y `REDIS_PASSWORD` deben ser **IDÉNTICAS** en `.env.stack` y `config/.env`.

---

## 4️⃣ Nginx: `config/nginx.conf`

Ubicación en servidor: `/opt/miboleta/config/nginx.conf`

### Cambios principales:

```nginx
server {
    listen 80;
    server_name miboleta.tuempresa.com;
    
    # Redirigir HTTP a HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name miboleta.tuempresa.com;
    
    # Certificados SSL
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    
    # Configuración SSL moderna
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    
    root /var/www/html/public;
    index index.html index.php;
    
    # ... resto de la configuración igual ...
    
    # Laravel Reverb WebSocket proxy
    location /app {
        proxy_pass http://reverb:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
    }
}
```

---

## 5️⃣ Certificados SSL

Coloca tus certificados en `/opt/miboleta/ssl/`:

```
/opt/miboleta/ssl/
├── fullchain.pem    # Certificado + cadena intermedia
└── privkey.pem      # Clave privada
```

### Opciones para obtener SSL:

**Opción A: Let's Encrypt (gratis)**
```bash
sudo certbot certonly --standalone -d miboleta.tuempresa.com
# Los certificados estarán en /etc/letsencrypt/live/miboleta.tuempresa.com/
# Cópialos a /opt/miboleta/ssl/
```

**Opción B: SSL de tu proveedor**
- Descarga los archivos .pem de tu proveedor
- Renómbralos a `fullchain.pem` y `privkey.pem`

---

## 🔄 Proceso de Deploy

### Primera vez:

```bash
# 1. En tu servidor, ejecuta el setup inicial
./scripts/setup-server.sh

# 2. Edita los archivos de configuración
nano /opt/miboleta/config/.env
nano /opt/miboleta/.env.stack
nano /opt/miboleta/config/nginx.conf

# 3. Agrega certificados SSL
cp /etc/letsencrypt/live/miboleta.tuempresa.com/fullchain.pem /opt/miboleta/ssl/
cp /etc/letsencrypt/live/miboleta.tuempresa.com/privkey.pem /opt/miboleta/ssl/

# 4. Login a GitHub Container Registry
docker login ghcr.io -u TU_USUARIO

# 5. Deploy
cd /opt/miboleta
export $(cat .env.stack | grep -v '^#' | xargs)
docker stack deploy -c docker-stack.yml miboleta
```

### Deploys subsecuentes:

Los deploys automáticos se hacen via GitHub Actions cuando haces push a `main`.

---

## 🔐 Generación de Credenciales Seguras

```bash
# Generar passwords seguros
openssl rand -base64 24  # Para DB_PASSWORD
openssl rand -base64 24  # Para REDIS_PASSWORD
openssl rand -base64 24  # Para DB_ROOT_PASSWORD

# Generar APP_KEY de Laravel
php artisan key:generate --show
# O si no tienes PHP local:
echo "base64:$(openssl rand -base64 32)"

# Generar REVERB_APP_SECRET
openssl rand -hex 32
```

---

## 📊 Resumen Visual

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRODUCCIÓN                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Internet                                                        │
│      │                                                           │
│      │ https://miboleta.tuempresa.com                           │
│      ▼                                                           │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    Nginx (:443)                          │    │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │    │
│  │  │  /      │  │  /api   │  │ /horizon│  │  /app   │    │    │
│  │  │ (React) │  │ (Laravel│  │ (Laravel│  │(WebSocket│    │    │
│  │  └────┬────┘  └────┬────┘  └────┬────┘  └────┬────┘    │    │
│  └───────┼────────────┼────────────┼────────────┼──────────┘    │
│          │            │            │            │                │
│          ▼            ▼            ▼            ▼                │
│     index.html    app:9000     app:9000    reverb:8080          │
│                                                                  │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐            │
│  │   App   │  │  MySQL  │  │  Redis  │  │ Horizon │            │
│  │ (PHP-FPM│  │  :3306  │  │  :6379  │  │ (Queue) │            │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✅ Verificación Post-Deploy

```bash
# Ver estado de servicios
docker stack services miboleta

# Ver logs
docker service logs miboleta_app -f
docker service logs miboleta_nginx -f

# Verificar endpoints
curl https://miboleta.tuempresa.com/health
curl https://miboleta.tuempresa.com/api/health
```

---

## 🆘 Troubleshooting

### Error: "Access denied for user"
Las credenciales en `config/.env` no coinciden con `.env.stack`. Verifica que sean idénticas.

### Error: "Connection refused" a MySQL
MySQL aún está iniciando. Espera 30 segundos o verifica logs: `docker service logs miboleta_db`

### Error: SSL certificate problem
Verifica que los certificados estén en `/opt/miboleta/ssl/` y tengan los nombres correctos.

### WebSocket no conecta
1. Verifica que Reverb esté corriendo: `docker service logs miboleta_reverb`
2. Verifica la config de Nginx tiene el proxy `/app`
3. Verifica `VITE_REVERB_*` en `.env.production`
