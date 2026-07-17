# 🧪 Pruebas Locales con Docker Swarm

Guía para probar el stack completo de Docker Swarm en tu máquina local antes del deploy a producción.

---

## 🚀 Quick Start

### 1. Ejecutar el script de prueba

```bash
./test-swarm-local.sh
```

Este script automáticamente:
- ✅ Verifica que Docker esté corriendo
- ✅ Inicializa Docker Swarm
- ✅ Genera el APP_KEY de Laravel
- ✅ Construye la imagen Docker
- ✅ Despliega el stack completo
- ✅ Ejecuta migraciones
- ✅ Configura cachés

### 2. Acceder a la aplicación

Una vez completado el script:

- **App:** http://localhost
- **API:** http://localhost/api
- **Horizon:** http://localhost/horizon
- **Health Check:** http://localhost/health

---

## 📋 Requisitos Previos

- Docker Desktop instalado y corriendo
- Al menos 4GB de RAM disponible
- Puertos 80 y 443 libres

---

## 🔧 Configuración Manual (Opcional)

Si prefieres hacerlo paso a paso en lugar del script automático:

### 1. Inicializar Swarm

```bash
docker swarm init
```

### 2. Crear el archivo .env

```bash
cp config/.env.local config/.env
```

Edita `config/.env` y genera un APP_KEY:

```bash
# Generar APP_KEY
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Copiar el resultado al .env en la línea APP_KEY
```

### 3. Crear archivo .env.stack

Crea el archivo `.env.stack` con:

```env
IMAGE_TAG=local
DB_ROOT_PASSWORD=root_password_local_123
DB_DATABASE=miboleta_local
DB_USERNAME=miboleta
DB_PASSWORD=miboleta_local_pass_123
REDIS_PASSWORD=redis_local_pass_456
```

### 4. Build de la imagen

```bash
docker build -t miboleta:local .
```

### 5. Deploy del stack

```bash
# Cargar variables
export $(cat .env.stack | grep -v '^#' | xargs)
export IMAGE_TAG=local

# Deploy
docker stack deploy -c docker-stack.yml miboleta
```

### 6. Verificar servicios

```bash
docker stack services miboleta
```

### 7. Ejecutar migraciones

```bash
# Esperar a que el contenedor esté listo
sleep 30

# Encontrar el contenedor
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)

# Ejecutar migraciones
docker exec $APP_CONTAINER php artisan migrate --force
```

---

## 📊 Monitoreo y Logs

### Ver estado de servicios

```bash
docker stack services miboleta
```

### Ver logs en tiempo real

```bash
# App
docker service logs miboleta_app -f

# Nginx
docker service logs miboleta_nginx -f

# Base de datos
docker service logs miboleta_db -f

# Redis
docker service logs miboleta_redis -f

# Horizon (colas)
docker service logs miboleta_horizon -f

# Reverb (websockets)
docker service logs miboleta_reverb -f
```

### Ver procesos del stack

```bash
docker stack ps miboleta
```

### Ver todos los contenedores

```bash
docker ps | grep miboleta
```

---

## 🛠️ Comandos Útiles

### Ejecutar comandos en el contenedor

```bash
# Encontrar el contenedor app
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)

# Artisan commands
docker exec $APP_CONTAINER php artisan migrate
docker exec $APP_CONTAINER php artisan db:seed
docker exec $APP_CONTAINER php artisan cache:clear
docker exec $APP_CONTAINER php artisan config:clear
docker exec $APP_CONTAINER php artisan route:list

# Entrar al shell
docker exec -it $APP_CONTAINER sh
```

### Escalar servicios

```bash
# Aumentar réplicas del app
docker service scale miboleta_app=3

# Ver el escalado
docker service ps miboleta_app
```

### Actualizar un servicio

```bash
# Actualizar la imagen
docker service update --image miboleta:local miboleta_app

# Forzar recreación
docker service update --force miboleta_app
```

### Reiniciar un servicio

```bash
docker service update --force miboleta_app
```

---

## 🗄️ Base de Datos

### Conectarse a MySQL

```bash
# Desde fuera del contenedor
docker exec -it $(docker ps -qf "name=miboleta_db" | head -1) \
  mysql -u root -proot_password_local_123

# O con el usuario normal
docker exec -it $(docker ps -qf "name=miboleta_db" | head -1) \
  mysql -u miboleta -pmiboleta_local_pass_123 miboleta_local
```

