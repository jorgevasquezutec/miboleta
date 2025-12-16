# Guía de Despliegue en Producción - MiBoleta

Esta guía te ayudará a desplegar MiBoleta en tu servidor VPS usando Docker Compose.

> **Nota:** Para deployment a GitHub Pages (frontend solo), consulta [DEPLOYMENT_GITHUB_PAGES.md](./DEPLOYMENT_GITHUB_PAGES.md)

## 📋 Requisitos Previos

Antes de comenzar, asegúrate de tener:

1. **Servidor VPS** con acceso SSH
2. **Dominio** configurado apuntando a tu servidor
3. **Docker** y **Docker Compose** instalados en el servidor
4. **Git** instalado (opcional, para deployment automático)
5. Al menos **2GB RAM** y **20GB espacio en disco**

## 🚀 Instalación en el Servidor

### 1. Conectarse al servidor

```bash
ssh usuario@tu-servidor.com
```

### 2. Instalar Docker y Docker Compose

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Agregar usuario al grupo docker (para ejecutar sin sudo)
sudo usermod -aG docker $USER

# Instalar Docker Compose
sudo apt install docker-compose-plugin -y

# Verificar instalación
docker --version
docker compose version
```

### 3. Clonar el repositorio (o subir archivos)

```bash
# Opción 1: Clonar desde Git
git clone https://github.com/tu-usuario/miboleta.git
cd miboleta

# Opción 2: Subir archivos manualmente
# Usa scp, rsync, o un cliente FTP
```

## ⚙️ Configuración

### 1. Configurar variables de entorno

```bash
# Copiar el archivo de ejemplo
cp .env.production.example .env.production

# Editar con tu editor favorito
nano .env.production
```

**Configuraciones CRÍTICAS que debes cambiar:**

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

# IMPORTANTE: Generar una clave única
# Ejecuta localmente: php artisan key:generate --show
APP_KEY=base64:TU_CLAVE_GENERADA_AQUI

# Database - Passwords FUERTES
DB_DATABASE=miboleta_prod
DB_USERNAME=miboleta_user
DB_PASSWORD=password_seguro_123_CAMBIAME
DB_ROOT_PASSWORD=root_password_seguro_456_CAMBIAME

# Redis - Password FUERTE
REDIS_PASSWORD=redis_password_seguro_789_CAMBIAME

# Reverb - Generar claves aleatorias
REVERB_APP_ID=miboleta-prod
REVERB_APP_KEY=genera_una_clave_aleatoria_32_chars
REVERB_APP_SECRET=genera_un_secreto_aleatorio_64_chars

# Mail - Configurar con tu proveedor SMTP
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_password_de_aplicacion
MAIL_FROM_ADDRESS=noreply@tudominio.com

# Dominio
SESSION_DOMAIN=.tudominio.com
SANCTUM_STATEFUL_DOMAINS=tudominio.com,www.tudominio.com
```

### 2. Configurar SSL (HTTPS)

