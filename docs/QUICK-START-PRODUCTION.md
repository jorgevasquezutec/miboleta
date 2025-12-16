# 🚀 Quick Start - Producción con Docker Hub

Esta guía te permite hacer deploys rápidos (30 segundos) usando imágenes pre-construidas.

## 📦 Flujo de trabajo optimizado

```
┌─────────────────────────────────────────────────────────┐
│  TU MÁQUINA LOCAL                                       │
│  1. Hacer cambios en código                            │
│  2. ./build-and-push.sh v1.0.1  ← Build + Push          │
│  3. (Opcional) git push                                 │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│  DOCKER HUB                                             │
│  Imagen: tu-usuario/miboleta:v1.0.1                     │
└─────────────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────────────┐
│  SERVIDOR PRODUCCIÓN (o local con ngrok)               │
│  1. ./deploy.sh deploy  ← Pull + Restart (30 seg)      │
│  2. ✅ Listo!                                           │
└─────────────────────────────────────────────────────────┘
```

## 🎯 Setup Inicial (Una sola vez)

### 1. Configurar Docker Hub

```bash
# Crear cuenta en hub.docker.com (gratis)
# Luego login
docker login
```

### 2. Configurar .env.production

```bash
# Editar y configurar
nano .env.production

# Cambiar:
DOCKER_USERNAME=tu-usuario-dockerhub  # ← Tu usuario de Docker Hub
IMAGE_TAG=latest

# Si usas ngrok, cuando lo inicies obtendrás una URL como:
# https://abc123.ngrok.io
# Actualiza:
APP_URL=https://abc123.ngrok.io
VITE_REVERB_HOST=abc123.ngrok.io
VITE_API_URL=https://abc123.ngrok.io/api
SESSION_SECURE_COOKIE=true  # ← Cambiar a true para HTTPS
```

### 3. Actualizar build-and-push.sh

```bash
# Editar el script
nano build-and-push.sh

# Cambiar línea 16:
DOCKER_USERNAME="${DOCKER_USERNAME:-tu-usuario-dockerhub}"
```

## 🔧 Uso Diario

### Primera vez (Build y Deploy)

```bash
# 1. En tu máquina - Build y push imagen
./build-and-push.sh v1.0.0

# 2. En servidor/local - Deploy inicial
./deploy.sh init
```

### Actualizar código (cada cambio)

```bash
# 1. Hacer tus cambios en el código
# Editar archivos...

# 2. Build y push nueva versión
./build-and-push.sh v1.0.1

# 3. Deploy (30 segundos)
./deploy.sh deploy

# O si hay cambios en BD:
./deploy.sh migrate
```

## 🌐 Usar con ngrok (Local → Público)

### 1. Instalar ngrok

```bash
# Descargar desde https://ngrok.com/download
# O con brew (Mac):
brew install ngrok

# Autenticarse (gratis):
ngrok config add-authtoken TU_TOKEN
```

### 2. Iniciar ngrok

```bash
# Exponer puerto 80 (donde corre nginx)
ngrok http 80
```

Verás algo así:
```
Forwarding  https://abc123.ngrok.io -> http://localhost:80
```

### 3. Actualizar .env.production con la URL de ngrok

```bash
nano .env.production

# Actualizar:
APP_URL=https://abc123.ngrok.io
VITE_REVERB_HOST=abc123.ngrok.io
VITE_REVERB_SCHEME=https
VITE_API_URL=https://abc123.ngrok.io/api
SANCTUM_STATEFUL_DOMAINS=abc123.ngrok.io
SESSION_SECURE_COOKIE=true
```

### 4. Rebuild y redeploy

```bash
# Build nueva imagen con configuración actualizada
./build-and-push.sh v1.0.2

# Deploy
./deploy.sh deploy
```

### 5. Acceder públicamente

Abre en cualquier navegador (desde cualquier lugar):
```
https://abc123.ngrok.io
```

## 📌 Versionado Semántico

Usa tags semánticos para tus versiones:

```bash
# Versión mayor (cambios incompatibles)
./build-and-push.sh v2.0.0

# Versión menor (nueva funcionalidad)
./build-and-push.sh v1.1.0

# Versión patch (bug fixes)
./build-and-push.sh v1.0.1

# También puedes usar:
./build-and-push.sh latest    # Última versión
./build-and-push.sh dev       # Desarrollo
./build-and-push.sh staging   # Staging
```

## 🔄 Workflow Completo de Ejemplo

```bash
# Día 1: Setup inicial
./build-and-push.sh v1.0.0
./deploy.sh init
# App corriendo en https://abc123.ngrok.io

# Día 2: Fix un bug
# ... editar código ...
./build-and-push.sh v1.0.1
./deploy.sh deploy
# ✅ Actualizado en 30 segundos

# Día 3: Nueva feature
# ... editar código ...
# ... crear migración ...
./build-and-push.sh v1.1.0
./deploy.sh migrate
# ✅ Deploy con migraciones

# Día 4: Rollback a versión anterior
nano .env.production  # Cambiar IMAGE_TAG=v1.0.1
./deploy.sh deploy
# ✅ Vuelto a versión anterior
```

## 🎨 Build Script - Opciones

```bash
# Build con versión específica
./build-and-push.sh v1.2.3

# Build sin push (solo local)
docker build -t miboleta:local .

# Ver imágenes locales
docker images | grep miboleta

# Ver imágenes en Docker Hub
https://hub.docker.com/r/tu-usuario/miboleta/tags
```

## 🐛 Troubleshooting

### ngrok se desconecta
```bash
# ngrok free cambia la URL cada vez
# Solución: Actualizar .env.production con nueva URL
# Y rebuild:
./build-and-push.sh v1.0.x
./deploy.sh deploy
```

### Error: imagen no encontrada
```bash
# Verificar que el DOCKER_USERNAME sea correcto
grep DOCKER_USERNAME .env.production

# Verificar que la imagen exista en Docker Hub
docker search tu-usuario/miboleta
```

### Deploy muy lento
```bash
# La primera vez descarga la imagen completa
# Los siguientes deploys son mucho más rápidos (solo cambios)
```

## 📊 Comandos Útiles

```bash
# Ver estado
./deploy.sh status

# Ver logs
./deploy.sh logs

# Reiniciar solo la app
./deploy.sh restart

# Detener todo
./deploy.sh stop

# Ver versión actual
docker compose -f docker-compose.prod.yml exec app php artisan --version
```

## 🎯 Ventajas de este flujo

- ⚡ **Deploy en 30 segundos** (vs 5+ minutos compilando)
- 🔄 **Rollback fácil** (cambiar IMAGE_TAG)
- 📦 **Versionado** con tags semánticos
- 🌐 **Testing público** con ngrok
- 🚀 **No necesitas Node.js** en el servidor
- 💾 **Historial** de versiones en Docker Hub

---

**¡Listo para producción! 🚀**
