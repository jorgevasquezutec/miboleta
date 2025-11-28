#!/bin/bash

# Script para desarrollo: compila automáticamente, copia y recarga el navegador
# Accedes por http://localhost:3000 (con auto-reload)

echo "🔄 Iniciando watch mode con auto-reload..."
echo "📂 Compilando frontend y copiando a backend/public/"
echo "🌐 Accede a: http://localhost:3000 (con auto-reload)"
echo "    (o http://localhost si prefieres sin proxy)"
echo ""

# Función para copiar archivos
copy_files() {
    cp -r dist/* backend/public/ 2>/dev/null || true
}

# Compilar una vez al inicio
echo "⏳ Build inicial..."
npm run build > /dev/null 2>&1
copy_files
echo "✅ Build inicial completado"
echo ""

# Iniciar browser-sync que proxea a localhost:80 y recarga automáticamente
echo "🚀 Iniciando browser-sync..."
npx browser-sync start --proxy "localhost:80" --files "backend/public/**/*" --no-notify --no-open &
BROWSERSYNC_PID=$!

# Esperar un poco para que browser-sync inicie
sleep 3

# Iniciar Vite en modo watch en background
echo "👀 Modo watch activado - edita archivos en src/"
echo ""
vite build --watch 2>&1 | while read line; do
    if [[ "$line" == *"built in"* ]]; then
        copy_files
        echo "✅ Compilado y copiado - El navegador se recargará automáticamente"
    elif [[ "$line" == *"error"* ]]; then
        echo "❌ Error: $line"
    fi
done &
VITE_PID=$!

# Esperar a que terminen
wait

# Cleanup al salir
trap "kill $VITE_PID $BROWSERSYNC_PID 2>/dev/null" EXIT