**Opción A: Usar Certbot (Let's Encrypt) - RECOMENDADO**

```bash
# Instalar Certbot
sudo apt install certbot -y

# Generar certificados SSL
sudo certbot certonly --standalone -d tudominio.com -d www.tudominio.com

# Los certificados se generarán en:
# /etc/letsencrypt/live/tudominio.com/fullchain.pem
# /etc/letsencrypt/live/tudominio.com/privkey.pem

# Copiar certificados a la carpeta del proyecto
sudo mkdir -p ./docker/ssl
sudo cp /etc/letsencrypt/live/tudominio.com/fullchain.pem ./docker/ssl/cert.pem
sudo cp /etc/letsencrypt/live/tudominio.com/privkey.pem ./docker/ssl/key.pem
sudo chown -R $USER:$USER ./docker/ssl
```

**Opción B: Certificado auto-firmado (solo para testing)**

```bash
mkdir -p ./docker/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout ./docker/ssl/key.pem \
  -out ./docker/ssl/cert.pem
```

### 3. Actualizar configuración de Nginx

Edita [docker/nginx/production.conf](../docker/nginx/production.conf) y reemplaza `tudominio.com` con tu dominio real.

## 🎯 Despliegue

### Método 1: Script Automático (RECOMENDADO)

```bash
# Hacer el script ejecutable (solo primera vez)
chmod +x deploy.sh

# Ejecutar despliegue completo
./deploy.sh deploy
```

El script hará automáticamente:
- ✅ Verificar configuración
- ✅ Construir imágenes Docker
- ✅ Iniciar contenedores
- ✅ Ejecutar migraciones
- ✅ Optimizar Laravel
- ✅ Configurar permisos
- ✅ Reiniciar servicios

### Método 2: Manual

```bash
# 1. Construir imágenes
docker compose -f docker-compose.prod.yml build

# 2. Iniciar contenedores
docker compose -f docker-compose.prod.yml --env-file .env.production up -d

# 3. Esperar que la base de datos esté lista
sleep 10

# 4. Ejecutar migraciones
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# 5. Crear enlace de storage
docker compose -f docker-compose.prod.yml exec app php artisan storage:link

# 6. Optimizar Laravel
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache

# 7. Configurar permisos
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
```

## 🔍 Verificación

### Verificar que todos los contenedores están corriendo

```bash
docker compose -f docker-compose.prod.yml ps
```

Deberías ver todos los servicios con estado "Up":
- miboleta_app
- miboleta_nginx
- miboleta_mysql
- miboleta_redis
- miboleta_horizon
- miboleta_reverb
- miboleta_backup

### Verificar logs

```bash
# Ver todos los logs
./deploy.sh logs

# O manualmente
docker compose -f docker-compose.prod.yml logs -f

# Ver logs de un servicio específico
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f nginx
```

### Verificar la aplicación

Abre tu navegador y visita:
- **Frontend:** https://tudominio.com
- **API:** https://tudominio.com/api/health
- **Horizon:** https://tudominio.com/horizon (panel de colas)

## 🔧 Comandos Útiles

### Gestión de la aplicación

```bash
# Ver estado
./deploy.sh status

# Ver logs
./deploy.sh logs

# Reiniciar servicios
./deploy.sh restart

# Optimizar Laravel
./deploy.sh optimize

# Ejecutar backup manual
./deploy.sh backup

# Detener todo
./deploy.sh stop

# Iniciar todo
./deploy.sh start
```

### Comandos Laravel

```bash
# Ejecutar comandos artisan
docker compose -f docker-compose.prod.yml exec app php artisan [comando]

# Ejemplos:
docker compose -f docker-compose.prod.yml exec app php artisan tinker
docker compose -f docker-compose.prod.yml exec app php artisan queue:work
docker compose -f docker-compose.prod.yml exec app php artisan horizon:status
```

### Acceder a contenedores

```bash
# Acceder al contenedor de la app
docker compose -f docker-compose.prod.yml exec app bash

# Acceder a MySQL
docker compose -f docker-compose.prod.yml exec db mysql -u root -p
```

## 💾 Backups

### Backup Automático

El sistema incluye backups automáticos diarios a las 2 AM:
- Los backups se guardan en el volumen `mysql_backups`
- Se mantienen los últimos 7 días
- Los archivos se comprimen con gzip

### Ubicación de los backups

```bash
# Ver backups disponibles
docker volume inspect miboleta_mysql_backups

# Copiar backup a tu máquina local
docker cp miboleta_backup:/backups/miboleta_prod_20231215_020000.sql.gz ./
```

### Restaurar backup

```bash
# 1. Copiar backup al contenedor
docker cp backup.sql.gz miboleta_mysql:/tmp/

# 2. Restaurar
docker compose -f docker-compose.prod.yml exec db bash -c \
  "gunzip < /tmp/backup.sql.gz | mysql -u root -p[PASSWORD] miboleta_prod"
```

## 🔄 Actualizaciones

### Actualizar código

```bash
# 1. Detener servicios
./deploy.sh stop

# 2. Actualizar código
git pull origin main

# 3. Redesplegar
./deploy.sh deploy
```

### Actualizar solo el frontend

Si solo cambiaste archivos del frontend React, reconstruye y copia:

```bash
cd frontend
npm run build
# Los archivos se copiarán automáticamente a backend/public
```

## 🛡️ Seguridad

### Firewall

```bash
# Permitir solo puertos necesarios
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

### Fail2ban (protección contra ataques)

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Actualizar sistema regularmente

```bash
sudo apt update && sudo apt upgrade -y
```

## 📊 Monitoreo

### Ver uso de recursos

```bash
# CPU y memoria de contenedores
docker stats

# Espacio en disco
df -h

# Ver logs de errores
docker compose -f docker-compose.prod.yml logs --tail=100 app | grep ERROR
```

### Horizon (monitoreo de colas)

Accede a https://tudominio.com/horizon para ver:
- Estado de las colas
- Jobs procesados
- Jobs fallidos
- Métricas de rendimiento

## 🆘 Troubleshooting

### Contenedor no inicia

```bash
# Ver logs del contenedor
docker compose -f docker-compose.prod.yml logs [servicio]

# Reiniciar contenedor específico
docker compose -f docker-compose.prod.yml restart [servicio]
```

### Error de permisos

```bash
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
```

### Limpiar todo y empezar de nuevo

```bash
# ⚠️ CUIDADO: Esto eliminará TODOS los datos
docker compose -f docker-compose.prod.yml down -v
./deploy.sh deploy
```

### Base de datos no responde

```bash
# Verificar estado de MySQL
docker compose -f docker-compose.prod.yml exec db mysqladmin ping -u root -p

# Reiniciar MySQL
docker compose -f docker-compose.prod.yml restart db
```

## 📝 Checklist de Producción

Antes de poner en producción, verifica:

- [ ] APP_DEBUG=false en .env.production
- [ ] APP_KEY generada y configurada
- [ ] Passwords fuertes para DB, Redis, etc.
- [ ] SSL/HTTPS configurado correctamente
- [ ] Dominio apuntando al servidor
- [ ] Firewall configurado
- [ ] Backups automáticos funcionando
- [ ] Logs rotando correctamente
- [ ] Horizon ejecutándose
- [ ] Reverb (WebSockets) funcionando
- [ ] Email SMTP configurado y probado
- [ ] Pruebas de carga realizadas

## 🎓 Recursos Adicionales

- [Documentación de Docker Compose](https://docs.docker.com/compose/)
- [Documentación de Laravel Deployment](https://laravel.com/docs/deployment)
- [Documentación de Laravel Horizon](https://laravel.com/docs/horizon)
- [Documentación de Laravel Reverb](https://laravel.com/docs/reverb)

## 📞 Soporte

Si encuentras problemas:
1. Revisa los logs: `./deploy.sh logs`
2. Verifica la configuración en `.env.production`
3. Consulta la sección de Troubleshooting arriba

---

**¡Tu aplicación está lista para producción! 🚀**
