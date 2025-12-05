# MiBoleta - Sistema de Gestión Documental

Sistema multi-tenant de gestión de documentos para empresas. Basado en el diseño original de Figma disponible en https://www.figma.com/design/7tyJxcpdePhaIBf7fxyVwF/Create-Mockups-for-MiBoleta.

## 🚀 Quick Start (Monolito Laravel + React + Docker)

### Opción 1: Script Automático (Recomendado)

```bash
./setup.sh
```

Este script:
- ✅ Instala Laravel en `backend/`
- ✅ Configura `.env` con credenciales Docker
- ✅ Compila el frontend React
- ✅ Construye y levanta los contenedores (PHP-FPM + Nginx + MySQL)
- ✅ Ejecuta migraciones

### Opción 2: Paso a Paso Manual

Ver [SETUP.md](SETUP.md) para instalación detallada paso a paso.

### Accesos

- **Web**: http://localhost:8080
- **MySQL**: localhost:3307
  - Usuario: `miboleta_user`
  - Password: `secret123`
  - Database: `miboleta`

## 📦 Scripts NPM Disponibles

### Desarrollo con Hot Reload (Recomendado)
```bash
npm run dev              # Vite dev server con HMR en :5173
npm run docker:dev:up    # Levantar solo Laravel + MySQL
npm run dev:fullstack    # Levantar backend Y frontend (todo en uno)
```
👉 **Ver [DEVELOPMENT.md](DEVELOPMENT.md) para desarrollo con hot reload completo**

### Desarrollo Frontend
```bash
npm run dev              # Vite dev server (desarrollo frontend solo)
npm run build            # Compilar frontend para producción
```

### Docker y Backend (Producción)
```bash
npm run docker:build     # Construir imágenes Docker
npm run docker:up        # Levantar contenedores
npm run docker:down      # Parar contenedores
npm run docker:logs      # Ver logs en tiempo real
npm run docker:restart   # Reiniciar contenedores
```

### Laravel Commands
```bash
npm run laravel:migrate       # Ejecutar migraciones (producción)
npm run laravel:fresh         # Reset DB y ejecutar seeds
npm run laravel:shell         # Acceder al contenedor PHP
npm run laravel:cache         # Cachear config y rutas de Laravel
npm run laravel:dev:migrate   # Ejecutar migraciones (desarrollo)
npm run laravel:dev:shell     # Shell en contenedor dev
```

### Setup
```bash
npm run setup:full       # Instalación completa automatizada
```

## Arquitectura

Este proyecto es un **monolito Laravel** que sirve el frontend React compilado:

- **Frontend**: React + TypeScript (compilado a `backend/public/`)
- **Backend**: Laravel API (rutas bajo `/api`)
- **Base de datos**: MySQL 8.0
- **Servidor web**: Nginx + PHP-FPM
- **Orquestación**: Docker Compose

**Importante**: NO necesitas PHP ni Composer instalados localmente. Todo corre en Docker.

### ¿Por qué no teníamos routing antes?

El frontend original usaba **estado local** (`currentView`) en lugar de rutas de navegador:

❌ **Sin routing de URL**:
- No hay URLs directas (ej. `/users`, `/documents/123`)
- No se puede compartir enlaces a vistas específicas
- El botón "atrás" del navegador no funciona como esperado
- Al recargar la página, pierdes el estado

✅ **Con routing (React Router)** — ya instalado:
- URLs semánticas: `/users`, `/documents/123`
- Enlaces compartibles
- Navegación del navegador funciona
- Estado se mantiene en la URL
- Nginx ya configurado con fallback a `index.html` para SPA

## Características

- Sistema multi-tenant con aislamiento de datos por empresa
- Gestión de usuarios con roles (Platform Admin, Tenant Admin, Manager, Employee)
- Gestión de documentos con búsqueda, filtros y paginación
- Dashboard con métricas y estadísticas
- Visor de documentos integrado
- Interfaz responsive construida con React y Tailwind CSS
- Gestión de estado con Zustand
- Routing con React Router v7
- Componentes UI de shadcn/ui

## Roles de Usuario

- **Platform Admin**: Administrador de la plataforma, puede gestionar todas las empresas y usuarios
- **Tenant Admin**: Administrador de empresa, puede gestionar usuarios de su empresa
- **Manager**: Gestor con permisos intermedios
- **Employee**: Usuario final con acceso a documentos

## Instalación

1. Instalar dependencias:
```bash
npm install
```

2. Iniciar el servidor de desarrollo:
```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:3000`

## Scripts Disponibles

- `npm run dev` - Inicia el servidor de desarrollo
- `npm run build` - Construye la aplicación para producción
- `npm run deploy` - Despliega la aplicación a GitHub Pages

## Deployment

La aplicación está configurada para desplegarse en GitHub Pages. Ver [DEPLOYMENT.md](DEPLOYMENT.md) para instrucciones detalladas.

### Deployment Automático

Cada push a la rama `main` despliega automáticamente la aplicación usando GitHub Actions.

