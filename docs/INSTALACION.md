# MiBoleta — Instalación en un servidor

Procedimiento para instalar la plataforma en un servidor propio: un VPS, una
máquina física de la empresa o una instancia en la nube.

| Atributo | Valor |
| --- | --- |
| **Versión** | 1.1.0 |
| **Fecha** | Agosto 2026 |
| **Audiencia** | Personal técnico de sistemas |

---

## 1. Qué necesita el servidor

| Recurso | Mínimo | Recomendado |
| --- | --- | --- |
| Sistema operativo | Linux de 64 bits (Ubuntu 22.04+, Debian 12+, Rocky 9+) | Ubuntu 24.04 LTS |
| **Arquitectura** | **x86_64 (amd64)** — ver aviso abajo | |
| CPU | 2 núcleos | 4 núcleos |
| Memoria | 4 GB | 8 GB |
| Disco | 40 GB | 100 GB o más, según el volumen de documentos |
| Software | Docker 24+ con el plugin `docker compose` v2 | |

> ### ⚠ La arquitectura importa
>
> Las imágenes de MiBoleta se publican **solo para x86_64 (amd64)**. En un
> servidor ARM la instalación se detiene con un aviso.
>
> Compruébelo antes de empezar:
>
> ```bash
> uname -m      # debe responder x86_64
> ```
>
> La mayoría de los VPS son x86_64, pero **no todos**: el nivel gratuito de
> Oracle Cloud, las instancias AWS Graviton y los servidores Ampere son ARM.
>
> Si su servidor es ARM y su Docker admite emulación, puede forzarla con
> `FORZAR_AMD64=1 ./instalar.sh`, asumiendo un rendimiento sensiblemente menor.
> Para uso real, es preferible un servidor x86_64.

**Puertos que deben quedar accesibles:**

| Puerto | Para qué | ¿Desde internet? |
| --- | --- | --- |
| 80 | Acceso web (y renovación de certificados) | Sí |
| 443 | Acceso web cifrado | Sí, si se usa HTTPS |
| 8085 | Notificaciones en tiempo real | **No hace falta abrirlo** |

> Las notificaciones viajan por el mismo puerto que la web (80 o 443), a través
> de la ruta `/app`. El 8085 solo se publica por comodidad de diagnóstico; puede
> dejarlo cerrado al exterior.

El resto de servicios —base de datos, Redis, firma— **no se publican**: solo
son accesibles entre contenedores.

### Instalar Docker, si no está

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"      # para no usar sudo en cada comando
newgrp docker                         # aplica el cambio sin cerrar sesión
docker compose version                # debe responder v2.x
```

---

## 2. Instalación

### Paso 1 — Copiar el paquete al servidor

```bash
# Desde su equipo
scp miboleta-v1.1.0.tar.gz usuario@servidor:~/

# Ya en el servidor
ssh usuario@servidor
sudo mkdir -p /opt/miboleta && sudo chown "$USER": /opt/miboleta
tar -xzf ~/miboleta-v1.1.0.tar.gz -C /opt/miboleta
cd /opt/miboleta/miboleta-v1.1.0
```

> Se copia primero a su carpeta personal y luego se extrae en `/opt`: ese
> directorio pertenece a `root`, así que un `scp` directo contra `/opt/` falla
> con *Permission denied*. Si prefiere evitar `sudo`, instale en `~/miboleta`;
> todo funciona igual.

### Paso 2 — Configurar

```bash
cd produccion
cp .env.example .env
nano .env
```

Hay que completar, como mínimo:

| Variable | Qué poner |
| --- | --- |
| `APP_URL` | La dirección por la que se accederá: `https://miboleta.tuempresa.com` o `http://<IP>` |
| `DB_PASSWORD` y `DB_ROOT_PASSWORD` | Dos contraseñas distintas, nuevas |
| `MAIL_*` | Los datos del servidor de correo saliente |
| `REVERB_APP_KEY` y `REVERB_APP_SECRET` | Dos valores propios |

`APP_KEY` puede quedar vacía: la genera el instalador.

