# MiBoleta — Sistema de Gestión Documental Multi-Tenant

Plataforma **multi-tenant** para gestión de boletas/documentos, usuarios, vacaciones (régimen laboral Perú), **firma digital legal (PAdES)** y carga masiva, con panel por empresa y administración de plataforma.

- **Frontend:** React 18 + TypeScript + Vite + Tailwind
- **Backend:** Laravel 12 + PHP 8.4 (Sanctum, Horizon, Reverb)
- **Firma legal:** sidecar Python (Ghostscript + pyHanko) que produce PDF/A-2b firmado con PAdES + sellado de tiempo
- **Infra:** Docker Compose (dev) · Docker Swarm + GitHub Actions (prod)

---

## 🚀 Quick Start (desarrollo)

### Requisitos
- Node.js 18+
- Docker y Docker Compose
- Git

### Instalación

```bash
git clone https://github.com/jorgevasquezutec/miboleta.git
cd miboleta

npm install                 # dependencias frontend
docker compose up -d        # app (Laravel) + nginx + MySQL + Redis + Horizon + Reverb + signer + adminer
npm run laravel:fresh       # migraciones + seeds
npm run dev                 # frontend en modo desarrollo
```

### Usuarios de prueba

Con `npm run laravel:fresh` (seeders base). Para una demo completa —con documentos,
vacaciones en todos los estados y saldos calculados— usa `DemoSeeder`:

```bash
docker compose exec app php artisan migrate:fresh --seed --seeder='Database\Seeders\DemoSeeder'
```

| Identificador | Rol | Password |
|---|---|---|
| admin@email.com | Super Administrador (root) | password |
| admin.clientes@miboleta.demo | Admin Clientes | password |
| admin@corporacionabc.com | Admin Empleados | password |
| aprobador@miboleta.demo | Aprobador | password |
| juan.perez@corporacionabc.com | Empleado | password |

> El login acepta **DNI o correo**. Los roles operativos son **por empresa**; el rol root es de plataforma. Ver [docs/TECHNICAL-DOCUMENTATION.md](docs/TECHNICAL-DOCUMENTATION.md).

---

## ✨ Módulos y capacidades

