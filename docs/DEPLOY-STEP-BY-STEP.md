# 🚀 Guía Paso a Paso: Deploy MiBoleta a Producción

Esta guía te lleva desde tu estado actual (rama `development`, sin subir cambios) hasta tener la aplicación corriendo en producción con Docker Swarm.

---

## 📋 Resumen de Pasos

| Fase | Dónde | Qué hacer |
|------|-------|-----------|
| **Fase 1** | Local | Preparar código y subir a GitHub |
| **Fase 2** | GitHub | Configurar secrets |
| **Fase 3** | Servidor | Instalar Docker y configurar |
| **Fase 4** | GitHub | Hacer deploy |
| **Fase 5** | Verificar | Comprobar que todo funcione |

---

# FASE 1: Preparar código (Local)

## Paso 1.1: Verificar archivos de configuración

Asegúrate de tener estos archivos en tu repo:

```bash
# Verificar que existen
ls -la config/.env.vite.swarm
ls -la docker-stack.yml
ls -la scripts/setup-server.sh
ls -la .github/workflows/docker-build-deploy.yml
```

## Paso 1.2: Editar variables de Vite para producción

Edita `config/.env.vite.swarm` con tu dominio real:

```env
# config/.env.vite.swarm
VITE_API_URL=https://tudominio.com/api
VITE_REVERB_APP_KEY="base64:TU_REVERB_KEY_AQUI"
VITE_REVERB_HOST="tudominio.com"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
```

> **Nota**: Si aún no tienes dominio, usa la IP del servidor temporalmente:
> ```env
> VITE_API_URL=http://IP_DEL_SERVIDOR/api
> VITE_REVERB_HOST="IP_DEL_SERVIDOR"
> VITE_REVERB_PORT="80"
> VITE_REVERB_SCHEME="http"
> ```

## Paso 1.3: Commit y push a development

```bash
git add .
git commit -m "feat: configuración de Docker Swarm para producción"
git push origin development
```

## Paso 1.4: Merge a main (cuando estés listo)

**Opción A: Desde GitHub (recomendado)**
1. Ve a GitHub → tu repo → Pull requests
2. New pull request
3. base: `main` ← compare: `development`
4. Create pull request
5. **NO hacer merge todavía** - primero configura el servidor

**Opción B: Desde terminal**
```bash
git checkout main
git merge development
# NO hacer push todavía
```

---

# FASE 2: Configurar GitHub Secrets

## Paso 2.1: Crear Personal Access Token

1. Ve a GitHub → Settings (tu perfil) → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token (classic)
3. Nombre: `miboleta-deploy`
4. Scopes: 
   - `read:packages`
   - `write:packages`
5. Generate token
6. **Copia el token** (no lo verás de nuevo)

## Paso 2.2: Configurar Secrets del repositorio

Ve a tu repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

### Si usas VPN para conectar al servidor:

| Secret | Valor |
|--------|-------|
| `VPN_HOST` | Host de tu VPN (ej: `vpn.empresa.com`) |
| `VPN_PORT` | Puerto VPN (ej: `443`) |
| `VPN_USER` | Tu usuario VPN |
| `VPN_PASS` | Tu contraseña VPN |
| `VPN_CERT` | Certificado SHA256 de la VPN* |

> *Para obtener VPN_CERT: `openfortivpn vpn.empresa.com:443 -u usuario 2>&1 | grep "certificate"

### Secrets de SSH (REQUERIDOS):

| Secret | Valor |
|--------|-------|
| `SSH_HOST` | IP o hostname del servidor (ej: `192.168.1.100`) |
| `SSH_PORT` | Puerto SSH (ej: `22`) |
| `SSH_USER` | Usuario SSH (ej: `deploy` o `root`) |
| `SSH_PASS` | Contraseña SSH |

---

# FASE 3: Configurar el Servidor

## Paso 3.1: Conectarte al servidor

```bash
ssh usuario@IP_DEL_SERVIDOR
```

## Paso 3.2: Instalar Docker

```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# IMPORTANTE: Cierra sesión y vuelve a entrar para que surta efecto
exit
ssh usuario@IP_DEL_SERVIDOR