> El instalador **se niega a continuar** si detecta valores `CAMBIAME` sin
> completar. Es deliberado: una instalación con contraseñas de ejemplo arranca
> igual, y el problema no se descubre hasta que ya está en uso.

### Paso 3 — Instalar

```bash
./instalar.sh
```

El script comprueba los requisitos, genera la clave de cifrado, arranca los
servicios, ejecuta las migraciones, siembra los catálogos base (roles y tipos
de documento) y pide los datos del primer administrador.

Tarda entre 3 y 10 minutos según la máquina y si las imágenes vienen en el
paquete o hay que descargarlas.

### Paso 4 — Comprobar

Abra `APP_URL` en un navegador e inicie sesión con el administrador que acaba
de crear. Si responde, la instalación está terminada.

```bash
docker compose -f docker-compose.produccion.yml -p miboleta ps
```

Los siete servicios deben aparecer como `Up`.

---

## 3. Después de instalar

Estos tres puntos **no** los hace el instalador y son necesarios antes de usar
el sistema de verdad.

### 3.1 Copias de seguridad

Sin esto, un fallo de disco pierde todas las boletas y todo el histórico.

El paquete incluye un script que respalda las tres cosas que hacen falta: la
base de datos, los documentos almacenados y el `.env`.

```bash
./backup.sh                    # guarda en ./backups
DESTINO=/mnt/nas ./backup.sh   # o donde prefiera
```

Automatícelo con `cron`, por ejemplo cada noche a las 2:00:

```bash
crontab -e
# añadir:
0 2 * * * cd /opt/miboleta/miboleta-v1.1.0/produccion && ./backup.sh >> backups/cron.log 2>&1
```

**Copie los respaldos fuera del servidor.** Una copia que vive en la misma
máquina no protege de la avería de esa máquina.

> El script respalda también el `.env` porque contiene la `APP_KEY`. Sin ella no
> se pueden descifrar las contraseñas de correo ni la del certificado de firma,
> aunque se tenga la base de datos íntegra. Ese archivo se guarda con permisos
> restringidos: trátelo como una credencial.

### 3.2 HTTPS

Se accede por HTTP simple mientras no se configure un certificado. Para un
sistema que maneja boletas de pago, **es necesario cifrar el tráfico**.

Con un dominio apuntando al servidor, lo más simple es Let's Encrypt:

```bash
sudo apt-get update && sudo apt-get install -y certbot   # Ubuntu/Debian
# En Rocky/Alma:  sudo dnf install -y epel-release && sudo dnf install -y certbot

# certbot necesita el puerto 80 para sí: hay que liberarlo
docker compose -f docker-compose.produccion.yml -p miboleta stop nginx

sudo certbot certonly --standalone -d miboleta.tuempresa.com

# Copiar el certificado a donde nginx lo espera (desde produccion/)
sudo cp /etc/letsencrypt/live/miboleta.tuempresa.com/fullchain.pem ssl/
sudo cp /etc/letsencrypt/live/miboleta.tuempresa.com/privkey.pem  ssl/
sudo chown "$USER": ssl/*.pem
```

Ahora active el bloque HTTPS. Al final de `nginx.conf` hay un `server` completo
comentado, entre dos marcas bien visibles:

```
# >>>>>>>>>>  DESCOMENTAR DESDE AQUI  >>>>>>>>>>
...
# <<<<<<<<<<  DESCOMENTAR HASTA AQUI  <<<<<<<<<<
```

Quite el `# ` del principio de cada línea que quede **entre** las dos marcas, sin
tocar las marcas mismas. Después cambie `APP_URL` a `https://...` en el `.env`.

**Compruebe la configuración antes de aplicarla.** Un `# ` de más o de menos deja
nginx sin arrancar, y si lo descubre al aplicar, el sitio ya está caído:

```bash
docker run --rm \
  -v "$PWD/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
  -v "$PWD/ssl:/etc/nginx/ssl:ro" \
  nginx:1.27-alpine nginx -t
```

Debe responder `syntax is ok` y `test is successful`. Los dos fallos que aparecen
aquí, y qué significan:

