#!/bin/sh
set -e

echo "🚀 MiBoleta - Container Starting..."

# Fix permissions for storage and cache (needed because volumes may override)
echo "🔐 Fixing permissions..."
# Make sure directories exist
mkdir -p /var/www/html/storage/app/documents
mkdir -p /var/www/html/storage/app/private
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Fix ownership (run as root in entrypoint)
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Fix permissions
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Ensure log file exists and is writable
touch /var/www/html/storage/logs/laravel.log
chown www-data:www-data /var/www/html/storage/logs/laravel.log
chmod 664 /var/www/html/storage/logs/laravel.log
echo "✅ Permissions fixed"

# Copiar archivos públicos al volumen compartido si está configurado
if [ "${COPY_PUBLIC_TO_VOLUME:-false}" = "true" ]; then
    echo "📁 Copying public files to shared volume..."
    if [ -d "/var/www/html/public-shared" ]; then
        cp -r /var/www/html/public/* /var/www/html/public-shared/ 2>/dev/null || true
        echo "✅ Public files copied to shared volume"
    fi
fi

# Esperar a que la base de datos esté lista
echo "⏳ Waiting for database..."
until php artisan db:show 2>/dev/null; do
    echo "   Database is not ready yet - sleeping"
    sleep 2
done
echo "✅ Database is ready!"

# Crear el storage link si no existe
echo "🔗 Creating storage link..."
php artisan storage:link || echo "   Storage link already exists"

# Verificar si es primera vez (tabla migrations no existe)
FIRST_TIME=false
if ! php artisan migrate:status 2>/dev/null | grep -q "Migration name"; then
    FIRST_TIME=true
    echo "🆕 First time setup detected!"
fi

# Ejecutar migraciones
if [ "$FIRST_TIME" = true ]; then
    echo "📦 Running migrations for the first time..."
    php artisan migrate --force

    # Solo hacer seed en primera instalación
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        echo "🌱 Running database seeders..."
        php artisan db:seed --class=DatabaseSeeder --force
    fi
else
    echo "🔄 Running migrations (if any)..."
    php artisan migrate --force
fi

# Limpiar y cachear configuraciones (solo en producción)
if [ "$APP_ENV" = "production" ]; then
    echo "🧹 Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "🔧 Development mode - skipping cache optimization"
fi

echo "✅ Container ready!"
echo ""

# Ejecutar el comando original (php-fpm, horizon, etc)
exec "$@"
