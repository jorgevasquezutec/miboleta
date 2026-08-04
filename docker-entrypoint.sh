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

# RUN_MIGRATIONS=false para los contenedores que NO deben migrar (horizon,
# reverb). Los tres comparten esta imagen y este entrypoint, así que al
# arrancar a la vez competían por las migraciones: uno ganaba y los otros dos
# morían con "Table 'migrations' already exists". En Swarm no se notaba porque
# la política de reinicio los volvía a levantar; en un compose plano se quedan
# caídos y la aplicación se queda sin colas ni websockets.
if [ "${RUN_MIGRATIONS:-true}" != "true" ]; then
    echo "⏭️  Migraciones omitidas en este contenedor (RUN_MIGRATIONS=false)"
    FIRST_TIME=false
else

# Esperar a que la base acepte conexiones ANTES de decidir nada.
# Sin esto, el `migrate:status` de abajo puede fallar por unos milisegundos de
# diferencia, el script concluye que la base está vacía e intenta crear las
# tablas otra vez: "SQLSTATE[42S01] Table 'migrations' already exists", el
# contenedor muere y se reinicia. El healthcheck del compose ayuda, pero no
# cubre el momento en que MySQL aún está aplicando permisos al usuario de la
# aplicación.
echo "⏳ Esperando a la base de datos..."
BD_LISTA=false
for i in $(seq 1 60); do
    if php -r '
        $h = getenv("DB_HOST") ?: "db";
        $p = getenv("DB_PORT") ?: "3306";
        $d = getenv("DB_DATABASE");
        $u = getenv("DB_USERNAME");
        $w = getenv("DB_PASSWORD");
        try { new PDO("mysql:host=$h;port=$p;dbname=$d", $u, $w); exit(0); }
        catch (Throwable $e) { exit(1); }
    ' 2>/dev/null; then
        BD_LISTA=true
        break
    fi
    sleep 2
done

if [ "$BD_LISTA" != true ]; then
    echo "❌ La base de datos no respondió tras 2 minutos."
    echo "   Revisa DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD y que el servicio esté arriba."
    exit 1
fi
echo "   Base de datos disponible."

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

    # Solo hacer seed en primera instalación.
    # SEEDER_CLASS es configurable para que el paquete de entrega pueda usar
    # DemoSeeder (datos de demostración en todos los módulos) en vez del
    # DatabaseSeeder base, que deja la plataforma sin documentos, sin
    # vacaciones y sin fechas de ingreso — o sea, sin nada que enseñar.
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        SEEDER_CLASS="${SEEDER_CLASS:-DatabaseSeeder}"
        echo "🌱 Running database seeders (${SEEDER_CLASS})..."
        php artisan db:seed --class="${SEEDER_CLASS}" --force
    fi
else
    echo "🔄 Running migrations (if any)..."
    php artisan migrate --force
fi

fi  # fin de RUN_MIGRATIONS

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

# Ejecutar el comando
# php-fpm necesita iniciar como root (él mismo cambia a www-data)
# horizon/reverb deben ejecutarse como www-data para que los archivos tengan el owner correcto
if echo "$@" | grep -qE "horizon|reverb"; then
    echo "🔄 Running as www-data: $@"
    exec su-exec www-data "$@"
else
    exec "$@"
fi
