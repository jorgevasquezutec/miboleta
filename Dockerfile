# Multi-stage Dockerfile for Laravel Monolith with React frontend
# Stage 1: Build React frontend
FROM node:18.20-alpine AS frontend_builder

# ARG para seleccionar archivo .env de Vite
# - Desarrollo (docker-compose): usa .env.local por defecto
# - Swarm/Producción: pasar --build-arg VITE_ENV_FILE=config/.env.vite.swarm
ARG VITE_ENV_FILE=.env.local

WORKDIR /app

# Copy package files
COPY package*.json ./
RUN npm ci

# Copy frontend source
COPY . .

# Copy the specified .env file for Vite (if it exists)
# This overwrites any .env.local or .env.production that might exist
COPY ${VITE_ENV_FILE} .env.production

RUN npm run build

# Stage 2: PHP-FPM with Laravel and built frontend
FROM php:8.4.2-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    gd \
    intl \
    mbstring \
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis


# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy Laravel application files
# (This assumes you'll have a 'backend' directory with Laravel)
# Adjust if Laravel is in root
COPY backend/ ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy built frontend from stage 1 to Laravel's public directory
COPY --from=frontend_builder /app/dist ./public

# Set proper permissions for all application files
# Ensure all PHP files are readable (some may have restrictive perms from local)
RUN chmod -R 644 /var/www/html/app /var/www/html/config /var/www/html/routes /var/www/html/database 2>/dev/null || true && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/public

# Copy and configure entrypoint
COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose PHP-FPM port
EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
