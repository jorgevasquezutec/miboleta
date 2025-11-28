# Multi-stage Dockerfile for Laravel Monolith with React frontend
# Stage 1: Build React frontend
FROM node:18-alpine AS frontend_builder

WORKDIR /app

# Copy package files
COPY package*.json ./
RUN npm ci

# Copy frontend source and build
COPY . .
RUN npm run build

# Stage 2: PHP-FPM with Laravel and built frontend
FROM php:8.4-fpm-alpine

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
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    gd \
    intl \
    mbstring

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

# Set proper permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/public

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