- **Multi-tenant con roles por empresa.** Un usuario puede pertenecer a varias empresas; sus roles operativos viven por empresa (pivote), y hay un switcher de empresa + rol. Root = plataforma.
- **Login por DNI o correo.**
- **Gestión de documentos/boletas** con visor PDF y descarga.
- **Firma digital legal (PAdES).** Normaliza a PDF/A-2b (Ghostscript) y firma con pyHanko + TSA usando un certificado único de plataforma (cifrado). Ver [Firma digital](#-firma-digital-legal-signer).
- **Vacaciones (Perú).** Saldo por empresa, 30/15 días según régimen, devengo por aniversario, flujo de solicitud/aprobación.
- **Carga masiva de usuarios.** Import asíncrono escalable (patrón de batches) — una fila por (usuario × empresa) agrupada por DNI.
- **Correo por empresa.** SMTP configurable por empresa con fallback al default de la plataforma.
- **Auditoría** por dominio (AuditService).

---

## 📦 Scripts NPM (dev)

```bash
# Desarrollo
npm run dev           # localhost
npm run dev:mobile    # auto-detecta IP de red (probar desde el celular)
npm run dev:local     # restaurar configuración localhost

# Docker (dev)
npm run docker:up | docker:down | docker:logs | docker:restart

# Laravel (dev)
npm run laravel:migrate   # migraciones
npm run laravel:fresh     # reset DB + seeds
npm run laravel:shell     # shell en el contenedor PHP
npm run laravel:cache     # cachear config y rutas

# Build
npm run build             # compilar frontend
npm run build:copy        # build + copiar a backend/public
```

### Desarrollo móvil
`npm run dev:mobile` detecta tu IP local, actualiza `.env.local` y `backend/.env`, y arranca Vite con `--host`. Luego `docker compose restart`. Volver con `npm run dev:local`.

---

## 🏗️ Arquitectura

```
                         ┌───────────────────────────┐
                         │   Frontend React + Vite    │
                         │   (compilado en la imagen  │
                         │    del app y servido por    │
                         │    nginx en producción)     │
                         └─────────────┬─────────────┘
                                       ▼
                         ┌───────────────────────────┐
                         │           NGINX            │
                         │  sirve SPA + /storage +    │
                         │  proxy /api,/horizon,/app  │
                         └─────────────┬─────────────┘
                                       ▼
       ┌───────────────────────────────────────────────────────┐
       │                 LARAVEL 12 (PHP 8.4-fpm)               │
       │        app · horizon (queues) · reverb (WS)           │
       │            (misma imagen, distinto command)           │
       └───┬───────────┬───────────┬──────────────┬────────────┘
           ▼           ▼           ▼              ▼
     ┌─────────┐ ┌─────────┐ ┌──────────┐  ┌──────────────┐
     │ MySQL 8 │ │  Redis  │ │  Reverb  │  │   signer     │
     │         │ │ (queues │ │  (WS)    │  │ Ghostscript  │
     │         │ │ +cache) │ │          │  │ + pyHanko    │
     └─────────┘ └─────────┘ └──────────┘  └──────────────┘
                                            (firma PAdES, red interna)
```

**Nota clave:** el frontend y el backend van en **la misma imagen** (`ghcr.io/<repo>/miboleta`). El `signer` es una **imagen aparte** (`...-signer`). Ver [Deploy](#-deploy-a-producción-docker-swarm).

### Stack

**Frontend:** React 18 · TypeScript · Vite · Tailwind v4 · shadcn/ui · Zustand · React Router v7 · React Query · Recharts
**Backend:** Laravel 12 · PHP 8.4 · Sanctum (auth) · Reverb (WebSockets) · Horizon (queues) · MySQL 8 · Redis
**Firma:** Python 3.12 · FastAPI/uvicorn · Ghostscript · pyHanko (PAdES + TSA)
**Infra:** Docker Compose (dev) · Docker Swarm (prod) · GitHub Actions (CI/CD) · Nginx · GHCR (registro de imágenes)

---

## 📁 Estructura del proyecto

```
miboleta/
├── src/                      # Frontend React (application/domain/infrastructure/presentation)
├── backend/                  # Laravel API (Http/Models/Services, migrations, seeders, routes/api.php)
├── signer/                   # Sidecar de firma legal (app.py FastAPI, pipeline.py, Dockerfile)
├── config/                   # Config de PRODUCCIÓN (nginx.conf, my.cnf, .env, .env.stack, .env.vite.swarm)
├── docker/                   # Config de DEV (nginx/default.conf, php/)
├── scripts/                  # Utilidades (dev-mobile.sh, dev-local.sh)
├── .github/workflows/
│   ├── tests.yml             # Suite de tests (gatilla el deploy)
│   └── docker-build-deploy.yml  # CI/CD: build imágenes + deploy a Swarm
├── docker-compose.yml        # Stack de DESARROLLO (bind-mounts)
├── docker-stack.yml          # Stack de PRODUCCIÓN (Swarm, imágenes de GHCR)
└── Makefile                  # Despliegue MANUAL a prod (plan B del CI) — ver abajo
```

---

## 🔐 Autenticación y roles

- **Método:** Laravel Sanctum en **modo token** (`Bearer`, guardado en `localStorage`), no cookies de sesión. Access token 1 h, refresh 30 días.
- **Login:** por **DNI o correo**.
- **Multi-tenant:** roles operativos **por empresa** (pivote `user_tenant_roles`), rol **root** global de plataforma. Dos switchers (empresa + rol).

| Rol | Nombre en pantalla | Alcance |
|---|---|---|
| `root` | Super Administrador | Plataforma: empresas, certificado de firma, ajustes globales |
| `admin_tenant` | Admin Clientes | Su empresa completa, incluidos usuarios Admin y Aprobador |
| `admin` | Admin Empleados | Operación diaria de su empresa |
| `aprobador` | Aprobador | Aprueba, rechaza y confirma vacaciones de su equipo |
| `client` | Empleado | Sus documentos, su firma y sus vacaciones |

La matriz completa está en `backend/config/access_matrix.php` (fuente única, expuesta en `GET /api/access-matrix`). `root` **no es comodín**: solo puede lo que la matriz le concede.

Detalle en [docs/TECHNICAL-DOCUMENTATION.md](docs/TECHNICAL-DOCUMENTATION.md).

---

## ✍️ Firma digital legal (signer)

El `signer` es un **sidecar HTTP interno** (FastAPI) que:
1. Normaliza el PDF a **PDF/A-2b** con Ghostscript.
2. Lo firma con **pyHanko** (PAdES) + **sellado de tiempo (TSA)** usando el certificado único de plataforma (cifrado).

**Importante (diseño):** el backend le pasa al signer **rutas absolutas del filesystem** (`input_path`, `output_path`, `certificate_path`) que viven en `storage/app/{documents,certificates}`. Por eso, en producción, el servicio `signer` **monta el mismo volumen `storage_data`** que `app`/`horizon` en `/var/www/html/storage`, y no publica puerto (solo alcanzable en la red interna vía `http://signer:8000`).

Spike manual de diagnóstico:
```bash
docker compose run --rm signer python /opt/signer/spike_sign.py <pdf>
```

---

## 🚀 Deploy a producción (Docker Swarm)

Producción corre en **Docker Swarm** (`/opt/miboleta` en el servidor) con imágenes publicadas en **GHCR**. El código NO se monta por bind-mount: va **horneado en la imagen** (por eso un `git pull` en el server no despliega nada — hay que reconstruir imagen).

### Flujo automático (CI/CD)

```
push a main  →  workflow "Tests"  →  (si pasa)  →  workflow "Build & Deploy"
                                                     ├─ build+push imagen app     (front+back)  → GHCR :latest
                                                     ├─ build+push imagen signer                → GHCR -signer:latest
                                                     └─ VPN → SSH al server:
                                                          docker pull (app + signer)
                                                          docker stack deploy -c docker-stack.yml
                                                          artisan migrate --force
                                                          artisan config:cache / route:cache / view:cache
```

Servicios del stack: `app`, `nginx`, `db`, `redis`, `horizon`, `reverb`, `adminer`, `signer`.

### Secrets requeridos en GitHub

```
VPN_HOST, VPN_PORT, VPN_USER, VPN_PASS, VPN_CERT   # conexión VPN del runner
SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS             # SSH al servidor
VITE_REVERB_APP_KEY, VITE_REVERB_HOST              # se hornean en el frontend en build time
```

### Deploy MANUAL desde tu Mac (`Makefile`) — plan B del CI

Fallback para cuando el CI no despliega (típico: *build OK pero el paso de deploy falló por VPN/SSH*). Requiere el alias SSH `miboleta` configurado; **la VPN la pones tú** al correr `make`.

| Comando | Qué hace |
|---|---|
| `make publish` | **Todo lo que se puede a mano:** build+push del signer (amd64) → copia `docker-stack.yml` → deploy en el server (pull imágenes + `stack deploy` + migrate + caches + estado) |
| `make deploy` | Solo el lado servidor (las imágenes ya están en GHCR) |
| `make nginx` | Copia `config/nginx.conf` al server y **recarga nginx sin rebuild** (con fallback a `service update --force`) |
| `make signer-build` | Construye+sube solo la imagen del signer |
| `make stack` | Copia solo el `docker-stack.yml` al server |
| `make help` | Lista los targets |

Gotchas que el Makefile ya resuelve:
- **Mac arm64 vs server amd64:** la imagen del signer se construye con `--platform linux/amd64` (si no, no corre en el server).
- **`.env.stack`** se carga con `set -a; . ./.env.stack` (nunca con `xargs`, o `REDIS_PASSWORD` queda vacío y tumba redis/horizon/reverb).
- El server necesita el `docker-stack.yml` nuevo → se copia antes del deploy.

> ⚠️ El deploy manual **no reconstruye el frontend** (el app image se deja al CI, porque el build del frontend necesita los secrets de Vite que no están en local). Cubre: desplegar la imagen del app que el CI ya subió + signer + migraciones + caches.

---

## 💾 Almacenamiento, volúmenes y persistencia

Los archivos subidos (logos, documentos, certificados) se guardan en el volumen **`storage_data`** (`/var/www/html/storage`), montado por `app`, `horizon`, `nginx` y `signer`. Discos de Laravel (`backend/config/filesystems.php`):

| Disco | Ruta | Uso | Acceso |
|---|---|---|---|
| `public` | `storage/app/public` | logos y assets públicos | nginx `/storage` (alias directo) |
| `documents` | `storage/app/documents` | boletas/documentos | privado (streaming por controlador) |
| `certificates` | `storage/app/certificates` | certificado de firma | privado |

**Persistencia:** reconstruir/redesplegar la imagen **NO borra** estos archivos — viven en el volumen, no en la imagen. La única forma de perderlos es **borrar/recrear el volumen** (`docker volume rm`, cambiar su nombre, o `docker stack rm` + prune). → Recomendado: **backup del volumen `storage_data`** (cron con `tar`/`restic`).

**Cómo sirve nginx `/storage` (y un gotcha resuelto):** en `config/nginx.conf`, `/storage` usa un **`alias`** directo al volumen. Debe declararse con `location ^~ /storage { ... }`. Sin el `^~`, la `location` de estáticos por regex (`~* \.(jpg|png|...)$`) **gana en precedencia** e intercepta las imágenes bajo `/storage`, sirviéndolas desde el `root` equivocado → **404 en todos los logos**. Ver [Troubleshooting](#-troubleshooting).

**Migración a dominio (IP → dominio):** las URLs de archivos **no se guardan en la BD** — se computan desde `APP_URL`. Para pasar de IP a dominio: cambiar `APP_URL=https://tudominio` en `config/.env`, `artisan config:cache`, y configurar `TrustProxies`. No requiere migrar datos.

---

## 🧭 Notas operativas (decisiones)

- **MinIO / object storage:** evaluado y **descartado por ahora**. Los problemas de "no veo imágenes" eran de **nginx**, no de storage; la persistencia ya funciona en el volumen. Además, mover documentos/certificados a object storage **rompería el signer** (que firma por ruta absoluta del filesystem). Reconsiderar solo si se va a **multi-nodo** o se quieren **URLs firmadas**.
- **Monitoreo (Beszel u otro):** opcional. El servidor está holgado (disco/RAM/CPU con mucho margen); útil solo por **alertas de caída/uptime y tendencias**, no por presión de recursos. Antes de instalar nada: limpiar imágenes colgantes (`docker image prune -f`, opcional en cron).

---

## 🛠️ Troubleshooting

### Logos / imágenes de `/storage` dan 404 en producción
Causa: precedencia de `location` en nginx (la regex de estáticos gana sobre `/storage`). Fix: `location ^~ /storage` en `config/nginx.conf`, luego aplicar sin rebuild:
```bash
make nginx     # copia config/nginx.conf al server + nginx -t + reload (con fallback a service update --force)
```
Verificar que el archivo exista en el volumen:
```bash
ssh miboleta 'docker exec $(docker ps -qf name=miboleta_app|head -1) ls -la /var/www/html/storage/app/public/tenants/logos/'
```

### El signer no firma / no responde
```bash
ssh miboleta 'docker stack services miboleta | grep signer'                         # debe estar N/N
ssh miboleta 'docker exec $(docker ps -qf name=miboleta_app|head -1) sh -c "php artisan tinker --execute=\"echo file_get_contents(\\\"http://signer:8000/health\\\");\""'
```
Recordar que la firma real necesita el **certificado cargado en prod** (se sube por la UI → `storage/app/certificates`).

### El deploy del CI "pasa" pero no veo cambios
Revisar el run: `gh run view <id> --log`. Si el build fue OK pero el deploy falló (VPN), usar `make deploy` (o `make publish`).

### Dev: "Network Error" al login / puerto en uso / móvil no conecta
- `docker compose ps` y `npm run docker:logs`
- Puerto ocupado: `lsof -i :5173` → `kill -9 <PID>`
- Móvil: misma red WiFi + `npm run dev:mobile` + `docker compose restart`

---

## 📚 Documentación adicional

**Documentación vigente** (se regenera en PDF desde `docs/pdf-generator`):

| Documento | Descripción |
|---|---|
| [docs/USER-MANUAL.md](docs/USER-MANUAL.md) | Manual de usuario, por rol |
| [docs/TECHNICAL-DOCUMENTATION.md](docs/TECHNICAL-DOCUMENTATION.md) | Arquitectura, autenticación, roles y despliegue |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación en un servidor propio |
| [docs/MODELADO_BASE_DATOS_SQL.md](docs/MODELADO_BASE_DATOS_SQL.md) | Modelo de datos |

**Notas técnicas de apoyo:**

| Documento | Descripción |
|---|---|
| [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) | Entorno de desarrollo con hot reload |
| [docs/CLEAN_ARCHITECTURE.md](docs/CLEAN_ARCHITECTURE.md) | Arquitectura del frontend |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Guía de despliegue |
| [docs/DEPARTMENTS_IMPLEMENTATION.md](docs/DEPARTMENTS_IMPLEMENTATION.md) | Áreas y cargos por empresa |
| [docs/TENANT_MULTI_SELECTOR.md](docs/TENANT_MULTI_SELECTOR.md) | Selector multi-empresa |
| [docs/sprintfix/MAPEO-CARGA-MASIVA.md](docs/sprintfix/MAPEO-CARGA-MASIVA.md) | Formato de carga masiva |
| [signer/README.md](signer/README.md) | Sidecar de firma digital |

---

## 📄 Licencia

Proyecto privado y confidencial. © 2024–2026 MiBoleta
