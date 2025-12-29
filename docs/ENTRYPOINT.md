# 🚀 Docker Entrypoint - Inicialización Automática

El archivo `docker-entrypoint.sh` se ejecuta automáticamente cuando el contenedor inicia y se encarga de toda la inicialización de Laravel.

---

## ✅ ¿Qué hace el Entrypoint?

Cuando un contenedor inicia, el entrypoint ejecuta **automáticamente**:

### 1. Espera a que la Base de Datos esté lista
```bash
⏳ Waiting for database...
   Database is not ready yet - sleeping
✅ Database is ready!
```

### 2. Crea el Storage Link
```bash
🔗 Creating storage link...
php artisan storage:link
```

### 3. Detecta si es Primera Instalación
```bash
🆕 First time setup detected!
```

Verifica si la tabla `migrations` existe para saber si es la primera vez.

### 4. Ejecuta Migraciones

**Primera vez:**
```bash
📦 Running migrations for the first time...
php artisan migrate --force
```

**Deploys subsecuentes:**
```bash
🔄 Running migrations (if any)...
php artisan migrate --force
```

### 5. Ejecuta Seeders (Solo Primera Vez)

Si `RUN_SEEDERS=true` y es la primera instalación:
```bash
🌱 Running database seeders...
php artisan db:seed --class=DatabaseSeeder --force
```

⚠️ **Importante:** Los seeders SOLO se ejecutan en la primera instalación, nunca en deploys subsecuentes.

### 6. Optimización (Solo Producción)

Si `APP_ENV=production`:
```bash
🧹 Optimizing for production...
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Inicia el Servicio
```bash
✅ Container ready!
exec php-fpm  # O el comando que corresponda
```

---

## 🔧 Variables de Entorno

### `RUN_SEEDERS`

Controla si se ejecutan los seeders en la primera instalación.

```env
# En testing/desarrollo local
RUN_SEEDERS=true

# En producción
RUN_SEEDERS=false
```

**Cuándo usar `true`:**
- Pruebas locales con Docker Swarm
- Entornos de staging/testing
- Cuando quieres datos de ejemplo

**Cuándo usar `false`:**
- Producción
- Cuando ya tienes datos reales
- Cuando solo quieres la estructura de BD

### `APP_ENV`

Controla si se ejecuta la optimización de cachés.

```env
# Desarrollo - Sin cachés
APP_ENV=local

# Producción - Con cachés
APP_ENV=production
```

---

## 📊 Flujo de Decisión

```
Container Start
    ↓
🔍 ¿DB está lista?
    ↓ NO → ⏳ Esperar 2s → (repetir)
    ↓ YES
🔗 Storage Link
    ↓
🔍 ¿Es primera vez?
    ↓
    ├─ YES → 📦 Migrate → 🔍 ¿RUN_SEEDERS=true? → YES → 🌱 Seed
    │                                              → NO  → Skip Seed
    └─ NO  → 🔄 Migrate (solo nuevas)
    ↓
🔍 ¿APP_ENV=production?
    ↓
    ├─ YES → 🧹 Cache configs
    └─ NO  → Skip cache
    ↓
✅ Start Service (php-fpm, horizon, etc)
```

---

## 🎯 Escenarios de Uso

### Escenario 1: Primera Instalación Local

**Config:** `RUN_SEEDERS=true`, `APP_ENV=local`

```
🚀 Container Starting...
⏳ Waiting for database...
✅ Database is ready!
🔗 Creating storage link...
🆕 First time setup detected!
📦 Running migrations for the first time...
🌱 Running database seeders...
🔧 Development mode - skipping cache optimization
✅ Container ready!
```

**Resultado:**
- ✅ Estructura de BD creada
- ✅ Datos de ejemplo insertados
- ✅ Sin cachés (desarrollo)

---

### Escenario 2: Primera Instalación Producción

**Config:** `RUN_SEEDERS=false`, `APP_ENV=production`

```
🚀 Container Starting...
⏳ Waiting for database...
✅ Database is ready!
🔗 Creating storage link...
🆕 First time setup detected!
📦 Running migrations for the first time...
🧹 Optimizing for production...
✅ Container ready!
```

**Resultado:**
- ✅ Estructura de BD creada
- ❌ Sin datos de ejemplo
- ✅ Cachés optimizados

---

### Escenario 3: Deploy Subsecuente (Update)

**Config:** Cualquiera, `APP_ENV=production`

```
🚀 Container Starting...
⏳ Waiting for database...
✅ Database is ready!
🔗 Creating storage link...
🔄 Running migrations (if any)...
🧹 Optimizing for production...
✅ Container ready!
```

**Resultado:**
- ✅ Solo nuevas migraciones ejecutadas
- ❌ Sin seeders (nunca en updates)
- ✅ Cachés actualizados

---

## 🛠️ Ver Logs del Entrypoint

### Durante el deploy:
```bash
# Logs en tiempo real
docker service logs miboleta_app -f

