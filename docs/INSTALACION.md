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
| 8085 | Notificaciones en tiempo real | Sí, si se quieren avisos instantáneos |

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
scp miboleta-v1.1.0.tar.gz usuario@servidor:/opt/
ssh usuario@servidor
cd /opt && tar -xzf miboleta-v1.1.0.tar.gz && cd miboleta-v1.1.0
```

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
0 2 * * * cd /opt/miboleta-v1.1.0/produccion && ./backup.sh >> backups/cron.log 2>&1
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
sudo apt-get install certbot
sudo certbot certonly --standalone -d miboleta.tuempresa.com

# Copiar el certificado a donde nginx lo espera
sudo cp /etc/letsencrypt/live/miboleta.tuempresa.com/fullchain.pem ssl/
sudo cp /etc/letsencrypt/live/miboleta.tuempresa.com/privkey.pem ssl/
```

Descomente el bloque `server` de HTTPS en `nginx.conf`, ponga `APP_URL` con
`https://` y reinicie:

```bash
docker compose -f docker-compose.produccion.yml -p miboleta restart nginx app
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
nano .env            # MIBOLETA_IMAGE=...:v1.2.0

# 3. Aplicar
docker compose -f docker-compose.produccion.yml -p miboleta pull
docker compose -f docker-compose.produccion.yml -p miboleta up -d
```

Las migraciones de base de datos se aplican solas al arrancar el contenedor
`app`.

---

## 5. Si algo falla

| Síntoma | Causa habitual | Qué hacer |
| --- | --- | --- |
| La web no carga | El puerto 80 está ocupado por otro servicio | Cambie `HTTP_PORT` en `.env` y reinicie |
| Error 500 al entrar | Las migraciones no terminaron | `... logs app` y espere o reintente |
| No llegan los correos | `MAIL_*` mal configurado | `... exec app php artisan miboleta:test-email` |
| No se procesan las cargas masivas | `horizon` caído | `... logs horizon` y `... restart horizon` |
| La campana no avisa | El puerto de `reverb` no es accesible | Abra `REVERB_PUBLIC_PORT` en el cortafuegos |
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
