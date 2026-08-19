# 🚀 Guía de Deploy - MiBoleta

Deploy automático usando GitHub Actions + Docker Swarm + VPN

---

## 📋 Requisitos Previos

- Servidor con Docker instalado
- Acceso VPN al servidor
- Cuenta GitHub con permisos al repositorio
- GitHub Container Registry (GHCR) habilitado

---

## 🔧 Setup Inicial del Servidor (Una sola vez)

### 1. Ejecutar el script de setup en el servidor

El script ya **no** genera los `.conf` por su cuenta: los copia de `config/` del
repositorio, que es la fuente de verdad. Por eso hay que subir el script **junto
a `config/`**, no suelto a `/tmp/` como decia esta guia antes.

```bash
# Copiar el script Y la carpeta config/ al servidor
ssh user@server 'mkdir -p ~/miboleta-setup/scripts'
scp -r config user@server:~/miboleta-setup/
scp scripts/setup-server.sh user@server:~/miboleta-setup/scripts/

# Conectarse al servidor y ejecutar
ssh user@server
chmod +x ~/miboleta-setup/scripts/setup-server.sh
~/miboleta-setup/scripts/setup-server.sh
```

> Si el servidor tiene un clon del repo, basta con `./scripts/setup-server.sh`.
> Ejecutado sin `config/` al lado, el script deja placeholders **vacios** y avisa
> con el `scp` exacto que falta: son archivos vacios a proposito, porque si la
> ruta del bind-mount no existe Docker crea un **directorio** ahi y el
> contenedor no levanta.

Esto creará la estructura:
```
/opt/miboleta/
├── config/
│   ├── .env          <- Crear y configurar
│   ├── nginx.conf
│   └── my.cnf
├── ssl/              <- Agregar certificados
├── secrets/
└── backups/
```

### 2. Configurar el archivo `.env` de producción

```bash
# En el servidor
cd /opt/miboleta/config
cp .env.example .env
nano .env
```

Variables críticas a configurar:
```env
APP_KEY=                    # Generar con: php artisan key:generate --show
APP_URL=https://tudominio.com

DB_PASSWORD=               # Password seguro para MySQL
DB_ROOT_PASSWORD=          # Password root de MySQL

REDIS_PASSWORD=            # Password seguro para Redis

# Email (si aplica)
MAIL_HOST=
MAIL_USERNAME=
MAIL_PASSWORD=

# Reverb (WebSockets)
REVERB_APP_KEY=           # Generar uno aleatorio
REVERB_APP_SECRET=        # Generar uno aleatorio
```

### 3. Login a GitHub Container Registry

```bash
# En el servidor
docker login ghcr.io -u TU_USUARIO_GITHUB
# Password: usar un Personal Access Token con permiso 'read:packages'
```

**Crear PAT en:** GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token
- Seleccionar scope: `read:packages`

### 4. (Opcional) Configurar SSL

Si usas HTTPS, agregar certificados en `/opt/miboleta/ssl/`:
```bash
/opt/miboleta/ssl/
├── cert.pem
└── key.pem
```

Y descomentar las líneas SSL en `docker-stack.yml` si es necesario.

---

## ⚙️ Configurar GitHub Secrets

En tu repositorio: **Settings → Secrets and variables → Actions → New repository secret**

### Secrets de VPN:
- `VPN_HOST` - Host del servidor VPN (ej: `vpn.empresa.com`)
- `VPN_PORT` - Puerto VPN (usualmente `443`)
- `VPN_USER` - Usuario VPN
- `VPN_PASS` - Contraseña VPN
- `VPN_CERT` - Hash del certificado trusted (obtener con `openfortivpn` en modo verbose)

### Secrets de SSH:
- `SSH_HOST` - IP del servidor (ej: `192.168.1.100`)
- `SSH_PORT` - Puerto SSH (usualmente `22`)
- `SSH_USER` - Usuario SSH (ej: `deploy`)
- `SSH_PASS` - Contraseña SSH

### Secrets de Base de Datos (para el .env remoto):
- `DB_ROOT_PASSWORD` - Password root de MySQL
- `DB_PASSWORD` - Password del usuario de base de datos
- `REDIS_PASSWORD` - Password de Redis

---

## 🚀 Deploy

### Deploy Automático

Cada push a la rama `main` trigger automáticamente:

```bash
git checkout main
git add .
git commit -m "feat: nueva funcionalidad"
git push origin main
```

El workflow hará:
1. ✅ Build de la imagen Docker (frontend + backend)
2. ✅ Push a GitHub Container Registry
3. ✅ Conexión VPN al servidor
4. ✅ Deploy vía SSH usando Docker Swarm
5. ✅ Ejecutar migraciones
6. ✅ Limpiar cachés
7. ✅ Verificar servicios

