# MiBoleta - Sistema de Gestión Documental

Sistema multi-tenant de gestión de documentos, vacaciones y boletas para empresas.

## 🚀 Quick Start

### Requisitos
- Node.js 18+
- Docker y Docker Compose
- Git

### Instalación Rápida

```bash
# Clonar el repositorio
git clone https://github.com/jorgevasquezutec/miboleta.git
cd miboleta

# Instalar dependencias frontend
npm install

# Levantar backend (Laravel + MySQL + Redis + Nginx)
docker compose up -d

# Ejecutar migraciones y seeds
npm run laravel:fresh

# Iniciar frontend en modo desarrollo
npm run dev
```

### Accesos por Defecto

| Servicio | URL | Puerto |
|----------|-----|--------|
| **Frontend** | http://localhost:5173 | 5173 |
| **Backend API** | http://localhost/api | 80 |
| **Adminer (DB)** | http://localhost:8080 | 8080 |
| **MySQL** | localhost:3307 | 3307 |
| **Redis** | localhost:6379 | 6379 |
| **Reverb (WebSocket)** | localhost:8085 | 8085 |

### Usuarios de Prueba

| Email | Rol | Password |
|-------|-----|----------|
| platform@miboleta.com | Platform Admin | password |
| admin@corporacionabc.com | Tenant Admin | password |
| jorge.perez@corporacionabc.com | Employee | password |

---

## 📦 Scripts NPM

### Desarrollo

```bash
npm run dev           # Desarrollo local (localhost)
npm run dev:mobile    # Desarrollo para móvil (auto-detecta IP de red)
npm run dev:local     # Restaurar configuración localhost
```

### Docker

```bash
npm run docker:up      # Levantar contenedores
npm run docker:down    # Parar contenedores
npm run docker:logs    # Ver logs en tiempo real
npm run docker:restart # Reiniciar contenedores
```

### Laravel

```bash
npm run laravel:migrate  # Ejecutar migraciones
npm run laravel:fresh    # Reset DB + seeds
npm run laravel:shell    # Acceder al contenedor PHP
npm run laravel:cache    # Cachear config y rutas
```

### Build

```bash
npm run build         # Compilar frontend para producción
npm run build:copy    # Build + copiar a backend/public
```

---

## 📱 Desarrollo Móvil

Para probar la aplicación desde tu celular (en la misma red WiFi):

```bash
npm run dev:mobile
```

Este comando automáticamente:
1. ✅ Detecta tu IP local actual
2. ✅ Actualiza `.env.local` (frontend)
3. ✅ Actualiza `backend/.env` (backend)
4. ✅ Inicia Vite con `--host`

**Importante:** Después de cambiar, reinicia Docker:
```bash
docker compose restart
```

Para volver a localhost:
```bash
npm run dev:local
docker compose restart
```

---

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND                              │
│  React 18 + TypeScript + Vite + Tailwind CSS                │
│  Puerto: 5173 (desarrollo)                                   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       NGINX (proxy)                          │
│  Puerto: 80 (HTTP) / 443 (HTTPS)                            │
│  - Sirve frontend compilado                                  │
│  - Proxy /api -> PHP-FPM                                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      LARAVEL (API)                           │
│  PHP 8.3 + Laravel 11 + Sanctum                             │
│  Puerto: 9000 (PHP-FPM interno)                             │
└─────────────────────────────────────────────────────────────┘
          │              │              │
          ▼              ▼              ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│   MySQL 8   │  │    Redis    │  │   Reverb    │