### Deployment Manual

```bash
npm run deploy
```

## Tecnologías

- **React 18** - Biblioteca UI
- **TypeScript** - Tipado estático
- **Vite** - Build tool y dev server
- **React Router v7** - Routing
- **Zustand** - Gestión de estado
- **Tailwind CSS** - Estilos
- **shadcn/ui** - Componentes UI
- **Lucide React** - Iconos
- **Recharts** - Gráficos
- **Sonner** - Notificaciones toast

## Estructura del Proyecto

```
src/
├── components/
│   ├── ui/              # Componentes UI de shadcn
│   └── views/           # Vistas principales
├── contexts/            # Contextos de React
├── hooks/               # Custom hooks
├── services/            # API mock y servicios
├── stores/              # Stores de Zustand
└── utils/               # Utilidades
```

## Documentación Adicional

- [Guía de Paginación](TABLE_PAGINATION.md) - Documentación del sistema de paginación
- [Guía de Migración a Zustand](ZUSTAND_MIGRATION_GUIDE.md) - Migración de Context a Zustand
- [Guía de Deployment](DEPLOYMENT.md) - Instrucciones de deployment a GitHub Pages

## Desarrollo

### Mock API

El proyecto utiliza un sistema de API mock con `setTimeout` para simular latencia de red (300-800ms). Ver `src/services/mockApi.ts`.

### Stores de Zustand

- `authStore` - Autenticación y usuario actual
- `usersStore` - Gestión de usuarios
- `tenantsStore` - Gestión de empresas/tenants
- `documentsStore` - Gestión de documentos con paginación

## ✅ Progreso del Proyecto

### Backend - Completado
- ✅ **Módulo 0**: Configuración de base de datos, migraciones y seeders
- ✅ **Módulo 1**: Sistema de autenticación con Laravel Sanctum
- ✅ **Módulo 2**: Sistema multi-tenant con pivot tables
- ✅ **Seguridad**: Sistema completo de HttpOnly cookies con access + refresh tokens
  - Access token: 1 hora (HttpOnly, SameSite=Lax)
  - Refresh token: 30 días (HttpOnly, SameSite=Strict)
  - Middleware para inyección automática de tokens desde cookies
  - Endpoint `/api/refresh` para renovación automática de tokens
  - Revocación de tokens en base de datos
  - Auditoría con IP y User Agent

### Frontend - Completado
- ✅ **apiClient.ts**: Cliente Axios configurado con:
  - `withCredentials: true` para manejo automático de cookies
  - Interceptor de request para X-Tenant-ID header
  - Interceptor de response con auto-refresh de tokens
  - Cola de requests durante refresh para evitar race conditions
  - Manejo completo de errores HTTP (401, 403, 404, 422, 500)
- ✅ **authStore**: Actualizado para trabajar sin token manual (cookies HttpOnly)
- ✅ **UserRepository**: Tipos actualizados para respuestas del backend real
- ✅ **useAuth hook**: Lógica de autenticación basada en usuario (no en token)

### Documentación
- ✅ **AUTH_SYSTEM.md**: Documentación completa del sistema de autenticación
  - Diagramas de arquitectura (backend + frontend)
  - Flujos completos: Login, Authenticated Request, Token Refresh, Logout
  - Guía de testing con curl
  - Configuración y troubleshooting
  - Comandos de mantenimiento

### Próximos Pasos

#### Inmediato: Testing
1. Probar sistema de autenticación end-to-end
2. Verificar cookies en DevTools del navegador
3. Probar auto-refresh de tokens (esperar 1h o modificar duración)
4. Probar multi-tenant switching
5. Probar logout y limpieza de cookies

#### Módulo 3: Gestión de Usuarios (CRUD Completo)
- [ ] Backend: Controlador CRUD con endpoints REST
  - GET /api/users (list con paginación y filtros)
  - POST /api/users (crear con validación)
  - GET /api/users/{id} (detalle)
  - PUT /api/users/{id} (actualizar)
  - DELETE /api/users/{id} (soft delete)
  - POST /api/users/{id}/assign-role
  - POST /api/users/{id}/assign-tenant
- [ ] Frontend: Actualizar UsersView para usar API real
- [ ] Implementar filtros y búsqueda server-side
- [ ] Implementar paginación server-side

#### Módulos Pendientes
- [ ] **Módulo 4**: Gestión de Documentos (CRUD + Upload)
- [ ] **Módulo 5**: Sistema de Vacaciones
- [ ] **Módulo 6**: Notificaciones
- [ ] **Módulo 7**: Reportes y Analytics
- [ ] **Módulo 8**: Testing automatizado

### Migración a API Real

Para migrar a una API real, reemplaza las llamadas en `src/services/mockApi.ts` con llamadas `fetch` reales. Los stores ya están preparados para trabajar con APIs reales.

## Licencia

Este proyecto es privado y confidencial.


platform@miboleta.com - Platform Administrator
carlos@empresa1.com - Tenant Administrator
maria@empresa1.com - Employee