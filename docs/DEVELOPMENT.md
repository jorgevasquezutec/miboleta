# 🔥 Desarrollo con Hot Reload (HMR)

## Setup Rápido - Desarrollo Local

Este setup te permite desarrollar con **hot reload instantáneo** en el frontend mientras Laravel corre en Docker.

### Arquitectura de Desarrollo

```
Frontend (Vite HMR) → localhost:5173 → Proxy /api → Nginx:8080 → Laravel → MySQL
     ↑                                                                        ↑
   Tu código                                                            Docker
```

---

## 🚀 Inicio Rápido

### 1. Levantar Backend (Docker)

```bash
# Primera vez: crear Laravel si no existe
composer create-project laravel/laravel backend

# Levantar solo backend + MySQL
npm run docker:dev:up

# Ejecutar migraciones
npm run laravel:dev:migrate
```

### 2. Levantar Frontend (Vite con HMR)

```bash
# En otra terminal
npm run dev
```

Se abrirá automáticamente `http://localhost:5173` con **hot reload** activado.

### 3. ¡Listo! Desarrolla con hot reload

- ✅ Edita archivos en `src/` → **Cambios instantáneos** sin recargar
- ✅ CSS y componentes se actualizan en < 50ms
- ✅ Llamadas a `/api` se proxean automáticamente a Laravel en `:8080`
- ✅ Laravel lee archivos desde `./backend` montado como volumen

---

## 📦 Comandos de Desarrollo

### Frontend

```bash
npm run dev                # Vite dev server con HMR en :5173
npm run build              # Build producción
```

### Backend (Docker Dev)

```bash
npm run docker:dev:up      # Levantar Laravel + MySQL
npm run docker:dev:down    # Parar contenedores
npm run docker:dev:logs    # Ver logs
npm run laravel:dev:migrate    # Migrar DB
npm run laravel:dev:shell      # Shell en contenedor
```

### Fullstack (un solo comando)

```bash
npm run dev:fullstack      # Levanta backend Y frontend
```

---

## Cómo Funciona

### Vite Proxy

En `vite.config.ts`:

```ts
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8080',  // Laravel en Docker
      changeOrigin: true,
    },
  },
}
```

**Resultado:**
- Frontend en `http://localhost:5173/`
- Peticiones a `/api/users` → proxeadas a `http://localhost:8080/api/users` (Laravel)

### Volumes (Hot Reload Backend)

En `docker-compose.dev.yml`:

```yaml
volumes:
  - ./backend:/var/www/html  # Código montado = sin rebuild
```

**Resultado:**
- Editas PHP en `./backend` → cambios **inmediatos** (sin reconstruir imagen)
- Para cambios de composer: `docker compose -f docker-compose.dev.yml restart app`

---

## Flujo de Trabajo Típico

### Día a día

```bash
# Terminal 1: Backend
npm run docker:dev:up
npm run docker:dev:logs    # (opcional) para ver logs

# Terminal 2: Frontend
npm run dev

# Desarrolla normalmente
# Guarda archivos → hot reload automático
```

### Cambios en dependencias

**Frontend (package.json):**
```bash
npm install [paquete]
# Vite detecta cambios automáticamente
```

**Backend (composer.json):**
```bash
docker compose -f docker-compose.dev.yml exec app composer require [paquete]
docker compose -f docker-compose.dev.yml restart app
```

### Migraciones y DB

```bash
# Crear migración
docker compose -f docker-compose.dev.yml exec app php artisan make:migration create_table

# Ejecutar migraciones
npm run laravel:dev:migrate

# Reset DB completo
docker compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed
```

---

## Producción vs Desarrollo

| Aspecto | Desarrollo | Producción |
|---------|-----------|------------|
| Frontend | Vite dev server (:5173) | Build compilado en `backend/public/` |
| Backend | Volumen montado | Código copiado en imagen |
| HMR | ✅ Sí | ❌ No |
| Rebuild necesario | ❌ No | ✅ Sí (al cambiar código) |
| Docker Compose | `docker-compose.dev.yml` | `docker-compose.yml` |
| Nginx | Solo sirve API | Sirve frontend + API |

### Comandos Producción

```bash
# Build completo (frontend + backend en imagen)
docker compose build
docker compose up -d

# Acceder
open http://localhost:8080
```

---

## Troubleshooting

### Puerto 5173 ocupado

```bash
# Cambiar puerto en vite.config.ts
server: { port: 5174 }
```

### API requests fallan (CORS)

Agrega en `backend/config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:5173'],
```

Y en `backend/app/Http/Kernel.php`:

```php
protected $middleware = [
    // ...
    \App\Http\Middleware\HandleCors::class,
];
```

### Hot reload no funciona

```bash
# Reiniciar Vite
# Ctrl+C y volver a:
npm run dev
```

### Cambios en Laravel no se ven

```bash
# Limpiar cache
docker compose -f docker-compose.dev.yml exec app php artisan cache:clear
docker compose -f docker-compose.dev.yml exec app php artisan config:clear

# O reiniciar contenedor
docker compose -f docker-compose.dev.yml restart app
```

### MySQL no responde

```bash
# Ver logs
docker compose -f docker-compose.dev.yml logs mysql

# Recrear volumen (⚠️ borra datos)
docker compose -f docker-compose.dev.yml down -v
docker compose -f docker-compose.dev.yml up -d
```

---

## Ventajas de este Setup

✅ **Hot Module Replacement**: Cambios instantáneos en < 50ms  
✅ **No rebuilds**: Edita código y ve cambios al instante  
✅ **Proxy automático**: `/api` se enruta a Laravel sin configurar CORS manualmente  
✅ **Docker ligero**: Solo backend en Docker, frontend nativo (más rápido)  
✅ **Desarrollo profesional**: Igual que equipos en producción (Netflix, Airbnb, etc.)  

---

## Comparación con Laravel Mix

| Feature | Laravel Mix | Vite (este setup) |
|---------|-------------|-------------------|
| HMR | ⚠️ Lento (webpack) | ✅ Instantáneo (esbuild) |
| Build speed | 🐌 30-60s | ⚡ 2-5s |
| Dev server | webpack-dev-server | Vite dev server |
| Status | Deprecado | Activo y mantenido |
| React support | Plugin | Nativo |

**Laravel Mix está deprecado desde Laravel 9.** Vite es el estándar oficial.

---

## Next Steps

1. ✅ Ya tienes HMR configurado
2. 🔧 Crea tus primeras rutas API en `backend/routes/api.php`
3. 🎨 (Opcional) Convierte el frontend a React Router
4. 🚀 Deploy a producción con `docker compose up --build`

---

**¿Dudas?** Lee el [README principal](../README.md)
