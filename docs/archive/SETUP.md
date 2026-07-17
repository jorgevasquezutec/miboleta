# MiBoleta - Setup Guide (Monolito Laravel + React)

Este proyecto es un monolito donde Laravel sirve el frontend React compilado.

## Arquitectura
- **Frontend**: React + TypeScript (compilado a `/backend/public`)
- **Backend**: Laravel API
- **Base de datos**: MySQL 8.0
- **Servidor web**: Nginx + PHP-FPM
- **Orquestación**: Docker Compose

## Prerequisitos
- Docker y Docker Compose instalados
- Node.js 18+ (para desarrollo local del frontend)

**NO necesitas:**
- ❌ PHP instalado localmente
- ❌ Composer instalado localmente  
- ❌ MySQL instalado localmente

Todo (Laravel, Composer, MySQL) corre en Docker.

## Pasos de instalación

### 1. Crear la aplicación Laravel

Si aún no tienes la carpeta `backend/` con Laravel, usa Docker (no necesitas Composer local):

```bash
# Desde la raíz del proyecto - usando Docker
docker run --rm -v $(pwd):/app composer:2 create-project laravel/laravel backend

# O si prefieres una versión específica
docker run --rm -v $(pwd):/app composer:2 create-project laravel/laravel:^11.0 backend
```

### 2. Configurar el entorno Laravel

```bash
cd backend
cp .env.example .env
```

Edita `backend/.env` con estas credenciales (coinciden con docker-compose.yml):

```env
APP_NAME=MiBoleta
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=miboleta
DB_USERNAME=miboleta_user
DB_PASSWORD=secret123
```

### 3. Preparar directorios de Laravel

```bash
# Crear directorios necesarios con permisos
cd backend
mkdir -p storage/logs
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage bootstrap/cache
```

### 4. Construir y levantar los contenedores

Desde la raíz del proyecto:

```bash
# Construir las imágenes
docker compose build

# Levantar los servicios
docker compose up -d

# Ver logs (opcional)
docker compose logs -f
```

### 5. Inicializar Laravel dentro del contenedor

```bash
# Generar application key
docker compose exec app php artisan key:generate

# Ejecutar migraciones
docker compose exec app php artisan migrate

# (Opcional) Ejecutar seeders
docker compose exec app php artisan db:seed
```

### 6. Acceder a la aplicación

Abre tu navegador en:
- **Web**: http://localhost:8080
- **MySQL**: localhost:3307 (puerto mapeado para acceso externo)

## Comandos útiles

### Desarrollo

```bash
# Ver logs en tiempo real
docker compose logs -f app

# Acceder al contenedor de la app
docker compose exec app sh

# Ejecutar comandos de Artisan
docker compose exec app php artisan [comando]

# Ejecutar Composer
docker compose exec app composer [comando]

# Reconstruir frontend y copiar a public
npm run build
docker compose exec app cp -r /path/to/dist/* /var/www/html/public/
```

### Base de datos

```bash
# Conectarse a MySQL
docker compose exec mysql mysql -u miboleta_user -psecret123 miboleta

# Backup de base de datos
docker compose exec mysql mysqldump -u miboleta_user -psecret123 miboleta > backup.sql

# Restaurar backup
docker compose exec -T mysql mysql -u miboleta_user -psecret123 miboleta < backup.sql
```

### Reiniciar servicios

```bash
# Reiniciar un servicio específico
docker compose restart app

# Parar todos los servicios
docker compose down

# Parar y eliminar volúmenes (⚠️ borra la base de datos)
docker compose down -v
```

## Estructura del proyecto

```
miboleta/
├── backend/              # Laravel application
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/          # Frontend compilado se copia aquí
│   ├── routes/
│   └── ...
├── src/                 # Frontend React source
├── docker/
│   └── nginx/
│       └── default.conf # Configuración Nginx
├── Dockerfile           # Multi-stage: build React + PHP-FPM
├── docker-compose.yml   # Orquestación de servicios
└── SETUP.md            # Este archivo
```

## Desarrollo local (sin Docker)

Si prefieres desarrollar sin Docker:

### Frontend
```bash
npm install
npm run dev  # Vite dev server en localhost:5173
```

### Backend
```bash
cd backend
composer install
php artisan serve  # Laravel en localhost:8000
```

Necesitarás MySQL corriendo localmente y ajustar `.env` con `DB_HOST=127.0.0.1`.

## Integración del Frontend

El `Dockerfile` compila el frontend (React) en la Stage 1 y copia el resultado a `backend/public/` en la Stage 2. 

**Para rebuilds del frontend:**
1. Haz cambios en `src/`
2. Ejecuta `npm run build` localmente
3. Copia `dist/` a `backend/public/`
4. O reconstruye la imagen completa: `docker compose up --build`

## Solución de problemas

### Permisos de storage
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Limpiar cache de Laravel
```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

### Recrear base de datos
```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Frontend no se actualiza
- Asegúrate de que `npm run build` genera archivos en `dist/`
- Verifica que el Dockerfile copia de `dist/` a `public/`
- Reconstruye: `docker compose up --build`

## Producción

Para producción considera:
- Usar `.env` con `APP_ENV=production` y `APP_DEBUG=false`
- Configurar `APP_URL` con tu dominio real
- Usar volúmenes persistentes o storage en cloud para `storage/`
- Configurar SSL/TLS en Nginx
- Usar secrets para credenciales sensibles
- Implementar cache de configuración: `php artisan config:cache`

## Licencia

[Tu licencia aquí]