# Verificar instalación
docker --version
```

## Paso 3.3: Inicializar Docker Swarm

```bash
docker swarm init
```

Si tienes múltiples IPs, especifica cuál usar:
```bash
docker swarm init --advertise-addr TU_IP_PRINCIPAL
```

## Paso 3.4: Crear estructura de directorios

```bash
sudo mkdir -p /opt/miboleta/{config,ssl,backups}
sudo chown -R $USER:$USER /opt/miboleta
cd /opt/miboleta
```

## Paso 3.5: Generar credenciales seguras

```bash
# Ejecuta estos comandos y guarda los resultados
echo "DB_ROOT_PASSWORD: $(openssl rand -base64 24)"
echo "DB_PASSWORD: $(openssl rand -base64 24)"
echo "REDIS_PASSWORD: $(openssl rand -base64 24)"
echo "APP_KEY: base64:$(openssl rand -base64 32)"
echo "REVERB_APP_KEY: base64:$(openssl rand -base64 32)"
echo "REVERB_APP_SECRET: $(openssl rand -hex 32)"
```

Guarda estos valores, los usarás en los siguientes pasos.

## Paso 3.6: Crear archivo .env.stack

```bash
cat > /opt/miboleta/.env.stack << 'EOF'
# Variables para Docker (MySQL, Redis)
IMAGE_TAG=latest

DB_ROOT_PASSWORD=PEGAR_DB_ROOT_PASSWORD_GENERADO
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=PEGAR_DB_PASSWORD_GENERADO

REDIS_PASSWORD=PEGAR_REDIS_PASSWORD_GENERADO
EOF

# Editar con tus valores reales
nano /opt/miboleta/.env.stack
```

## Paso 3.7: Crear archivo config/.env (Laravel)

```bash
cat > /opt/miboleta/config/.env << 'EOF'
APP_NAME=MiBoleta
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com
APP_KEY=PEGAR_APP_KEY_GENERADO

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=PEGAR_DB_PASSWORD_GENERADO

REDIS_HOST=redis
REDIS_PASSWORD=PEGAR_REDIS_PASSWORD_GENERADO
REDIS_PORT=6379
REDIS_DB=0

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REVERB_APP_ID=miboleta
REVERB_APP_KEY=PEGAR_REVERB_APP_KEY_GENERADO
REVERB_APP_SECRET=PEGAR_REVERB_APP_SECRET_GENERADO
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="tudominio.com"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
EOF

# Editar con tus valores reales
nano /opt/miboleta/config/.env
```

> **IMPORTANTE**: `DB_PASSWORD` y `REDIS_PASSWORD` deben ser **IDÉNTICOS** en `.env.stack` y `config/.env`

## Paso 3.8: Crear config de Nginx

```bash
cat > /opt/miboleta/config/nginx.conf << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.html index.php;

    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    client_max_body_size 20M;

    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri /index.php?$query_string;
    }

    location /api {
        try_files $uri /index.php?$query_string;
    }

    location /horizon {
        try_files $uri /index.php?$query_string;
    }

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

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ /\. {
        deny all;
    }
}
EOF
```

## Paso 3.9: Crear config de MySQL

```bash
cat > /opt/miboleta/config/my.cnf << 'EOF'
[mysqld]
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
max_connections=200
innodb_buffer_pool_size=256M
EOF
```

## Paso 3.10: Login a GitHub Container Registry

```bash
# Usa el Personal Access Token que creaste en Paso 2.1
docker login ghcr.io -u TU_USUARIO_GITHUB

