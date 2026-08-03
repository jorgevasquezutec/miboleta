#!/bin/bash
# Script de inicialización para MiBoleta monolito
# NO requiere PHP ni Composer instalados localmente - todo en Docker

set -e  # Exit on error

echo "🚀 Inicializando MiBoleta - Monolito Laravel + React"
echo "📌 Todo se ejecuta en Docker (no necesitas PHP local)"
echo ""

# Check if backend directory exists
if [ -d "backend" ]; then
    echo "⚠️  La carpeta 'backend' ya existe."
    read -p "¿Deseas continuar? Esto podría sobrescribir archivos. (s/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        echo "❌ Instalación cancelada."
        exit 1
    fi
fi

# Step 1: Create Laravel app using Docker (no local PHP/Composer needed)
echo "📦 Paso 1: Creando aplicación Laravel (usando Docker)..."
if [ ! -d "backend" ]; then
    echo "   Descargando imagen Composer..."
    docker run --rm -v $(pwd):/app composer:2 create-project laravel/laravel backend --prefer-dist
    echo "✅ Laravel instalado en backend/"
else
    echo "⏭️  Saltando creación de Laravel (ya existe)"
fi

# Step 2: Configure .env
echo ""
echo "⚙️  Paso 2: Configurando .env de Laravel..."
cd backend

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ Archivo .env creado"
fi

# Update .env with Docker values
cat > .env << 'EOF'
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

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOF

echo "✅ .env configurado con credenciales Docker"

# Step 3: Create necessary directories
echo ""
echo "📁 Paso 3: Creando directorios necesarios..."
mkdir -p storage/logs
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/app/public
chmod -R 775 storage bootstrap/cache
echo "✅ Directorios creados con permisos correctos"

cd ..

# Step 4: Build frontend
echo ""
echo "🎨 Paso 4: Compilando frontend React..."
if [ -f "package.json" ]; then
    npm install
    npm run build
    echo "✅ Frontend compilado en dist/"
else
    echo "⚠️  No se encontró package.json. Asegúrate de tener el código del frontend."
fi

# Step 5: Docker Compose
echo ""
echo "🐳 Paso 5: Construyendo y levantando contenedores Docker..."
docker compose build
docker compose up -d

echo ""
echo "⏳ Esperando a que los servicios estén listos..."
sleep 10

# Step 6: Initialize Laravel in container
echo ""
echo "🔧 Paso 6: Inicializando Laravel dentro del contenedor..."
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force

echo ""
echo "✅ ¡Instalación completa!"
echo ""
echo "🌐 Accede a tu aplicación en: http://localhost:8080"
echo "🗄️  MySQL disponible en: localhost:3307"
echo ""
echo "Comandos útiles:"
echo "  - Ver logs: docker compose logs -f"
echo "  - Acceder al contenedor: docker compose exec app sh"
echo "  - Parar servicios: docker compose down"
echo ""
echo "📖 Consulta SETUP.md para más información."