| Lo que dice | Qué pasó |
| --- | --- |
| `unknown directive "..."` | Una línea mal descomentada. El número de línea del mensaje es esa línea. |
| `cannot load certificate` | Los `.pem` no están en `ssl/`, o el usuario no puede leerlos. Repase el `cp` y el `chown` de arriba. |

Corrija y repita hasta que pase, antes de continuar.

Cuando la comprobación pase, aplique:

```bash
docker compose -f docker-compose.produccion.yml -p miboleta up -d
```

> **`up -d`, no `restart`.** `restart` reinicia el contenedor con las variables
> con las que se creó: el `APP_URL` nuevo no se leería y los enlaces de los
> correos seguirían apuntando a `http://`. `up -d` detecta el cambio y recrea
> lo necesario.

Compruebe que responde por HTTPS, incluida una dirección interna del sistema:

```bash
curl -I https://miboleta.tuempresa.com/health        # 200
curl -I https://miboleta.tuempresa.com/vacaciones    # 200, no 404
```

**Cierre el HTTP.** Con el certificado ya en marcha, el puerto 80 sigue sirviendo
las boletas sin cifrar a quien las pida por ahí. Para que redirija, añada esta
línea dentro del `server { listen 80; ... }` del principio de `nginx.conf`,
justo después del `listen 80;`:

```nginx
    return 301 https://$host$request_uri;
```

Vuelva a comprobar con `nginx -t`, aplique con `up -d`, y verifique:

```bash
curl -I http://miboleta.tuempresa.com/vacaciones     # 301 -> https://...
```

> **Solo si usa los puertos estándar.** Esa redirección manda al 443. Si cambió
> `HTTP_PORT` o `HTTPS_PORT` en el `.env`, el destino saldría sin puerto y no
> funcionaría: escriba entonces el puerto a mano, por ejemplo
> `return 301 https://$host:8443$request_uri;`.

**Renovación automática.** El certificado caduca a los 90 días. Sin esto, el
sitio deja de funcionar sin aviso:

Se automatiza con los *hooks* de certbot, que solo se ejecutan **si de verdad
toca renovar**: así nginx no se para todas las semanas para nada.

```bash
sudo crontab -e
```

Añada esto **en una sola línea**, sin cortarla:

```
0 3 * * 1 cd /opt/miboleta/miboleta-v1.1.0/produccion && certbot renew --quiet --pre-hook "docker compose -f docker-compose.produccion.yml -p miboleta stop nginx" --post-hook "cp /etc/letsencrypt/live/*/fullchain.pem ssl/ && cp /etc/letsencrypt/live/*/privkey.pem ssl/ && docker compose -f docker-compose.produccion.yml -p miboleta start nginx"
```

> **Una sola línea, obligatoriamente.** `crontab` **no admite** partir una
> entrada en varias líneas con `\`: si lo hace, cada trozo se toma como una
> entrada distinta, todas inválidas, y `crontab -e` rechaza el archivo entero
> al guardar.

Compruebe que quedó bien y que la renovación funcionaría:

```bash
sudo crontab -l                  # debe mostrar la línea completa
sudo certbot renew --dry-run     # ensayo sin renovar de verdad
```

### 3.3 Certificado de firma digital

La firma criptográfica de documentos requiere un certificado propio de la
empresa. Se carga desde la aplicación: **Ajustes de plataforma → Certificado de
firma**, con el usuario Super Administrador.

Sin certificado, todo lo demás funciona; solo queda inactiva la firma digital.

---

## 4. Operación diaria

| Acción | Comando |
| --- | --- |
| Ver el estado | `docker compose -f docker-compose.produccion.yml -p miboleta ps` |
| Ver registros de la aplicación | `... logs -f app` |
| Ver registros de las tareas | `... logs -f horizon` |
| Reiniciar todo | `... restart` |
| Detener | `... down` |
| Arrancar | `... up -d` |

*(`...` = `docker compose -f docker-compose.produccion.yml -p miboleta`)*

### Actualizar a una versión nueva

```bash
# 1. Copia de seguridad ANTES de nada
./backup.sh

# 2. Nueva versión de las imágenes en .env
nano .env            # MIBOLETA_IMAGE y MIBOLETA_SIGNER_IMAGE