### Backup de la base de datos

```bash
docker exec $(docker ps -qf "name=miboleta_db" | head -1) \
  mysqldump -u root -proot_password_local_123 miboleta_local > backup.sql
```

### Restore

```bash
docker exec -i $(docker ps -qf "name=miboleta_db" | head -1) \
  mysql -u root -proot_password_local_123 miboleta_local < backup.sql
```

---

## 🧹 Limpieza

### Remover el stack (mantener datos)

```bash
docker stack rm miboleta
```

### Limpieza completa (con script)

```bash
./cleanup-swarm-local.sh
```

El script te preguntará si deseas:
- Eliminar volúmenes (datos)
- Salir de Swarm mode

### Limpieza manual completa

```bash
# 1. Remover stack
docker stack rm miboleta

# 2. Esperar a que se detengan los contenedores
sleep 10

# 3. Eliminar volúmenes
docker volume rm miboleta_mysql_data
docker volume rm miboleta_redis_data
docker volume rm miboleta_storage_data
docker volume rm miboleta_cache_data
docker volume rm miboleta_nginx_logs
docker volume rm miboleta_mysql_backups

# 4. Salir de Swarm (opcional)
docker swarm leave --force
```

---

## 🐛 Troubleshooting

### Los servicios no inician

```bash
# Ver logs del servicio que falla
docker service logs miboleta_app

# Ver eventos del servicio
docker service ps miboleta_app --no-trunc

# Verificar el stack
docker stack ps miboleta
```

### Error de conexión a la base de datos

```bash
# Verificar que MySQL está corriendo
docker service ps miboleta_db

# Ver logs de MySQL
docker service logs miboleta_db

# Verificar credenciales en config/.env
cat config/.env | grep DB_
```

### Error de permisos en storage

```bash
APP_CONTAINER=$(docker ps -qf "name=miboleta_app" | head -1)
docker exec $APP_CONTAINER chown -R www-data:www-data storage bootstrap/cache
```

### La imagen no se actualiza

```bash
# Rebuild forzado
docker build --no-cache -t miboleta:local .

# Actualizar servicio
docker service update --force --image miboleta:local miboleta_app
```

### Puertos en uso

```bash
# Ver qué está usando el puerto 80
lsof -i :80

# Detener otros servicios que usen el puerto
# O cambiar el puerto en docker-stack.yml:
#   ports:
#     - "8080:80"
```

### Limpiar todo Docker

```bash
# CUIDADO: Esto elimina TODOS los contenedores, imágenes y volúmenes
docker system prune -a --volumes
```

---

## 📝 Archivos de Configuración

```
miboleta/
├── config/
│   ├── .env.local          # Variables de entorno de la app (local)
│   ├── .env.example        # Template
│   ├── nginx.conf          # Nginx config
│   └── my.cnf              # MySQL config
├── .env.stack              # Variables para docker-stack.yml
├── docker-stack.yml        # Definición del stack
├── test-swarm-local.sh     # Script de prueba automático
└── cleanup-swarm-local.sh  # Script de limpieza
```

---

## ✅ Checklist de Verificación

Antes de hacer el deploy a producción, verifica:

- [ ] Todos los servicios están corriendo (6 servicios)
- [ ] La aplicación es accesible en http://localhost
- [ ] Las migraciones se ejecutaron correctamente
- [ ] Puedes crear usuarios y hacer login
- [ ] Los archivos se pueden subir correctamente
- [ ] Horizon muestra las colas funcionando
- [ ] No hay errores en los logs
- [ ] Los websockets (Reverb) funcionan

```bash
# Verificar todos los servicios
docker stack services miboleta

# Debe mostrar:
# NAME                REPLICAS  IMAGE
# miboleta_app        1/1       miboleta:local
# miboleta_db         1/1       mysql:8.0
# miboleta_horizon    1/1       miboleta:local
# miboleta_nginx      1/1       nginx:alpine
# miboleta_redis      1/1       redis:7-alpine
# miboleta_reverb     1/1       miboleta:local
```

---

## 🚀 Siguiente Paso

Una vez que todo funcione correctamente en local, estás listo para:

1. Hacer commit de los cambios
2. Configurar los GitHub Secrets
3. Hacer push a `main` para trigger el deploy automático

Ver [DEPLOY.md](DEPLOY.md) para instrucciones de deploy a producción.