# Cuando pida password, pega el Personal Access Token
```

## Paso 3.11: Verificar estructura final

```bash
ls -la /opt/miboleta/
# Deberías ver:
# .env.stack
# config/
#   .env
#   nginx.conf
#   my.cnf
# ssl/  (vacío por ahora)
# backups/
```

---

# FASE 4: Hacer el Deploy

## Paso 4.1: Merge a main y push

**Desde tu máquina local:**

```bash
# Si aún no hiciste merge
git checkout main
git merge development
git push origin main
```

## Paso 4.2: Verificar que el workflow se ejecute

1. Ve a GitHub → tu repo → **Actions**
2. Deberías ver "Build & Deploy" ejecutándose
3. Espera a que termine (5-10 minutos la primera vez)

## Paso 4.3: Si el workflow falla

Revisa los logs en GitHub Actions para ver el error:
- **Error de VPN**: Verifica los secrets VPN
- **Error de SSH**: Verifica SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS
- **Error de Docker**: Verifica que hiciste `docker login ghcr.io` en el servidor

---

# FASE 5: Verificar el Deploy

## Paso 5.1: Verificar servicios (en el servidor)

```bash
ssh usuario@IP_DEL_SERVIDOR
docker stack services miboleta
```

Deberías ver algo como:
```
NAME               MODE         REPLICAS   IMAGE
miboleta_app       replicated   1/1        ghcr.io/jorgevasquezutec/miboleta:latest
miboleta_nginx     replicated   1/1        nginx:1.27-alpine
miboleta_db        replicated   1/1        mysql:8.0.40
miboleta_redis     replicated   1/1        redis:7.4-alpine
miboleta_horizon   replicated   1/1        ghcr.io/jorgevasquezutec/miboleta:latest
miboleta_reverb    replicated   1/1        ghcr.io/jorgevasquezutec/miboleta:latest
miboleta_adminer   replicated   1/1        adminer:4.8.1
```

## Paso 5.2: Ver logs si algo falla

```bash
# Ver logs de la app
docker service logs miboleta_app -f

# Ver logs de nginx
docker service logs miboleta_nginx -f

# Ver logs de la base de datos
docker service logs miboleta_db -f
```

## Paso 5.3: Probar la aplicación

```bash
# Health check
curl http://IP_DEL_SERVIDOR/health

# API
curl http://IP_DEL_SERVIDOR/api

# Desde tu navegador
http://IP_DEL_SERVIDOR
```

---

# 📝 Resumen de Archivos

## En tu repositorio (se suben a GitHub):
```
miboleta/
├── config/.env.vite.swarm    # Variables Vite para producción
├── docker-stack.yml          # Definición del stack
├── Dockerfile                # Build de la imagen
├── scripts/setup-server.sh   # Script de setup (referencia)
└── .github/workflows/docker-build-deploy.yml
```

## En el servidor (se crean manualmente):
```
/opt/miboleta/
├── .env.stack                # Credenciales Docker
├── config/
│   ├── .env                  # Variables Laravel
│   ├── nginx.conf            # Config Nginx
│   └── my.cnf                # Config MySQL
├── ssl/                      # Certificados (si tienes dominio)
└── backups/
```

---

# 🆘 Troubleshooting

## Error: "Access denied for user 'miboleta'"
Las credenciales no coinciden. Verifica que `DB_PASSWORD` sea igual en:
- `/opt/miboleta/.env.stack`
- `/opt/miboleta/config/.env`

Si ya desplegaste con credenciales incorrectas:
```bash
docker stack rm miboleta
docker volume rm miboleta_swarm_mysql_data
# Corrige las credenciales y vuelve a desplegar
```

## Error: "unauthorized" al hacer docker pull
```bash
docker logout ghcr.io
docker login ghcr.io -u TU_USUARIO
# Usa el Personal Access Token como password
```

## Error: "network not found"
```bash
docker stack rm miboleta
sleep 10
docker stack deploy -c docker-stack.yml miboleta
```

## Ver todos los contenedores
```bash
docker ps -a
```

## Reiniciar un servicio
```bash
docker service update --force miboleta_app
```

---

# 🔄 Deploys Futuros

Una vez configurado, para hacer nuevos deploys solo necesitas:

```bash
# En tu máquina local
git checkout development
# ... hacer cambios ...
git add .
git commit -m "tu mensaje"
git push origin development

# Crear PR y merge a main
# O directamente:
git checkout main
git merge development
git push origin main
# El deploy se ejecuta automáticamente
```