# Solo logs del entrypoint (con emojis)
docker service logs miboleta_app | grep '🚀\|✅\|⏳\|📦\|🌱\|🧹'
```

### En un contenedor específico:
```bash
# Encontrar el contenedor
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)

# Ver logs del contenedor
docker logs $APP_CONTAINER
```

---

## 🐛 Troubleshooting

### Error: "Database is not ready yet"

El contenedor espera indefinidamente hasta que la BD esté lista. Esto es **normal** durante los primeros 5-15 segundos.

Si se queda esperando más de 1 minuto:
```bash
# Verificar que MySQL esté corriendo
docker service ps miboleta_db

# Ver logs de MySQL
docker service logs miboleta_db
```

### Error: "SQLSTATE[HY000] [2002] Connection refused"

La BD aún no está lista. El entrypoint **automáticamente reintenta** cada 2 segundos.

### Error: Seeders fallan

Los seeders solo se ejecutan si:
1. Es la primera instalación
2. `RUN_SEEDERS=true`

Si fallan:
```bash
# Ver el error específico
docker service logs miboleta_app

# Ejecutar seeders manualmente
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)
docker exec $APP_CONTAINER php artisan db:seed --class=DatabaseSeeder
```

### Migraciones no se ejecutan

El entrypoint **siempre** ejecuta migraciones. Si no ves las migraciones:
```bash
# Verificar logs
docker service logs miboleta_app

# Verificar estado de migraciones manualmente
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)
docker exec $APP_CONTAINER php artisan migrate:status
```

---

## 🔄 Resetear y Volver a Empezar

Para que el entrypoint detecte como "primera instalación":

```bash
# 1. Detener el stack
docker stack rm miboleta

# 2. Eliminar los volúmenes (CUIDADO: Borra todos los datos)
docker volume rm miboleta_swarm_mysql_data
docker volume rm miboleta_swarm_redis_data
docker volume rm miboleta_swarm_storage_data
docker volume rm miboleta_swarm_cache_data

# 3. Volver a hacer deploy
docker stack deploy -c docker-stack.yml miboleta
```

O usa el script de limpieza:
```bash
./cleanup-swarm-local.sh
# Responde "y" cuando pregunte si eliminar volúmenes
```

---

## 📝 Modificar el Entrypoint

Si necesitas personalizar el comportamiento:

1. Edita `docker-entrypoint.sh`
2. Rebuild la imagen:
   ```bash
   docker build -t miboleta:local .
   ```
3. Redeploy:
   ```bash
   docker stack deploy -c docker-stack.yml miboleta
   ```

---

## ✨ Ventajas de este Approach

1. **✅ Automático**: No necesitas ejecutar comandos manuales
2. **✅ Idempotente**: Puedes reiniciar el contenedor sin problemas
3. **✅ Inteligente**: Detecta primera instalación vs updates
4. **✅ Seguro**: Espera a la BD, no falla si algo no está listo
5. **✅ Flexible**: Control con variables de entorno
6. **✅ Production-ready**: Optimiza automáticamente para producción

---

## 🚀 Uso en GitHub Actions

El mismo entrypoint se usa automáticamente en el deploy de GitHub Actions:

```yaml
# .github/workflows/docker-build-deploy.yml
- name: Deploy via SSH
  run: |
    docker stack deploy -c docker-stack.yml miboleta
    # El entrypoint se ejecuta automáticamente
    # No necesitas comandos adicionales!
```

Todo funciona automáticamente:
- ✅ Primera instalación: Migra + (opcionalmente) Seed
- ✅ Updates: Solo migra cambios nuevos
- ✅ Rollbacks: Funciona sin problemas

---

## 📚 Referencias

- Archivo: [docker-entrypoint.sh](docker-entrypoint.sh)
- Dockerfile: [Dockerfile](Dockerfile)
- Config: [config/.env.example](config/.env.example)
