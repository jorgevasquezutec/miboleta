# 🚀 Guía de Deploy con GitHub Actions y Docker Swarm

Esta guía explica cómo configurar el deploy automático desde GitHub Actions a tu servidor con Docker Swarm.

---

## 📋 Pre-requisitos

### En tu servidor:

1. Docker instalado con Swarm activado
2. Acceso SSH configurado
3. Directorio `/opt/miboleta` creado
4. Archivos de configuración en el servidor

### En GitHub:

1. Repositorio con acceso a GitHub Container Registry (GHCR)
2. Secrets configurados

---

## 🔐 Paso 1: Configurar GitHub Secrets

Ve a tu repositorio en GitHub → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

Crea los siguientes secrets:

### Secrets de VPN (si usas VPN para conectar al servidor):

| Secret | Descripción | Ejemplo |
|--------|-------------|---------|
| `VPN_HOST` | Host del servidor VPN | `vpn.tuempresa.com` |
| `VPN_PORT` | Puerto VPN | `443` |
| `VPN_USER` | Usuario VPN | `jorge` |
| `VPN_PASS` | Contraseña VPN | `tu_password_vpn` |
| `VPN_CERT` | Certificado VPN (SHA256) | `abc123...` (obtener con openfortivpn) |

### Secrets de SSH:

| Secret | Descripción | Ejemplo |
|--------|-------------|---------|
| `SSH_HOST` | IP o hostname del servidor | `192.168.1.100` o `server.tuempresa.com` |
| `SSH_PORT` | Puerto SSH | `22` |
| `SSH_USER` | Usuario SSH | `deploy` |
| `SSH_PASS` | Contraseña SSH | `tu_password_ssh` |

---

## 🖥️ Paso 2: Configurar el Servidor

### 2.1 Ejecutar setup inicial (primera vez)

```bash
# Descargar el script de setup
curl -o setup-server.sh https://raw.githubusercontent.com/jorgevasquezutec/miboleta/main/scripts/setup-server.sh

# Dar permisos y ejecutar
chmod +x setup-server.sh
./setup-server.sh
```

Esto crea la estructura:
```
/opt/miboleta/
├── config/
│   ├── .env          # Variables Laravel (EDITAR)
│   ├── nginx.conf    # Config Nginx
│   └── my.cnf        # Config MySQL
├── ssl/
│   ├── fullchain.pem # Certificado SSL
│   └── privkey.pem   # Clave privada SSL
├── backups/
├── secrets/
├── .env.stack        # Variables Docker (EDITAR)
└── docker-stack.yml  # Se copia automáticamente
```

### 2.2 Configurar credenciales

**Editar `/opt/miboleta/.env.stack`:**
```env
IMAGE_TAG=latest

DB_ROOT_PASSWORD=TuPasswordRootMySQLSeguro
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=TuPasswordAppMySQLSeguro

REDIS_PASSWORD=TuPasswordRedisSeguro
```

**Editar `/opt/miboleta/config/.env`:**
```env
APP_NAME=MiBoleta
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com
APP_KEY=base64:TU_APP_KEY_GENERADA

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta
DB_PASSWORD=TuPasswordAppMySQLSeguro    # ← MISMO que en .env.stack

REDIS_HOST=redis
REDIS_PASSWORD=TuPasswordRedisSeguro    # ← MISMO que en .env.stack
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REVERB_APP_ID=miboleta
REVERB_APP_KEY=base64:TU_REVERB_KEY
REVERB_APP_SECRET=TU_REVERB_SECRET
```

### 2.3 Configurar SSL (si tienes dominio)

**Opción A: Let's Encrypt**
```bash
sudo certbot certonly --standalone -d tudominio.com

# Copiar certificados
sudo cp /etc/letsencrypt/live/tudominio.com/fullchain.pem /opt/miboleta/ssl/
sudo cp /etc/letsencrypt/live/tudominio.com/privkey.pem /opt/miboleta/ssl/
```

**Opción B: SSL de proveedor**
```bash
# Subir tus certificados a /opt/miboleta/ssl/
```

### 2.4 Login a GitHub Container Registry

