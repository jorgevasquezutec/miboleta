#!/bin/bash
# Helper para ejecutar Artisan sin tener PHP instalado localmente
# Usa el contenedor Docker de la app

# Uso: ./artisan.sh [comando artisan]
# Ejemplo: ./artisan.sh migrate
# Ejemplo: ./artisan.sh make:model Product

# Detectar si estamos en modo dev o producción
if docker compose -f docker-compose.dev.yml ps | grep -q "miboleta_app_dev"; then
    COMPOSE_FILE="docker-compose.dev.yml"
    echo "🐳 Usando modo desarrollo"
else
    COMPOSE_FILE="docker-compose.yml"
    echo "🐳 Usando modo producción"
fi

echo "🐳 Ejecutando: php artisan $@"
docker compose -f $COMPOSE_FILE exec app php artisan "$@"
