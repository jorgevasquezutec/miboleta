# 📝 Guía de archivos .env

## 🎯 Archivos de configuración y su propósito

```
proyecto/
├── .env.production          ← Backend Laravel (MySQL, Redis, Mail, etc.)
├── .env.production.local    ← Frontend Vite (VITE_* variables)
├── .env.local               ← Desarrollo local (no se usa en build)
└── backend/.env             ← Desarrollo (Docker Compose local)
```

## 🔍 Cómo funciona durante el BUILD

### 1. Build de la imagen Docker

```bash
./build-and-push.sh v1.0.0
```

**Lo que sucede internamente:**

```dockerfile
# Stage 1: Build React frontend
FROM node:18-alpine AS frontend_builder
COPY . .                    # ← Copia .env.production.local
RUN npm run build           # ← Vite lee .env.production.local
                           # ← Variables VITE_* se EMBEBEN en el código JS

# Stage 2: PHP Laravel
FROM php:8.4-fpm-alpine
COPY backend/ ./            # ← NO copia .env (usarás .env.production en runtime)
COPY --from=frontend_builder /app/dist ./public  # ← Frontend compilado
```

**Variables Vite que se embeben:**
- `VITE_API_URL` → Se convierte en código JavaScript hardcoded
- `VITE_REVERB_HOST` → Se convierte en código JavaScript hardcoded
- Etc.

### 2. Runtime en producción

```bash
./deploy.sh deploy
```

**Lo que sucede:**

```yaml
# docker-compose.prod.yml
services:
  app:
    image: tu-usuario/miboleta:v1.0.0  # ← Imagen pre-built
    env_file:
      - .env.production  # ← Se carga en RUNTIME para Laravel
```

Laravel lee `.env.production` en runtime para:
- Conectarse a MySQL (`DB_HOST`, `DB_PASSWORD`)
- Conectarse a Redis (`REDIS_HOST`, `REDIS_PASSWORD`)
- Configurar sesiones, mail, etc.

## 📋 Prioridad de archivos .env (para Vite)

Durante `npm run build`, Vite busca archivos en este orden:

1. `.env.production.local` ⭐ **MAYOR PRIORIDAD**
2. `.env.production`
3. `.env.local`
4. `.env`

## ✅ Cuándo modificar cada archivo

### `.env.production` (Backend Laravel)

Modificar cuando cambies:
- Passwords de base de datos
- Configuración de Redis
- Configuración de email
- APP_URL (URL pública de la app)
- SANCTUM_STATEFUL_DOMAINS

**NO incluye variables VITE_***

```env
# .env.production
APP_URL=http://localhost
DB_PASSWORD=tu_password
REDIS_PASSWORD=tu_password
SANCTUM_STATEFUL_DOMAINS=localhost
```

### `.env.production.local` (Frontend Vite)

Modificar cuando cambies:
- URL del API
- Configuración de WebSockets
- Cualquier variable que use el frontend

**Solo variables VITE_***

```env
# .env.production.local
VITE_API_URL=http://localhost/api
VITE_REVERB_HOST="localhost"
VITE_REVERB_SCHEME="http"
```

## 🌐 Ejemplo con ngrok

### 1. Iniciar ngrok

```bash
ngrok http 80
# Obtienes: https://abc123.ngrok.io
```

### 2. Actualizar configuración

```bash
./update-ngrok-url.sh https://abc123.ngrok.io
```

Esto actualiza **automáticamente**:

`.env.production`:
```env
APP_URL=https://abc123.ngrok.io
SANCTUM_STATEFUL_DOMAINS=abc123.ngrok.io
SESSION_SECURE_COOKIE=true
```

`.env.production.local`:
```env
VITE_API_URL=https://abc123.ngrok.io/api
VITE_REVERB_HOST="abc123.ngrok.io"
VITE_REVERB_SCHEME="https"
```

### 3. Rebuild (necesario porque variables VITE_* se embeben)

```bash
./build-and-push.sh v1.0.1  # ← Nuevo build con URLs actualizadas
./deploy.sh deploy           # ← Deploy
```

## ⚠️ IMPORTANTE: Cuándo hacer rebuild

### ✅ Necesitas REBUILD cuando cambias:

- Variables `VITE_*` (frontend)
- Código del frontend (React, TypeScript, etc.)
- Código del backend (PHP, Laravel)

**Razón:** Estas se embeben en la imagen Docker

```bash
./build-and-push.sh v1.0.x  # ← Siempre incrementa versión
./deploy.sh deploy
```

### ❌ NO necesitas rebuild cuando cambias:

- Solo variables de backend Laravel (no VITE_*)
- Passwords de base de datos
- Configuración de email

**Razón:** Laravel lee `.env.production` en runtime

```bash
# Solo editar .env.production en el servidor
nano .env.production
./deploy.sh restart  # ← Solo restart
```

## 🎨 Workflow completo de ejemplo

### Caso 1: Cambio de código

```bash
# Editas un archivo .tsx o .php
./build-and-push.sh v1.0.1  # ← Rebuild
./deploy.sh deploy           # ← Deploy
```

### Caso 2: Cambio de password DB

```bash
# Editas .env.production (en servidor)
nano .env.production
# Cambias DB_PASSWORD

./deploy.sh restart  # ← NO rebuild necesario
```

### Caso 3: Cambio de URL (ngrok)

```bash
# ngrok te da nueva URL
./update-ngrok-url.sh https://xyz789.ngrok.io

./build-and-push.sh v1.0.2  # ← Rebuild (VITE_* cambiaron)
./deploy.sh deploy
```

## 📊 Resumen visual

```
┌──────────────────────────────────────────────────────┐
│  DESARROLLO LOCAL                                    │
├──────────────────────────────────────────────────────┤
│  .env.local         → Vite (frontend development)    │
│  backend/.env       → Laravel (backend development)  │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  BUILD TIME (./build-and-push.sh)                    │
├──────────────────────────────────────────────────────┤
│  .env.production.local → EMBEBIDO en JS compilado    │
│  .env.production      → NO usado en build            │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  RUNTIME (./deploy.sh)                               │
├──────────────────────────────────────────────────────┤
│  .env.production      → Laravel lee en runtime       │
│  .env.production.local → Ya está en el JS compilado  │
└──────────────────────────────────────────────────────┘
```

## 💡 Tips

1. **Git**: `.env.production` está en `.gitignore` ✅
2. **Backup**: Guarda `.env.production` en lugar seguro
3. **Passwords**: Usa passwords fuertes diferentes para cada ambiente
4. **ngrok**: Recuerda rebuild cuando cambie la URL
5. **Versionado**: Incrementa versión en cada rebuild

---

**¡Ahora entiendes completamente cómo funcionan los archivos .env! 🚀**