```bash
# Crear Personal Access Token en GitHub con permisos: read:packages
docker login ghcr.io -u TU_USUARIO_GITHUB
# Ingresar el Personal Access Token como password
```

### 2.5 Inicializar Docker Swarm (si no está activo)

```bash
docker swarm init
```

---

## 📝 Paso 3: Configurar Variables de Vite para Producción

Edita `config/.env.vite.swarm` en tu repositorio local:

```env
# Variables de Vite para Producción
VITE_API_URL=https://tudominio.com/api
VITE_REVERB_APP_KEY="base64:TU_REVERB_KEY"
VITE_REVERB_HOST="tudominio.com"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
```

---

## 🔄 Paso 4: Subir Cambios y Hacer Deploy

### Primera opción: Merge a main (deploy automático)

```bash
# Commit tus cambios
git add .
git commit -m "feat: configuración de Docker Swarm y deploy"

# Push a development
git push origin development

# Crear PR y merge a main
# El deploy se ejecutará automáticamente al hacer merge
```

### Segunda opción: Ejecutar workflow manualmente

1. Ve a GitHub → **Actions** → **Build & Deploy**
2. Click en **Run workflow**
3. Selecciona la rama `main`

---

## 🔧 Paso 5: Modificar Workflow para rama development (opcional)

Si quieres que también haga deploy desde `development`, modifica `.github/workflows/docker-build-deploy.yml`:

```yaml
on:
  push:
    branches: [main, development]  # ← Agregar development
    tags: ['v*.*.*']
  workflow_dispatch:
```

---

## 📊 Flujo Completo del Deploy

```
┌─────────────────────────────────────────────────────────────────┐
│                     GITHUB ACTIONS                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Push a main/development                                     │
│         ↓                                                        │
│  2. Build imagen Docker                                         │
│     - Usa config/.env.vite.swarm para Vite                     │
│     - Push a ghcr.io/jorgevasquezutec/miboleta:latest          │
│         ↓                                                        │
│  3. Conectar VPN (si aplica)                                    │
│         ↓                                                        │
│  4. SSH al servidor                                              │
│     - Copiar docker-stack.yml                                   │
│     - docker pull ghcr.io/jorgevasquezutec/miboleta:latest     │
│     - docker stack deploy -c docker-stack.yml miboleta         │
│         ↓                                                        │
│  5. Post-deploy                                                  │
│     - Ejecutar migraciones                                      │
│     - Limpiar cachés                                            │
│         ↓                                                        │
│  6. Desconectar VPN                                             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✅ Verificar Deploy

### Desde el servidor:

```bash
# Ver estado de servicios
docker stack services miboleta

# Ver logs
docker service logs miboleta_app -f
docker service logs miboleta_nginx -f

# Verificar salud
curl https://tudominio.com/health
curl https://tudominio.com/api/health
```

### Desde GitHub:

1. Ve a **Actions**
2. Revisa el último workflow
3. Verifica que todos los steps estén ✅

---

## 🆘 Troubleshooting

### Error: "VPN connection failed"
- Verifica los secrets VPN
- Prueba la conexión VPN manualmente primero

### Error: "SSH connection refused"
- Verifica que el puerto SSH sea correcto
- Verifica que el servidor permita conexiones desde GitHub (o desde la VPN)

### Error: "unauthorized" al hacer docker pull
- Ejecuta `docker login ghcr.io` en el servidor
- Verifica que el Personal Access Token tenga permiso `read:packages`

### Error: "Access denied" en MySQL
- Verifica que DB_PASSWORD sea igual en `.env.stack` y `config/.env`
- Si cambiaste credenciales, elimina el volumen y recrea: `docker volume rm miboleta_swarm_mysql_data`

---

## 📝 Resumen de Archivos de Configuración

| Ubicación | Archivo | Propósito |
|-----------|---------|-----------|
| **Local (repo)** | `config/.env.vite.swarm` | Variables Vite para build |
| **Servidor** | `/opt/miboleta/.env.stack` | Variables Docker (MySQL, Redis) |
| **Servidor** | `/opt/miboleta/config/.env` | Variables Laravel |
| **Servidor** | `/opt/miboleta/config/nginx.conf` | Config Nginx |
| **Servidor** | `/opt/miboleta/ssl/` | Certificados SSL |