# 3. Aplicar
docker compose -f docker-compose.produccion.yml -p miboleta pull
docker compose -f docker-compose.produccion.yml -p miboleta up -d
```

Cambie **las dos** imágenes: si solo actualiza `MIBOLETA_IMAGE`, el servicio de
firma se queda en la versión anterior.

Las migraciones de base de datos se aplican solas al arrancar el contenedor
`app`. Actualice **en horario sin actividad**: las tareas en curso (cargas
masivas, envíos) se interrumpen al recrear los contenedores, aunque se
reintentan solas.

**Si el paquete es de instalación sin conexión** (trae carpeta `imagenes/`), no
use `pull`: cargue las imágenes del paquete nuevo y luego aplique.

```bash
for t in imagenes/*.tar; do docker load -i "$t"; done
docker compose -f docker-compose.produccion.yml -p miboleta up -d
```

### Volver atrás si la actualización falla

```bash
# 1. Volver a la imagen anterior en .env y aplicar
nano .env
docker compose -f docker-compose.produccion.yml -p miboleta up -d

# 2. Si además hay que restaurar la base de datos
gunzip -c backups/miboleta-db-FECHA.sql.gz | \
  docker compose -f docker-compose.produccion.yml -p miboleta exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$MYSQL_DATABASE"'

# 3. Y los documentos, si se perdieron
docker run --rm -v miboleta_storage_data:/data \
  -v "$PWD/backups":/in alpine \
  tar xzf /in/miboleta-storage-FECHA.tgz -C /data
```

> Restaurar la base **sustituye** su contenido actual. Haga una copia del estado
> presente antes, aunque esté defectuoso: puede contener datos posteriores al
> último respaldo.

---

## 5. Si algo falla

| Síntoma | Causa habitual | Qué hacer |
| --- | --- | --- |
| La web no carga | El puerto 80 está ocupado por otro servicio | Cambie `HTTP_PORT` en `.env` y aplique con `... up -d` (no `restart`) |
| La web no carga y el puerto está libre | El cortafuegos bloquea el acceso | `sudo ufw allow 80/tcp && sudo ufw allow 443/tcp` |
| El login falla sin mensaje claro | Se accede por una dirección distinta a `APP_URL` | Use exactamente la dirección de `APP_URL`, o corríjala y `... up -d` |
| Error 500 al entrar | Las migraciones no terminaron | `... logs app` y espere o reintente |
| No llegan los correos | `MAIL_*` mal configurado | `... exec app php artisan email:test su-correo@empresa.com` |
| No se procesan las cargas masivas | `horizon` caído | `... logs horizon` y `... restart horizon` |
| La campana no avisa | `reverb` caído, o `REVERB_HOST` mal puesto | `... logs reverb` y compruebe que en `.env` dice `REVERB_HOST=reverb` (no una IP ni `0.0.0.0`) |
| nginx se reinicia en bucle nada más instalar | SELinux bloquea los archivos montados (Rocky, Alma, RHEL) | Los montajes ya llevan la opción `z`; si persiste: `sudo chcon -Rt container_file_t nginx.conf ssl/` |
| Falla la firma digital | `signer` caído o sin certificado | `... logs signer` y revise Ajustes de plataforma |

Para ver todo junto:

```bash
docker compose -f docker-compose.produccion.yml -p miboleta logs --tail=100
```

---

## 6. Desinstalar

```bash
# Detener y borrar contenedores, CONSERVANDO los datos
docker compose -f docker-compose.produccion.yml -p miboleta down

# Borrar TAMBIÉN los datos (irreversible)
docker compose -f docker-compose.produccion.yml -p miboleta down -v
```

> `down -v` borra la base de datos y los documentos almacenados. Haga copia de
> seguridad antes si hay algo que conservar.

Dos matices:

- **Sus copias de `./backups` no se borran.** Están en una carpeta del servidor,
  no en un volumen de Docker.
- **Las imágenes tampoco**, y ocupan varios GB. Para liberar ese espacio:

```bash
docker image rm ghcr.io/jorgevasquezutec/miboleta:latest \
                ghcr.io/jorgevasquezutec/miboleta-signer:latest
docker image prune -f
```