│ Puerto:3307 │  │ Puerto:6379 │  │ Puerto:8085 │
└─────────────┘  └─────────────┘  └─────────────┘
```

### Tecnologías

**Frontend:**
- React 18 + TypeScript
- Vite (build tool)
- Tailwind CSS v4
- shadcn/ui (componentes)
- Zustand (estado)
- React Router v7
- React Query
- Recharts (gráficos)

**Backend:**
- Laravel 11 + PHP 8.3
- Laravel Sanctum (autenticación)
- Laravel Reverb (WebSockets)
- Laravel Horizon (queues)
- MySQL 8 + Redis

**Infraestructura:**
- Docker Compose (desarrollo)
- Docker Swarm (producción)
- GitHub Actions (CI/CD)
- Nginx (reverse proxy)

---

## 📁 Estructura del Proyecto

```
miboleta/
├── src/                          # Frontend React
│   ├── application/              # Casos de uso
│   ├── domain/                   # Entidades y modelos
│   ├── infrastructure/           # HTTP, repositories
│   └── presentation/             # UI
│       ├── components/           # Componentes reutilizables
│       ├── pages/                # Páginas por rol
│       │   ├── admin/            # Páginas de administrador
│       │   ├── employee/         # Páginas de empleado
│       │   └── shared/           # Páginas compartidas
│       ├── hooks/                # Custom hooks
│       ├── stores/               # Zustand stores
│       └── routes/               # React Router
├── backend/                      # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/     # Controladores API
│   │   ├── Models/               # Eloquent models
│   │   └── Services/             # Lógica de negocio
│   ├── database/
│   │   ├── migrations/           # Migraciones DB
│   │   └── seeders/              # Datos de prueba
│   └── routes/
│       └── api.php               # Rutas API
├── docker/                       # Configuración Docker
│   ├── nginx/                    # Nginx config
│   └── php/                      # PHP-FPM config
├── scripts/                      # Scripts de utilidad
│   ├── dev-mobile.sh             # Config desarrollo móvil
│   └── dev-local.sh              # Config desarrollo local
├── .github/workflows/            # GitHub Actions
│   └── docker-build-deploy.yml   # CI/CD pipeline
├── docker-compose.yml            # Docker desarrollo
└── docker-stack.yml              # Docker Swarm producción
```

---

## 🔐 Sistema de Autenticación

- **Método:** Laravel Sanctum con cookies HttpOnly
- **Access Token:** 1 hora (renovación automática)
- **Refresh Token:** 30 días
- **Multi-tenant:** Usuarios pueden pertenecer a múltiples empresas

Ver [AUTH_SYSTEM.md](docs/AUTH_SYSTEM.md) para documentación detallada.

---

## 👥 Roles de Usuario

| Rol | Permisos |
|-----|----------|
| **Platform Admin** | Gestión de toda la plataforma y empresas |
| **Tenant Admin** | Gestión de usuarios y documentos de su empresa |
| **Supervisor** | Aprobación de vacaciones de su equipo |
| **Employee** | Visualización de documentos y solicitud de vacaciones |

---

## 🚀 Deploy a Producción (Docker Swarm)

El proyecto incluye CI/CD con GitHub Actions que despliega automáticamente a Docker Swarm.

### Secrets necesarios en GitHub:

```
# Conexión VPN
VPN_HOST, VPN_PORT, VPN_USER, VPN_PASS, VPN_CERT

# Conexión SSH al servidor
SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS

# Frontend Vite
VITE_REVERB_APP_KEY, VITE_REVERB_HOST
```

### Trigger del deploy:
- ✅ Automático en push a `main`
- ✅ Manual desde GitHub Actions

Ver [docker-stack.yml](docker-stack.yml) para la configuración de Swarm.

---

## 📱 Diseño Responsive

La aplicación está optimizada para:
- 📱 Móvil (< 640px)
- 📱 Tablet (640px - 1024px)
- 💻 Desktop (> 1024px)

Características responsive:
- Sidebar colapsable en móvil
- Títulos y subtítulos adaptativos
- PDFViewer con zoom automático (50% en móvil)
- Botones y controles compactos en móvil
- Scroll horizontal para tablas

---

## 📚 Documentación Adicional

| Documento | Descripción |
|-----------|-------------|
| [AUTH_SYSTEM.md](docs/AUTH_SYSTEM.md) | Sistema de autenticación |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Guía de desarrollo |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Guía de deployment |
| [TABLE_PAGINATION.md](TABLE_PAGINATION.md) | Sistema de paginación |

---

## 🛠️ Troubleshooting

### Error: "Network Error" al hacer login
- Verifica que Docker esté corriendo: `docker compose ps`
- Revisa los logs: `npm run docker:logs`

### Error: Puerto en uso
- Mata procesos en el puerto: `lsof -i :5173` / `kill -9 <PID>`
- O usa otro puerto: `npx vite --port 3000 --host`

### Móvil no conecta
- Verifica que estés en la misma red WiFi
- Ejecuta `npm run dev:mobile` para configurar la IP
- Reinicia Docker: `docker compose restart`

---

## 📄 Licencia

Este proyecto es privado y confidencial.

© 2024-2025 MiBoleta