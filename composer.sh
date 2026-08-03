#!/bin/bash
# Helper para ejecutar Composer sin tenerlo instalado localmente
# Usa Docker para ejecutar cualquier comando de Composer

# Uso: ./composer.sh [comando composer]
# Ejemplo: ./composer.sh require laravel/sanctum

if [ ! -d "backend" ]; then
    echo "❌ Error: La carpeta 'backend' no existe."
    echo "   Ejecuta primero: ./setup.sh"
    exit 1
fi

echo "🐳 Ejecutando: composer $@"
docker run --rm -v $(pwd)/backend:/app composer:2 "$@"