### Deploy Manual

También puedes trigger manualmente desde GitHub:
- Ve a **Actions** → **Build & Deploy** → **Run workflow**

### Deploy con Tags (Versionado)

Para crear una versión específica:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Esto generará imágenes con tags:
- `ghcr.io/jorgevasquezutec/miboleta:v1.0.0`
- `ghcr.io/jorgevasquezutec/miboleta:latest`

---

## 📊 Verificar el Deploy

### Desde GitHub Actions
- Ve a **Actions** y revisa el workflow
- Verifica que todos los pasos estén en verde ✅

### Desde el servidor

```bash
# Conectarse al servidor
ssh user@server

# Ver servicios del stack
docker stack services miboleta

# Ver logs de un servicio
docker service logs miboleta_app -f

# Ver contenedores corriendo
docker ps | grep miboleta

# Ver estado de servicios
docker stack ps miboleta
```

### Verificar la aplicación

```bash
# Health check
curl http://localhost/health

# Ver la app
curl http://localhost
```

---

## 🔄 Rollback

Si algo sale mal, puedes hacer rollback de los servicios:

```bash
# En el servidor
docker service rollback miboleta_app
docker service rollback miboleta_horizon
docker service rollback miboleta_reverb
```

O actualizar a una imagen específica:

```bash
docker service update --image ghcr.io/jorgevasquezutec/miboleta:v1.0.0 miboleta_app
```

---

## 🛠️ Comandos Útiles

### Ejecutar comandos en el contenedor

```bash
# Encontrar el contenedor
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)

# Ejecutar comandos artisan
docker exec $APP_CONTAINER php artisan migrate
docker exec $APP_CONTAINER php artisan cache:clear
docker exec $APP_CONTAINER php artisan queue:work --once

# Ver logs
docker exec $APP_CONTAINER tail -f storage/logs/laravel.log

# Entrar al contenedor
docker exec -it $APP_CONTAINER sh
```

### Gestionar el stack

```bash
# Actualizar stack (después de cambiar docker-stack.yml)
docker stack deploy -c docker-stack.yml miboleta --with-registry-auth

# Escalar servicios
docker service scale miboleta_app=3

# Remover stack
docker stack rm miboleta
```

### Backups de base de datos

```bash
# Backup
docker exec $(docker ps -qf "name=miboleta_db") \
  mysqldump -u root -p$DB_ROOT_PASSWORD miboleta_prod > backup.sql

# Restore
docker exec -i $(docker ps -qf "name=miboleta_db") \
  mysql -u root -p$DB_ROOT_PASSWORD miboleta_prod < backup.sql
```

---

## 🐛 Troubleshooting

### El workflow falla en la conexión VPN
- Verificar que los secrets `VPN_*` estén correctos
- Revisar que el certificado VPN sea el correcto
- Aumentar el tiempo de espera en el workflow

### El workflow falla en SSH
- Verificar que los secrets `SSH_*` estén correctos
- Verificar que el usuario SSH tenga permisos sudo sin password
- Verificar que la VPN esté conectada correctamente

### Los servicios no inician
- Verificar logs: `docker service logs miboleta_app`
- Verificar que el archivo `.env` esté correcto
- Verificar que la base de datos esté corriendo

### Error de permisos en storage
```bash
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)
docker exec $APP_CONTAINER chown -R www-data:www-data storage bootstrap/cache
```

### Limpiar imágenes antiguas
```bash
docker image prune -a --filter "until=24h"
```

---

## 📦 Estructura del Proyecto

```
miboleta/
├── .github/workflows/
│   └── docker-build-deploy.yml    # Workflow de CI/CD
├── config/                         # Configs para producción
│   ├── .env.example               # Template del .env
│   ├── nginx.conf                 # Nginx config
│   └── my.cnf                     # MySQL config
├── scripts/
│   └── setup-server.sh            # Setup inicial del servidor
├── backend/                        # Laravel backend
├── src/                            # React frontend
├── Dockerfile                      # Multi-stage build
├── docker-stack.yml               # Docker Swarm stack
└── DEPLOY.md                      # Esta guía
```

---

## 🔐 Seguridad

- ✅ Nunca commitear archivos `.env` con valores reales
- ✅ Usar GitHub Secrets para credenciales
- ✅ Rotar passwords regularmente
- ✅ Usar SSL/TLS en producción
- ✅ Configurar firewall en el servidor
- ✅ Limitar acceso SSH por IP
- ✅ Usar VPN para acceso al servidor

---

## 📝 Notas

- El primer deploy puede tomar más tiempo (descarga de imágenes)
- Los deploys subsecuentes son más rápidos (usa cache)
- Las migraciones se ejecutan automáticamente
- El rollout es progresivo (zero-downtime)
