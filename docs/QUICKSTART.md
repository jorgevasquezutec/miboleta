# 🚀 INICIO RÁPIDO - MiBoleta

## ⚠️ Prerequisitos

**Solo necesitas:**
- ✅ Docker y Docker Compose
- ✅ Node.js (para el frontend)

**NO necesitas:**
- ❌ PHP instalado
- ❌ Composer instalado
- ❌ MySQL instalado

Todo (Laravel, Composer, MySQL) corre en Docker.

---

## TL;DR - Desarrollo con Hot Reload (Recomendado)

```bash
# 1. Levantar backend (usa Docker para crear Laravel si no existe)
npm run docker:dev:up

# 2. En otra terminal: levantar frontend con HMR
npm run dev

# 3. Abrir
open http://localhost:5173
```

**Hot reload activado** → Edita código y ve cambios instantáneos ⚡

👉 **[Leer guía completa de desarrollo](DEVELOPMENT.md)**

---

## TL;DR - Setup Completo (Producción)

```bash
# 1. Ejecutar setup automático (usa Docker, no necesitas PHP local)
./setup.sh

# 2. Acceder a la app
open http://localhost:8080
```

¡Eso es todo! 🎉

---

## O hazlo manualmente en 3 pasos

### Paso 1: Instalar Laravel (con Docker, sin PHP local)

```bash
# Crea Laravel usando Docker (no necesitas composer local)
docker run --rm -v $(pwd):/app composer:2 create-project laravel/laravel backend

cd backend
cp .env.example .env
```

Edita `backend/.env`:
```env
DB_HOST=mysql
DB_DATABASE=miboleta
DB_USERNAME=miboleta_user
DB_PASSWORD=secret123
```

### Paso 2: Build y Docker

```bash
cd ..
npm install
npm run build
npm run docker:up
```

### Paso 3: Migrar DB

```bash
npm run laravel:migrate
```

Listo → http://localhost:8080

---

## Comandos útiles del día a día

### Desarrollo (Hot Reload)

```bash
# Levantar stack de desarrollo
npm run docker:dev:up      # Backend + MySQL
npm run dev                # Frontend con HMR

# Ver logs
npm run docker:dev:logs

# Acceder al shell de Laravel
npm run laravel:dev:shell

# Migrar DB
npm run laravel:dev:migrate
```

### Producción

```bash
# Ver logs en tiempo real
npm run docker:logs

# Reiniciar después de cambios en .env
npm run docker:restart

# Acceder al contenedor de Laravel
npm run laravel:shell

# Ejecutar comandos Artisan
docker compose exec app php artisan [comando]

# Recompilar frontend y actualizar
npm run build
docker compose restart nginx
```

---

## Estructura de carpetas (después del setup)

```
miboleta/
├── backend/              ← Laravel (creado por setup.sh)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/          ← Frontend compilado se copia aquí
│   └── routes/
├── src/                 ← Código fuente React
├── docker/
│   └── nginx/
│       └── default.conf
├── Dockerfile
├── docker-compose.yml
├── setup.sh             ← Script de instalación
└── SETUP.md             ← Documentación completa
```

---

## Usuarios de prueba

Por defecto puedes loguearte con:

**Platform Admin** (ve todas las empresas):
- Email: `platform@miboleta.com`
- Password: (cualquier texto)

**Tenant Admin** (solo su empresa):
- Email: `admin@empresa.com`
- Password: (cualquier texto)

**Empleado**:
- Email: `empleado@empresa.com`
- Password: (cualquier texto)

> Nota: El login es mock (Context API). Para usar Laravel auth, implementa rutas en `backend/routes/api.php`.

---

## Solución rápida de problemas

### "Connection refused" en MySQL
```bash
# Espera a que MySQL termine de inicializar (primero uso)
docker compose logs mysql
# Cuando veas "ready for connections", ejecuta:
npm run laravel:migrate
```

### Frontend no se ve actualizado
```bash
npm run build
docker compose restart nginx
```

### Permisos de storage en Laravel
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Limpiar todo y empezar de cero
```bash
docker compose down -v   # ⚠️ Borra la DB
rm -rf backend/
./setup.sh               # Reinstalar desde cero
```

---

## Next Steps

1. ✅ Completaste setup básico
2. 📖 Lee [SETUP.md](SETUP.md) para entender la arquitectura completa
3. 🔧 Configura tus primeras rutas API en `backend/routes/api.php`
4. 🎨 Convierte el frontend a React Router (opcional, ya tienes `react-router-dom` instalado)
5. 🚀 Deploy a producción (configura SSL, .env de producción, etc.)

---

## 📋 Scripts Helper (sin PHP/Composer local)

Ejecuta Composer y Artisan SIN tener PHP instalado:

```bash
# Composer (instalar paquetes, etc.)
./composer.sh require laravel/sanctum
./composer.sh install
./composer.sh update

# Artisan (migraciones, modelos, etc.)
./artisan.sh migrate
./artisan.sh make:model Product
./artisan.sh make:controller ApiController
./artisan.sh db:seed

# Ejemplo completo: instalar Sanctum para API
./composer.sh require laravel/sanctum
./artisan.sh vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
./artisan.sh migrate
```

---

**¿Problemas?** Abre un issue o contacta al equipo.
