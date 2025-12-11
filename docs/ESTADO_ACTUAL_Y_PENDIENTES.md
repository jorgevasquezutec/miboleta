# Estado Actual del Sistema MiBoleta

**Fecha de análisis:** 1 de diciembre de 2025  
**Proyecto:** MiBoleta - Sistema de Gestión Documental + Módulo de Vacaciones

---

## RESUMEN EJECUTIVO

El sistema MiBoleta actualmente tiene implementado aproximadamente el **25-30%** del alcance total planteado. La arquitectura base está establecida pero faltan implementar las funcionalidades core del negocio del sistema de documentos + el módulo de vacaciones completo.

---

## LO QUE YA TENEMOS IMPLEMENTADO

### 1. INFRAESTRUCTURA BASE

**Backend Laravel**
- Laravel 12.x instalado y configurado
- Docker Compose funcionando (PHP-FPM + Nginx + MySQL)
- Estructura de carpetas Laravel estándar
- Sistema de migraciones base (users, cache, jobs)
- Scripts de deployment configurados
- Entorno de desarrollo con hot-reload

**Frontend React**
- React 18.3.1 con TypeScript
- Vite 6.3.5 como bundler
- Clean Architecture implementada (core, presentation, infrastructure)
- Tailwind CSS + shadcn/ui components
- React Router v7 configurado
- Zustand para state management

### 2. ARQUITECTURA Y ESTRUCTURA

**Clean Architecture (Frontend)**
```
src/
├── core/
│   ├── domain/          (Entidades y casos de uso)
│   └── application/     (Servicios y DTOs)
├── infrastructure/
│   ├── http/           (API clients)
│   ├── persistence/    (Repositories)
│   └── storage/        (LocalStorage)
├── presentation/
│   ├── pages/          (Vistas principales)
│   ├── components/     (Componentes React)
│   ├── stores/         (Zustand stores)
│   └── routes/         (Configuración de rutas)
└── shared/             (Utilidades compartidas)
```

**Stores de Zustand implementados:**
- `authStore` - Autenticación básica
- `usersStore` - Gestión de usuarios (CRUD mock)
- `tenantsStore` - Gestión de organizaciones (CRUD mock)
- `documentsStore` - Gestión de documentos (CRUD mock)

### 3. COMPONENTES UI

**Páginas implementadas (Frontend):**
- Login page
- Admin Dashboard (página base)
- Employee Dashboard (página base)
- Users Management Page (interfaz)
- Tenants Management Page (interfaz)
- Settings Page (interfaz)
- User Profile View

**Componentes shadcn/ui:**
- Todos los componentes base de shadcn/ui instalados
- Navbar/Layout básico
- Cards, Buttons, Forms, Tables, Dialogs
- Estadísticas y gráficos con Recharts

### 4. SISTEMA DE AUTENTICACIÓN (MOCK)

**Implementado:**
- Login básico con mock API
- Roles definidos: platform-admin, tenant-admin, manager, employee
- Rutas protegidas por rol
- Store de autenticación con Zustand

**Estado:** Frontend mock, sin backend real

### 5. MULTI-TENANCY (MOCK)

**Implementado:**
- Concepto de Tenant/Organización
- Separación de datos por organización en frontend
- CRUD de tenants en interfaz
- Usuarios asociados a tenants

**Estado:** Solo interfaz, sin implementación backend

---

## LO QUE FALTA POR IMPLEMENTAR

### SPRINT 1: Gestión de Empleados (80% PENDIENTE)

**Pendiente:**
- [ ] API Backend real para gestión de empleados
- [ ] Modelo `Employee` en Laravel
- [ ] Migraciones de base de datos
- [ ] Controllers de API
- [ ] Validaciones backend
- [ ] Sistema de carpetas personales
- [ ] Documentos huérfanos (tabla + lógica)
- [ ] Asociación automática de documentos
- [ ] Seeders con datos de prueba

**Implementado:**
- [x] Interfaz de gestión de empleados (frontend)
- [x] CRUD mock en Zustand

**Esfuerzo restante:** 60-70 horas

---

### SPRINT 2: Procesamiento de Documentos (95% PENDIENTE)

**Pendiente:**
- [ ] Carga de archivos ZIP
- [ ] Procesamiento asíncrono con Jobs
- [ ] Laravel Horizon instalación y configuración
- [ ] Sistema de colas (Redis/Database)
- [ ] Job: ProcessZipFile
- [ ] Job: ValidateEmployees
- [ ] Job: DistributeDocuments
- [ ] Job: SendNotificationEmail
- [ ] Gestión de documentos huérfanos
- [ ] Sistema de notificaciones por email
- [ ] Configuración SMTP/Mailtrap
- [ ] Templates de email
- [ ] Solicitud de firma digital
- [ ] Reportes de procesamiento
- [ ] Dashboard de métricas

**Implementado:**
- [x] Migración base de jobs (Laravel default)

**Esfuerzo restante:** 75-80 horas

---

### SPRINT 3: Portal Empleado y Notificaciones (90% PENDIENTE)

**Pendiente:**
- [ ] Portal empleado funcional (backend)
- [ ] Autenticación real de empleados
- [ ] API de documentos por empleado
- [ ] Visor PDF integrado
- [ ] Descarga de documentos
- [ ] Sistema de notificaciones por email (producción)
- [ ] Laravel Reverb instalación
- [ ] WebSockets en tiempo real
- [ ] Centro de notificaciones
- [ ] Notificaciones push
- [ ] Badge de contador
- [ ] Preferencias de notificaciones

**Implementado:**
- [x] Vista de dashboard empleado (mock)
- [x] Estructura de componentes

**Esfuerzo restante:** 70-75 horas

---

### SPRINT 4: Firma Digital y Finalización (100% PENDIENTE)

**Pendiente:**
- [ ] Sistema de firma digital
- [ ] Validación legal de firmas
- [ ] Registro de timestamp, IP, geolocalización
- [ ] Certificado de firma
- [ ] Auditoría completa
- [ ] Logs de acceso
- [ ] Historial de cambios
- [ ] Dashboard de métricas
- [ ] Búsqueda avanzada
- [ ] Filtros combinables
- [ ] Exportación de reportes
- [ ] Optimizaciones de performance
- [ ] Security hardening
- [ ] Monitoring y logs
- [ ] Documentación técnica
- [ ] Manual de usuario
- [ ] Scripts de deployment
- [ ] Testing de carga

**Implementado:**
- Nada

**Esfuerzo restante:** 80 horas

---

## ANÁLISIS DETALLADO POR COMPONENTE

### BACKEND (Laravel)

**Implementado:**
```
✓ Laravel 12 instalado
✓ Docker configurado
✓ Migraciones base (users, cache, jobs)
✓ Estructura de carpetas estándar
✓ Config de queue, mail, cache
```

**Pendiente:**
```
✗ Modelos de negocio (Employee, Document, Tenant, etc.)
✗ Controllers de API
✗ Middleware de multi-tenancy
✗ Sistema de autenticación API (Sanctum/JWT)
✗ Políticas de acceso
✗ Jobs asíncronos
✗ Horizon
✗ Reverb (WebSockets)
✗ Sistema de notificaciones
✗ Almacenamiento de archivos
✗ Procesamiento de PDFs
✗ Sistema de firma digital
✗ Auditoría y logging
✗ Seeders con datos reales
✗ Tests unitarios e integración
```

**Porcentaje completado:** ~10%

---

### FRONTEND (React)

**Implementado:**
```
✓ Arquitectura Clean establecida
✓ Zustand stores (mock)
✓ React Router configurado
✓ Componentes UI (shadcn)
✓ Páginas base creadas
✓ Layout y Navbar
✓ Mock API
✓ Gestión de estado
✓ Formularios básicos
```

**Pendiente:**
```
✗ Integración con API real
✗ Autenticación real (tokens)
✗ Upload de archivos
✗ Visor PDF
✗ WebSocket client
✗ Notificaciones en tiempo real
✗ Sistema de firma digital (UI)
✗ Búsqueda avanzada
✗ Exportación de reportes
✗ Optimizaciones de performance
✗ Testing (Jest/Testing Library)
✗ Manejo de errores robusto
✗ Loading states mejorados
✗ Validaciones completas
```

**Porcentaje completado:** ~35%

---

### DEPENDENCIAS FALTANTES

**Backend Laravel:**
- [ ] Laravel Horizon (`laravel/horizon`)
- [ ] Laravel Reverb (`laravel/reverb`)
- [ ] Laravel Sanctum o Passport (autenticación API)
- [ ] Spatie Laravel Permission (roles y permisos)
- [ ] PDF processing library (mpdf, dompdf, etc.)
- [ ] Storage driver (AWS S3, DigitalOcean Spaces)
- [ ] Redis (para queues y cache)

**Frontend React:**
- [ ] React PDF viewer (`@react-pdf/renderer` o similar)
- [ ] WebSocket client (Laravel Echo)
- [ ] Pusher o Socket.io client
- [ ] File upload library (react-dropzone)
- [ ] Excel export (xlsx, exceljs)
- [ ] Date picker mejorado
- [ ] Rich text editor (si es necesario)

---

## COMPARACIÓN: PLANTEADO vs IMPLEMENTADO

| Componente | Planteado | Implementado | Pendiente |
|------------|-----------|--------------|-----------|
| **Backend Laravel** | 100% | 10% | 90% |
| **Frontend React** | 100% | 35% | 65% |
| **Base de Datos** | 100% | 5% | 95% |
| **Autenticación** | 100% | 20% | 80% |
| **Multi-tenancy** | 100% | 15% | 85% |
| **Gestión Empleados** | 100% | 20% | 80% |
| **Procesamiento Docs** | 100% | 0% | 100% |
| **Notificaciones Email** | 100% | 0% | 100% |
| **WebSockets** | 100% | 0% | 100% |
| **Portal Empleado** | 100% | 10% | 90% |
| **Firma Digital** | 100% | 0% | 100% |
| **Auditoría** | 100% | 0% | 100% |
| **Búsqueda Avanzada** | 100% | 0% | 100% |
| **Reportería** | 100% | 0% | 100% |
| **Testing** | 100% | 0% | 100% |
| **Documentación** | 100% | 15% | 85% |

**Porcentaje total completado:** ~20-25%

---

## DEUDA TÉCNICA IDENTIFICADA

### 1. Autenticación
- Actualmente usa mock API
- No hay tokens de autenticación
- No hay refresh de sesión
- No hay protección CSRF

### 2. Base de Datos
- Solo tiene migraciones default de Laravel
- Falta toda la estructura del negocio
- No hay seeders funcionales
- No hay relaciones establecidas

### 3. API Backend
- No hay endpoints reales
- Frontend usa datos mock en localStorage
- No hay validaciones backend
- No hay manejo de errores estructurado

### 4. Almacenamiento
- No hay sistema de archivos configurado
- Falta integración con storage (S3, local, etc.)
- No hay gestión de carpetas por empleado

### 5. Performance
- No hay cache implementado
- No hay optimizaciones de queries
- No hay lazy loading
- No hay paginación real

### 6. Seguridad
- No hay rate limiting
- No hay validación de permisos real
- No hay encriptación de datos sensibles
- No hay auditoría de accesos

---
---

## TODO: SISTEMA DE GESTIÓN DOCUMENTAL (PENDIENTE)

### SPRINT 0: AUTENTICACIÓN Y AUTORIZACIÓN (PREREQUISITO CRÍTICO)

#### Backend - Laravel Sanctum Setup
- [ ] Instalar Laravel Sanctum: `composer require laravel/sanctum`
- [ ] Publicar configuración: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
- [ ] Ejecutar migraciones de Sanctum
- [ ] Configurar CORS en `config/cors.php`:
  - `supports_credentials => true`
  - `allowed_origins => [env('FRONTEND_URL')]`
- [ ] Configurar Sanctum en `config/sanctum.php`:
  - Agregar dominios stateful (localhost:5173, localhost:3000)
  - Configurar middleware
- [ ] Agregar `HasApiTokens` trait al modelo User

#### Backend - Sistema de Roles
- [ ] Crear migración `create_roles_table`:
  - id, name (root, admin, client), description, permissions (json), guard_name
- [ ] Crear migración `create_user_roles_table` (pivot):
  - id, user_id, role_id, tenant_id (para scope por organización)
- [ ] Crear modelo `Role` con relaciones
- [ ] Crear modelo `UserRole` (pivot)
- [ ] Migración `add_auth_fields_to_users_table`:
  - tenant_id (nullable para root)
#### Frontend
- [ ] Conectar `usersStore` con API real usando apiClient
- [ ] Conectar `tenantsStore` con API real usando apiClient
- [ ] Implementar validación de DNI en formulario
- [ ] Búsqueda en tiempo real de empleados
- [ ] Filtros por estado, departamento, tenant (si es root)
- [ ] Root ve selector de tenant, admin NO lo ve
- [ ] Paginación con backend
- [ ] Ordenamiento por columnas
- [ ] Manejo de errores de API (401, 403, 422, 500)
- [ ] Loading states en todas las operaciones
- [ ] Confirmación de eliminación con modal
- [ ] Vista de documentos asociados por empleado
- [ ] Deshabilitar acciones según rol (client no puede crear/editar)
#### Backend - Controllers de Autenticación
- [ ] Crear `AuthController`:
  - `login()`: validar credenciales, crear sesión, NO devolver token (usa cookies)
  - `logout()`: invalidar sesión, limpiar cookies
  - `me()`: obtener usuario actual con roles
  - `refresh()`: refrescar CSRF token
- [ ] Crear `RegisterController` (si es necesario):
  - Validaciones de email único
  - Asignación automática de rol client
  - Verificación de email (opcional)
- [ ] Rate limiting en login: máximo 5 intentos por minuto
- [ ] Logging de intentos de login fallidos

#### Backend - Middleware y Políticas
- [ ] Crear middleware `CheckRole`:
  - Verificar si usuario tiene rol(es) requerido(s)
  - Considerar scope de tenant
- [ ] Crear middleware `CheckPermission`:
  - Verificar permisos específicos en JSON
- [ ] Crear middleware `TenantScope`:
  - Filtrar queries automáticamente por tenant_id
  - Excepto para rol root
- [ ] Policy `TenantPolicy`: solo root y admin del tenant pueden gestionar
- [ ] Policy `EmployeePolicy`: root, admin ven todos; client solo sus docs
- [ ] Policy `DocumentPolicy`: verificar acceso por empleado

#### Backend - API Routes
- [ ] Rutas públicas:
  - POST `/api/auth/login`
  - POST `/api/auth/register` (opcional)
  - GET `/sanctum/csrf-cookie` (necesario para SPA)
- [ ] Rutas protegidas `auth:sanctum`:
  - GET `/api/auth/me`
  - POST `/api/auth/logout`
  - POST `/api/auth/refresh`
- [ ] Rutas para root: `role:root`
  - Gestión de tenants
  - Gestión de usuarios de cualquier tenant
  - Acceso a todas las métricas
- [ ] Rutas para admin: `role:root,admin`
  - Gestión de empleados de su tenant
  - Carga de documentos
  - Reportes de su organización
- [ ] Rutas para client: `role:root,admin,client`
  - Ver sus propios documentos
  - Descargar documentos
  - Firmar documentos

#### Frontend - Axios Client con Cookies
- [ ] Crear `infrastructure/http/apiClient.ts`:
  - Configurar axios con `withCredentials: true`
  - baseURL desde variable de entorno
  - Interceptor de respuesta para 401 (redirect a login)
  - Interceptor de respuesta para 403 (mostrar error)
- [ ] Configurar variables de entorno:
  - `VITE_API_URL=http://localhost:8080`
  - `VITE_API_BASE_URL=http://localhost:8080/api`

#### Frontend - Auth Store Actualizado
- [ ] Actualizar `authStore.ts`:
  - Eliminar campo `token` (ya no se usa)
  - Método `login()`: GET csrf-cookie + POST login con cookies
  - Método `logout()`: POST logout + limpiar estado
  - Método `me()`: GET usuario actual al recargar app
  - Método `checkAuth()`: verificar si hay sesión activa
  - NO usar localStorage para token (solo user info)
- [ ] Llamar `checkAuth()` al iniciar la app
- [ ] Interceptor para renovar CSRF si es necesario

#### Frontend - Guards y Roles
- [ ] Actualizar componente `ProtectedRoute`:
  - Verificar roles del usuario
  - Verificar permisos específicos si es necesario
- [ ] Crear helper `usePermission(permission)` hook
- [ ] Crear helper `useRole(role)` hook
- [ ] **[EN ANÁLISIS]** Implementar cambio de contexto para usuarios admin+client:
  - [ ] Crear state `currentContext` en authStore ('admin' | 'client')
  - [ ] Componente `RoleSwitch` para cambiar entre admin/client
  - [ ] Actualizar navegación según rol Y contexto:
    - Root: ve todo
    - Admin (contexto admin): gestiona organización
    - Client (contexto client): solo sus documentos
    - Admin + Client: puede cambiar entre contextos
  - [ ] Mostrar indicador visual del contexto actual
  - [ ] Guardar contexto en localStorage para persistencia
  - [ ] Decidir si es necesario o simplificar mostrando todo junto

#### Configuración de Variables de Entorno
- [ ] Backend `.env`:
  - `SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000`
  - `SESSION_DRIVER=cookie`
  - `SESSION_DOMAIN=localhost`
  - `FRONTEND_URL=http://localhost:5173`
- [ ] Frontend `.env`:
  - `VITE_API_URL=http://localhost:8080`
  - `VITE_API_BASE_URL=http://localhost:8080/api`

#### Testing de Autenticación
- [ ] Test: login exitoso devuelve usuario y crea sesión
- [ ] Test: login fallido devuelve 401
- [ ] Test: logout invalida sesión
- [ ] Test: ruta protegida sin auth devuelve 401
- [ ] Test: ruta con rol incorrecto devuelve 403
- [ ] Test: root puede acceder a todo
- [ ] Test: admin solo ve su tenant
- [ ] Test: client solo ve sus documentos
- [ ] Test: rate limiting en login funciona

---

### SPRINT 1: GESTIÓN DE EMPLEADOS

#### Backend
- [ ] Crear modelo `Employee` con campos: dni, nombre, apellido, email, teléfono, departamento, cargo, fecha_ingreso, status
- [ ] Crear modelo `Tenant` (Organización) con campos: nombre, ruc, dirección, logo, color_principal, status
- [ ] Migración tabla `employees` con relación a `tenants`
- [ ] Migración tabla `tenants`
- [ ] Migración tabla `employee_folders` (estructura de carpetas)
- [ ] Migración tabla `orphaned_documents` (documentos sin empleado)
- [ ] Controller `EmployeeController` con CRUD completo
- [ ] Controller `TenantController` con CRUD completo
- [ ] Validaciones: DNI único por tenant, email único, RUC válido
- [ ] API endpoints: GET/POST/PUT/DELETE `/api/employees` (protegido con role:root,admin)
- [ ] API endpoints: GET/POST/PUT/DELETE `/api/tenants` (protegido con role:root)
- [ ] Aplicar TenantScope middleware en queries de empleados
- [ ] Políticas de acceso: root ve todo, admin solo su tenant
- [ ] Seeder con empleados de prueba
- [ ] Seeder con tenants de prueba
- [ ] Crear carpetas automáticamente al crear empleado
- [ ] Detectar documentos huérfanos al crear empleado
- [ ] Asociar automáticamente documentos por DNI
- [ ] Notificar empleado si hay documentos asociados

#### Frontend
- [ ] Conectar `usersStore` con API real (eliminar mock)
- [ ] Conectar `tenantsStore` con API real (eliminar mock)
- [ ] Implementar validación de DNI en formulario
- [ ] Búsqueda en tiempo real de empleados
- [ ] Filtros por estado, departamento, tenant
- [ ] Paginación con backend
- [ ] Ordenamiento por columnas
- [ ] Manejo de errores de API
- [ ] Loading states en todas las operaciones
- [ ] Confirmación de eliminación con modal
- [ ] Vista de documentos asociados por empleado

---

### SPRINT 2: PROCESAMIENTO DE DOCUMENTOS

#### Backend - Carga y Procesamiento
- [ ] Instalar Laravel Horizon: `composer require laravel/horizon`
- [ ] Configurar Horizon (publicar assets, configurar workers)
- [ ] Configurar Redis como queue driver
- [ ] Crear modelo `Document` con campos: nombre, ruta, tipo, categoría, período, empleado_id, tenant_id, requiere_firma, estado_firma
- [ ] Crear modelo `ProcessingLog` para auditoría
- [ ] Migración tabla `documents`
- [ ] Migración tabla `processing_logs`
- [ ] Controller `DocumentController` para upload y gestión
- [ ] Endpoint POST `/api/documents/upload-zip` para carga masiva
- [ ] Validación de archivo ZIP (tamaño, formato)
- [ ] Configurar storage disk para documentos
- [ ] Job `ProcessZipFile`: extraer archivos del ZIP
- [ ] Job `ValidateEmployees`: verificar DNI en nombres de archivos
- [ ] Job `DistributeDocuments`: mover archivos a carpetas de empleados
- [ ] Job `CreateOrphanedDocuments`: guardar documentos sin empleado
- [ ] Job `SendNotificationEmail`: notificar a empleados
- [ ] Manejo de errores con reintentos (3 intentos)
- [ ] Logging detallado en cada paso
- [ ] Rollback automático en fallos críticos
- [ ] Dashboard de métricas en Horizon
- [ ] API endpoint GET `/api/processing/status` para progreso
- [ ] API endpoint GET `/api/orphaned-documents` lista de huérfanos

#### Backend - Notificaciones Email
- [ ] Configurar SMTP (Mailtrap para desarrollo, Gmail/SES para producción)
- [ ] Template email: documento nuevo disponible
- [ ] Template email: documento requiere firma
- [ ] Template email: errores de procesamiento (admin)
- [ ] Template email: resumen de procesamiento
- [ ] Job `SendMassNotification` para envío masivo
- [ ] Control de frecuencia (evitar spam)
- [ ] Tracking de emails enviados/rebotados
- [ ] Tabla `email_logs` para auditoría
- [ ] Reintento automático para emails fallidos

#### Frontend
- [ ] Componente drag & drop para subir ZIP
- [ ] Barra de progreso durante upload
- [ ] Vista previa del contenido del ZIP
- [ ] Selector de categoría de documento
- [ ] Selector de período (mes/año)
- [ ] Checkbox "Requiere firma digital"
- [ ] Modal de confirmación antes de procesar
- [ ] Dashboard de procesamiento en tiempo real
- [ ] Lista de empleados no encontrados
- [ ] Lista de documentos huérfanos
- [ ] Botón para asociar manualmente documentos huérfanos
- [ ] Métricas: procesados, errores, pendientes
- [ ] Descarga de reporte Excel
- [ ] Filtros por fecha de carga
- [ ] Historial de cargas

---

### SPRINT 3: PORTAL EMPLEADO Y NOTIFICACIONES

#### Backend - Portal Empleado
- [ ] Instalar Laravel Sanctum: `composer require laravel/sanctum`
- [ ] Configurar autenticación API con tokens
- [ ] Endpoint POST `/api/auth/login` para empleados
- [ ] Endpoint POST `/api/auth/logout`
- [ ] Endpoint GET `/api/auth/me` para obtener usuario actual
- [ ] Endpoint GET `/api/employee/documents` (mis documentos)
- [ ] Endpoint GET `/api/employee/documents/{id}` (ver documento)
- [ ] Endpoint GET `/api/employee/documents/{id}/download`
- [ ] Middleware de autenticación para rutas de empleado
- [ ] Política: empleado solo ve sus documentos
- [ ] Registro de auditoría al visualizar documento
- [ ] Tabla `document_views` para tracking
- [ ] Contador de documentos nuevos

#### Backend - WebSockets (Laravel Reverb)
- [ ] Instalar Laravel Reverb: `composer require laravel/reverb`
- [ ] Configurar Reverb (publicar config, ejecutar servidor)
- [ ] Crear evento `DocumentProcessed` (termina procesamiento)
- [ ] Crear evento `NewDocumentAvailable` (nuevo documento para empleado)
- [ ] Crear evento `DocumentSigned` (empleado firma)
- [ ] Crear evento `ProcessingError` (error crítico)
- [ ] Broadcast a canal privado por empleado
- [ ] Broadcast a canal de admin
- [ ] Configurar broadcasting routes
- [ ] Testing de eventos WebSocket

#### Backend - Notificaciones Internas
- [ ] Tabla `notifications` (Laravel default)
- [ ] Notificación: documento nuevo
- [ ] Notificación: documento requiere firma
- [ ] Notificación: procesamiento completado (admin)
- [ ] Notificación: error de procesamiento (admin)
- [ ] Endpoint GET `/api/notifications` (mis notificaciones)
- [ ] Endpoint PUT `/api/notifications/{id}/read`
- [ ] Endpoint DELETE `/api/notifications/{id}`
- [ ] Contador de no leídas

#### Frontend - Portal Empleado
- [ ] Página de login para empleados
- [ ] Integrar autenticación real con Sanctum
- [ ] Guardar token en localStorage/cookie segura
- [ ] Refresh automático de token
- [ ] Dashboard personal con resumen
- [ ] Lista de documentos con filtros (categoría, período)
- [ ] Indicador visual de documentos nuevos
- [ ] Búsqueda dentro de mis documentos
- [ ] Responsive perfecto para móvil
- [ ] Visor PDF integrado (react-pdf o iframe)
- [ ] Zoom in/out en visor
- [ ] Navegación entre páginas del PDF
- [ ] Botón de descarga
- [ ] Preview en lista sin abrir completo

#### Frontend - WebSocket y Notificaciones
- [ ] Instalar Laravel Echo: `npm install --save laravel-echo pusher-js`
- [ ] Configurar Echo client
- [ ] Conectar a Reverb server
- [ ] Suscribirse a canal privado del empleado
- [ ] Escuchar evento `NewDocumentAvailable`
- [ ] Escuchar evento `DocumentSigned`
- [ ] Mostrar toast notification en tiempo real
- [ ] Actualizar contador de documentos nuevos
- [ ] Actualizar lista sin recargar
- [ ] Manejo de reconexión automática
- [ ] Fallback a polling si WebSocket falla
- [ ] Centro de notificaciones en header
- [ ] Badge con contador de no leídas
- [ ] Lista de notificaciones con scroll
- [ ] Marcar como leído/no leído
- [ ] Eliminar notificaciones
- [ ] Link directo al documento desde notificación

---

### SPRINT 4: FIRMA DIGITAL Y FINALIZACIÓN

#### Backend - Firma Digital
- [ ] Tabla `document_signatures` con timestamp, IP, geolocalización, user_agent
- [ ] Endpoint POST `/api/documents/{id}/sign` (firmar documento)
- [ ] Endpoint GET `/api/documents/{id}/signature` (obtener firma)
- [ ] Endpoint GET `/api/documents/{id}/certificate` (descargar certificado)
- [ ] Validación: documento no firmado previamente
- [ ] Captura de IP y user agent
- [ ] Generación de hash del documento
- [ ] Generación de certificado PDF
- [ ] Evento `DocumentSigned` para notificaciones
- [ ] Notificar admin cuando empleado firma
- [ ] Dashboard admin: documentos pendientes de firma
- [ ] Estadísticas de firmas por período

#### Backend - Auditoría
- [ ] Tabla `audit_logs` con: usuario, acción, modelo, cambios, IP, timestamp
- [ ] Middleware de auditoría para todas las acciones
- [ ] Log de login/logout
- [ ] Log de creación/edición/eliminación
- [ ] Log de visualización de documentos
- [ ] Log de firmas
- [ ] Log de descargas
- [ ] Endpoint GET `/api/audit` para consultar logs
- [ ] Filtros por usuario, acción, fecha
- [ ] Exportación de logs a Excel
- [ ] Retención de logs (cleanup automático después de X meses)

#### Backend - Búsqueda Avanzada
- [ ] Endpoint GET `/api/search/documents` con múltiples filtros
- [ ] Búsqueda por: nombre, categoría, período, empleado, estado
- [ ] Búsqueda full-text en contenido (opcional, con Laravel Scout)
- [ ] Filtros combinables (AND/OR)
- [ ] Ordenamiento configurable
- [ ] Paginación optimizada
- [ ] Cache de búsquedas frecuentes
- [ ] Índices de base de datos para performance

#### Backend - Optimizaciones y Producción
- [ ] Implementar cache de consultas frecuentes (Redis)
- [ ] Optimizar queries N+1 con eager loading
- [ ] Compresión de respuestas API (gzip)
- [ ] Rate limiting en API (throttle)
- [ ] CORS configurado correctamente
- [ ] HTTPS enforced
- [ ] Encriptación de datos sensibles
- [ ] Backup automático de base de datos
- [ ] Configurar monitoreo (Laravel Telescope o similar)
- [ ] Centralizar logs (Laravel Log Viewer)
- [ ] Testing de carga con artillery/k6
- [ ] Scripts de deployment (CI/CD)
- [ ] Variables de entorno documentadas
- [ ] Health check endpoint

#### Frontend - Firma Digital
- [ ] Modal de términos y condiciones (primera firma)
- [ ] Aceptación de términos con checkbox
- [ ] Botón de firma con confirmación
- [ ] Indicador visual: firmado/pendiente
- [ ] Lista de documentos pendientes de firma
- [ ] Firma masiva (seleccionar múltiples)
- [ ] Descarga de certificado de firma
- [ ] Historial de firmas

#### Frontend - Búsqueda y Reportería
- [ ] Buscador avanzado con múltiples filtros
- [ ] Filtros: fecha, categoría, estado, empleado
- [ ] Selector de rango de fechas (date picker)
- [ ] Aplicar/limpiar filtros
- [ ] Resultados paginados
- [ ] Ordenamiento por columnas
- [ ] Exportación a Excel (librería xlsx)
- [ ] Dashboard de reportes con gráficos
- [ ] Métricas: total documentos, por categoría, por estado
- [ ] Gráfico de evolución temporal
- [ ] Top empleados con más documentos
- [ ] Documentos próximos a vencer

#### Testing
- [ ] Tests unitarios de modelos (PHPUnit)
- [ ] Tests de API endpoints (Feature tests)
- [ ] Tests de Jobs (Job testing)
- [ ] Tests de eventos y listeners
- [ ] Tests de políticas de acceso
- [ ] Tests de validaciones
- [ ] Tests frontend (Jest + Testing Library)
- [ ] Tests de componentes React
- [ ] Tests de stores de Zustand
- [ ] Tests end-to-end (Cypress/Playwright)
- [ ] Coverage mínimo del 70%

#### Documentación
- [ ] README actualizado con instrucciones completas
- [ ] Documentación de API (Swagger/OpenAPI)
- [ ] Manual técnico de arquitectura
- [ ] Manual de instalación y deployment
- [ ] Manual de usuario final (con capturas)
- [ ] Guía de troubleshooting
- [ ] Documentación de variables de entorno
- [ ] Diagramas actualizados (arquitectura, flujo, BD)

---

## TODO: MÓDULO DE VACACIONES (NUEVO)

### Backend - Modelos y Base de Datos

- [ ] Crear modelo `VacationRequest` con campos:
  - empleado_id, tenant_id
  - fecha_inicio, fecha_fin, días_solicitados
  - motivo, estado (pendiente/aprobado/rechazado)
  - aprobador_id, fecha_aprobacion, comentarios_aprobador
  - timestamps
- [ ] Crear modelo `Approver` con campos:
  - usuario_id, tenant_id, orden
  - puede_aprobar_vacaciones (boolean)
- [ ] Crear modelo `VacationNotification` para tracking
- [ ] Migración tabla `vacation_requests`
- [ ] Migración tabla `approvers`
- [ ] Migración tabla `vacation_notifications`
- [ ] Relaciones: VacationRequest -> Employee, Approver
- [ ] Validaciones: fechas válidas, no solapamiento

### Backend - API Controllers

- [ ] `VacationRequestController`:
  - POST `/api/vacation-requests` (crear solicitud)
  - GET `/api/vacation-requests` (listar mis solicitudes)
  - GET `/api/vacation-requests/{id}` (detalle)
  - PUT `/api/vacation-requests/{id}/approve` (aprobar)
  - PUT `/api/vacation-requests/{id}/reject` (rechazar)
  - DELETE `/api/vacation-requests/{id}` (cancelar)
- [ ] `ApproverController`:
  - GET `/api/approvers` (listar aprobadores)
  - POST `/api/approvers` (configurar aprobador)
  - DELETE `/api/approvers/{id}` (remover)
- [ ] Validaciones: solo admin configura aprobadores
- [ ] Validaciones: empleado solo ve sus solicitudes
- [ ] Políticas de acceso por rol

### Backend - Jobs y Notificaciones

- [ ] Job `SendVacationRequestEmail`:
  - Enviar email a aprobadores
  - Incluir datos: empleado, fechas, motivo
  - Link con token para aprobar/rechazar directo
- [ ] Job `SendVacationStatusEmail`:
  - Notificar empleado de aprobación/rechazo
  - Notificar admin plataforma
  - Incluir comentarios del aprobador
- [ ] Template email: solicitud nueva (aprobadores)
- [ ] Template email: solicitud aprobada (empleado)
- [ ] Template email: solicitud rechazada (empleado)
- [ ] Template email: notificación admin
- [ ] Generar token único para link de aprobación
- [ ] Endpoint GET `/api/vacation-requests/approve-token/{token}`
- [ ] Expiración de token (48 horas)

### Backend - WebSocket Vacaciones

- [ ] Evento `VacationRequestCreated`
- [ ] Evento `VacationRequestApproved`
- [ ] Evento `VacationRequestRejected`
- [ ] Broadcast a canal de aprobadores
- [ ] Broadcast a canal del empleado
- [ ] Broadcast a canal de admin plataforma

### Backend - Reportería

- [ ] Endpoint GET `/api/vacation-requests/report`:
  - Filtros: fecha_inicio, fecha_fin, estado, empleado_id, tenant_id
  - Paginación
  - Ordenamiento
- [ ] Endpoint GET `/api/vacation-requests/export`:
  - Exportar a Excel con filtros
  - Incluir: empleado, fechas, días, estado, aprobador
- [ ] Endpoint GET `/api/vacation-requests/stats`:
  - Total solicitudes, aprobadas, rechazadas, pendientes
  - Por período, por tenant
  - Días promedio solicitados

### Frontend - Configuración de Aprobadores

- [ ] Página `/admin/vacation-approvers`
- [ ] Lista de aprobadores por organización
- [ ] Botón "Agregar Aprobador"
- [ ] Modal para seleccionar usuario
- [ ] Solo usuarios con rol admin/manager pueden ser aprobadores
- [ ] Orden de aprobación (si hay múltiples)
- [ ] Eliminar aprobador
- [ ] Filtro por organización (admin plataforma)

### Frontend - Solicitudes de Vacaciones

- [ ] Página `/employee/vacation-requests`
- [ ] Botón "Nueva Solicitud"
- [ ] Modal con formulario:
  - Date picker: fecha inicio
  - Date picker: fecha fin
  - Cálculo automático de días
  - Textarea: motivo
  - Mostrar días disponibles restantes
  - Validación: no solapar con solicitudes aprobadas
- [ ] Tabla de mis solicitudes:
  - Columnas: fechas, días, estado, aprobador, fecha aprobación
  - Estados con badges de color
  - Filtro por estado
  - Ordenamiento
- [ ] Detalle de solicitud con timeline
- [ ] Botón cancelar (solo si está pendiente)

### Frontend - Bandeja de Aprobación

- [ ] Página `/admin/vacation-approvals`
- [ ] Tabla de solicitudes pendientes:
  - Empleado, fechas, días, motivo
  - Botón "Aprobar" y "Rechazar"
- [ ] Modal de aprobación:
  - Confirmar datos
  - Textarea: comentarios (opcional)
  - Botón confirmar
- [ ] Modal de rechazo:
  - Textarea: motivo del rechazo (obligatorio)
  - Botón confirmar
- [ ] Contador de solicitudes pendientes en navbar
- [ ] Badge rojo con número
- [ ] Filtros: empleado, fecha solicitud
- [ ] Historial de solicitudes aprobadas/rechazadas

### Frontend - Notificaciones Vacaciones

- [ ] Toast notification cuando llega nueva solicitud (aprobadores)
- [ ] Toast notification cuando aprueban/rechazan (empleado)
- [ ] Badge en centro de notificaciones
- [ ] Notificación con link directo a la solicitud
- [ ] Marcar como leída al hacer click
- [ ] Sonido opcional para notificaciones importantes

### Frontend - Reportería de Vacaciones

- [ ] Página `/admin/vacation-reports`
- [ ] Filtros:
  - Rango de fechas (date range picker)
  - Estado: todos/pendiente/aprobado/rechazado
  - Empleado (autocomplete)
  - Organización (solo admin plataforma)
- [ ] Botón "Aplicar Filtros"
- [ ] Tabla paginada con resultados
- [ ] Botón "Exportar a Excel"
- [ ] Gráfico de barras: solicitudes por mes
- [ ] Gráfico de pie: distribución por estado
- [ ] Métricas:
  - Total solicitudes
  - Días promedio solicitados
  - Tasa de aprobación
  - Tiempo promedio de respuesta
- [ ] Vista diferenciada:
  - Admin plataforma: ve todas las organizaciones
  - Admin organización: solo su organización

### Frontend - Integración con WebSocket

- [ ] Escuchar evento `VacationRequestCreated` (aprobadores)
- [ ] Escuchar evento `VacationRequestApproved` (empleado + admin)
- [ ] Escuchar evento `VacationRequestRejected` (empleado)
- [ ] Actualizar contador de pendientes en tiempo real
- [ ] Actualizar lista de solicitudes sin recargar
- [ ] Mostrar toast con notificación
- [ ] Actualizar badge de notificaciones

### Testing - Módulo Vacaciones

- [ ] Test: crear solicitud de vacaciones
- [ ] Test: aprobar solicitud
- [ ] Test: rechazar solicitud
- [ ] Test: cancelar solicitud
- [ ] Test: validación de fechas
- [ ] Test: no solapamiento
- [ ] Test: envío de emails
- [ ] Test: eventos WebSocket
- [ ] Test: permisos por rol
- [ ] Test: reportería con filtros
- [ ] Test: exportación Excel

---

## RESUMEN DE TAREAS TOTALES

### Sistema de Documentos
- **Backend:** ~180 tareas
- **Frontend:** ~110 tareas
- **Testing/Docs:** ~40 tareas
- **Total:** ~330 tareas

### Módulo de Vacaciones
- **Backend:** ~35 tareas
- **Frontend:** ~40 tareas
- **Testing:** ~12 tareas
- **Total:** ~87 tareas

### GRAN TOTAL: ~417 tareas por implementar

## DEPENDENCIAS CRÍTICAS A INSTALAR

### Backend
```bash
composer require laravel/sanctum          # Autenticación API con cookies (PRIORITARIO)
composer require laravel/horizon          # Queue management
composer require laravel/reverb           # WebSockets
composer require maatwebsite/excel        # Export Excel
# NO instalar spatie/laravel-permission - usaremos sistema custom más simple
```

### Frontend
```bash
npm install axios                         # HTTP client (si no está)
npm install laravel-echo pusher-js        # WebSocket client
npm install react-pdf                     # PDF viewer
npm install react-dropzone                # File upload
npm install xlsx                          # Excel export
npm install date-fns                      # Date utilities
```

---

## DECISIONES DE ARQUITECTURA - ROLES Y PERMISOS

### Sistema de Roles Propuesto (EN ANÁLISIS)

**3 Roles principales (un usuario puede tener múltiples roles):**

> ⚠️ **NOTA:** La funcionalidad de cambio de contexto (admin/client) está en análisis.  
> Pendiente definir si es necesario o si mostramos todas las opciones juntas en el menú.

1. **ROOT (Super Admin)**
   - Usuario único del sistema (platform@miboleta.com)
   - Acceso total sin restricciones
   - Gestiona todas las organizaciones
   - Configura usuarios admin de organizaciones
   - Ve métricas globales del sistema
   - NO tiene tenant_id (null)

2. **ADMIN (Administrador de Organización)**
   - Múltiples admins por organización
   - Gestiona empleados de su organización
   - Carga documentos masivamente
   - Ve reportes de su organización
   - Configura aprobadores de vacaciones
   - Tiene tenant_id específico
   - **PUEDE SER ADMIN + CLIENT simultáneamente**

3. **CLIENT (Usuario Final / Empleado)**
   - Empleados de la organización
   - Solo ve sus propios documentos
   - Puede descargar y firmar documentos
   - Crea solicitudes de vacaciones
   - Tiene tenant_id + employee_id
   - **PUEDE SER ADMIN + CLIENT simultáneamente**

**Casos de uso comunes:**
- ✅ Juan es SOLO admin → gestiona pero no tiene documentos propios
- ✅ María es SOLO client → solo ve sus documentos
- ✅ Carlos es ADMIN + CLIENT → puede gestionar Y ver sus propios documentos
- ✅ Ana es ADMIN de "Empresa A" (solo 1 tenant por admin)

### ¿Por qué NO usar Spatie Laravel Permission?

**Ventajas de NO usarlo:**
- ✅ Más simple para 3 roles fijos
- ✅ Menos tablas en base de datos (2 en vez de 5)
- ✅ Queries más rápidas (menos JOINs)
- ✅ Más fácil de entender y mantener
- ✅ No necesitamos permisos granulares complejos

**Cuándo SÍ usarlo:**
- ⚠️ Si necesitas +10 roles diferentes
- ⚠️ Si los permisos cambian frecuentemente
- ⚠️ Si necesitas permisos por módulo/recurso
- ⚠️ Si roles son configurables por el usuario

```sql
-- Tabla: roles (3 registros fijos)
CREATE TABLE roles (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50) UNIQUE, -- 'root', 'admin', 'client'
    display_name VARCHAR(100), -- 'Super Administrador', 'Administrador', 'Cliente'
    description TEXT,
    permissions JSON, -- {"employees": ["create","read","update","delete"], ...}
    guard_name VARCHAR(50) DEFAULT 'web'
);

-- Tabla: user_roles (relación usuario-rol-tenant)
CREATE TABLE user_roles (
    id BIGINT PRIMARY KEY,
    user_id BIGINT, -- FK a users
    role_id BIGINT, -- FK a roles
    tenant_id BIGINT NULL, -- NULL para root, específico para admin/client
    granted_by BIGINT NULL, -- quién otorgó el rol
    granted_at TIMESTAMP,
    UNIQUE(user_id, role_id, tenant_id) -- evita duplicados
);

-- Ejemplos de registros:
-- Carlos (user_id=5, tenant_id=1):
--   user_roles: (5, admin, 1) + (5, client, 1) = ADMIN + CLIENT de la misma empresa
--
-- Juan (user_id=3, tenant_id=1):
--   user_roles: (3, admin, 1) = SOLO ADMIN de la empresa
```

**Ventajas del pivot user_roles:**
- ✅ Un usuario puede tener MÚLTIPLES roles en la MISMA organización (admin + client)
- ✅ Un admin pertenece a UNA SOLA organización (tenant_id único por admin)
- ✅ Permite auditoría de quién otorgó el rol
- ✅ Flexible para casos especiales
    UNIQUE(user_id, role_id, tenant_id) -- un usuario puede ser admin de varios tenants
);
```

**Ventaja del pivot user_roles:**
- Un usuario puede ser admin de MÚLTIPLES organizaciones
- Ejemplo: Juan es admin de "Empresa A" y "Empresa B"
- Permite auditoría de quién otorgó el rol

### Permisos en JSON (flexible)

```json
// role: root
{
  "tenants": ["create", "read", "update", "delete"],
  "employees": ["create", "read", "update", "delete"],
  "documents": ["create", "read", "update", "delete", "download"],
  "reports": ["view_all"],
  "system": ["configure"]
}

// role: admin
{
  "employees": ["create", "read", "update", "delete"], // solo su tenant
  "documents": ["create", "read", "download"], // solo su tenant
  "reports": ["view_tenant"],
  "vacations": ["approve", "reject"]
}

### Helpers en Modelo User

```php
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    
    // Relaciones
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot('tenant_id', 'granted_at')
                    ->withTimestamps();
    }
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    
    // Verificar rol
    public function hasRole($role, $tenantId = null): bool
    {
        $query = $this->roles()->where('name', $role);
        
        if ($tenantId) {
            $query->wherePivot('tenant_id', $tenantId);
        } elseif ($this->tenant_id) {
            // Si no se especifica tenant, usar el del usuario
            $query->wherePivot('tenant_id', $this->tenant_id);
        }
        
        return $query->exists();
    }
    
    public function isRoot(): bool
    {
        return $this->hasRole('root');
    }
    
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
    
    public function isClient(): bool
    {
        return $this->hasRole('client');
    }
    
    // Verificar si tiene ambos roles
    public function isAdminAndClient(): bool
    {
        return $this->isAdmin() && $this->isClient();
    }
    
    // Obtener roles actuales del usuario
    public function getCurrentRoles(): array
    {
        return $this->roles()
                    ->wherePivot('tenant_id', $this->tenant_id)
                    ->pluck('name')
                    ->toArray();
    }
    
    // Verificar permiso (considera TODOS los roles del usuario)
    public function can($permission): bool
    {
        $roles = $this->roles()
                     ->wherePivot('tenant_id', $this->tenant_id)
                     ->get();
        
        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions, true);
            
            foreach ($permissions as $resource => $actions) {
                if (in_array($permission, $actions)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    // Verificar si puede ver documentos propios (es client o admin+client)
    public function canViewOwnDocuments(): bool
    {
        return $this->isClient() && $this->employee_id !== null;
    }
}
```     return $this->roles()
                    ->where('name', 'admin')
                    ->get()
                    ->pluck('pivot.tenant_id')
                    ->filter()
                    ->unique();
    }
}
```

### Middleware de Roles

```php
// CheckRole.php
class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        
        // Root siempre pasa
        if ($user->isRoot()) {
            return $next($request);
        }
        
        // Verificar si tiene alguno de los roles permitidos
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }
        
        return response()->json(['message' => 'No autorizado'], 403);
    }
}

// TenantScope.php
class TenantScope
### Uso en Routes

```php
// Solo root
Route::middleware(['auth:sanctum', 'role:root'])->group(function () {
    Route::apiResource('tenants', TenantController::class);
    Route::get('reports/global', [ReportController::class, 'global']);
});

// Root o Admin (funciones administrativas)
Route::middleware(['auth:sanctum', 'role:root,admin', 'tenant.scope'])->group(function () {
    Route::apiResource('employees', EmployeeController::class);
    Route::post('documents/upload', [DocumentController::class, 'upload']);
    Route::get('reports/tenant', [ReportController::class, 'tenant']);
    Route::post('vacations/approve', [VacationController::class, 'approve']);
});

// Client (ver sus propios documentos)
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('documents/my', [DocumentController::class, 'myDocuments']);
    Route::get('documents/{id}/download', [DocumentController::class, 'download']);
    Route::post('documents/{id}/sign', [DocumentController::class, 'sign']);
    Route::post('vacations', [VacationController::class, 'request']);
});

// NOTA: Si un usuario es ADMIN + CLIENT:
//   - Puede acceder a AMBOS grupos de rutas
//   - En frontend, tendrá un "Cambiar contexto" para alternar vistas
```

### Frontend - Cambio de Contexto (Admin + Client)

**Componente de Cambio de Vista:**

```tsx
// components/RoleSwitch.tsx
import { useAuthStore } from '@/stores/authStore';

export function RoleSwitch() {
  const { user, currentContext, setContext } = useAuthStore();
  
  // Verificar si tiene ambos roles
  const isAdminAndClient = user?.roles?.includes('admin') && 
                           user?.roles?.includes('client');
  
  if (!isAdminAndClient) return null;
  
  return (
    <div className="flex items-center gap-2 p-2 bg-gray-100 rounded">
      <span className="text-sm">Vista actual:</span>
      <button
        onClick={() => setContext('admin')}
        className={currentContext === 'admin' ? 'btn-primary' : 'btn-secondary'}
      >
        👔 Administrador
      </button>
      <button
        onClick={() => setContext('client')}
        className={currentContext === 'client' ? 'btn-primary' : 'btn-secondary'}
      >
        👤 Mis Documentos
      </button>
    </div>
  );
}
```

**Auth Store con Contexto:**

```typescript
// stores/authStore.ts
interface AuthState {
  user: User | null;
  currentContext: 'admin' | 'client' | null; // NUEVO
  
  setContext: (context: 'admin' | 'client') => void;
  getCurrentRoles: () => string[];
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      currentContext: null,
      
      login: async (email, password) => {
        // ... login normal
        const { data } = await apiClient.post('/api/auth/login', { email, password });
        
        // Determinar contexto inicial
        const hasAdmin = data.user.roles.includes('admin');
        const hasClient = data.user.roles.includes('client');
        
        let initialContext = null;
        if (hasAdmin && hasClient) {
          initialContext = 'admin'; // Por defecto admin si tiene ambos
        } else if (hasAdmin) {
          initialContext = 'admin';
        } else if (hasClient) {
          initialContext = 'client';
        }
        
        set({ 
          user: data.user, 
          currentContext: initialContext 
        });
      },
      
      setContext: (context) => {
        const { user } = get();
        
        // Verificar que el usuario tenga ese rol
        if (user?.roles?.includes(context)) {
          set({ currentContext: context });
        }
      },
      
      getCurrentRoles: () => {
        const { user } = get();
        return user?.roles || [];
      },
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        currentContext: state.currentContext,
      }),
    }
  )
);
```

**Navegación Condicional:**

```tsx
// App.tsx - Sidebar
function Sidebar() {
  const { user, currentContext } = useAuthStore();
  
  const isAdmin = user?.roles?.includes('admin');
  const isClient = user?.roles?.includes('client');
  const isAdminContext = currentContext === 'admin';
  
  return (
    <aside>
      {/* Switch de contexto si tiene ambos roles */}
      {isAdmin && isClient && <RoleSwitch />}
      
      <nav>
        {/* Menú de Admin - solo si es admin Y está en contexto admin */}
        {isAdmin && isAdminContext && (
          <>
            <NavLink to="/admin/dashboard">Dashboard</NavLink>
            <NavLink to="/admin/employees">Empleados</NavLink>
            <NavLink to="/admin/documents/upload">Cargar Documentos</NavLink>
            <NavLink to="/admin/reports">Reportes</NavLink>
            <NavLink to="/admin/vacations/approvals">Aprobar Vacaciones</NavLink>
          </>
        )}
        
        {/* Menú de Client - solo si es client Y está en contexto client */}
        {isClient && currentContext === 'client' && (
          <>
            <NavLink to="/my-documents">Mis Documentos</NavLink>
            <NavLink to="/my-vacations">Mis Vacaciones</NavLink>
            <NavLink to="/profile">Mi Perfil</NavLink>
          </>
        )}
      </nav>
    </aside>
  );
}
```

### Controller con Lógica de Contexto

```php
// DocumentController.php
class DocumentController extends Controller
{
    // Vista de administrador: todos los documentos del tenant
    public function index(Request $request)
    {
        // Middleware ya filtró por tenant_id si no es root
        $documents = Document::with('employee')
                            ->orderBy('created_at', 'desc')
                            ->paginate(20);
        
        return response()->json($documents);
    }
    
    // Vista de client: solo SUS documentos
    public function myDocuments(Request $request)
    {
        $user = $request->user();
        
        // Verificar que tenga employee_id
        if (!$user->employee_id) {
            return response()->json([
                'message' => 'No tiene empleado asignado'
            ], 400);
        }
        
        $documents = Document::where('employee_id', $user->employee_id)
                            ->orderBy('created_at', 'desc')
                            ->paginate(20);
        
        return response()->json($documents);
    }
}
```     }
        
        return $next($request);
    }
}
```

### Uso en Routes

```php
// Solo root
Route::middleware(['auth:sanctum', 'role:root'])->group(function () {
    Route::apiResource('tenants', TenantController::class);
    Route::get('reports/global', [ReportController::class, 'global']);
});

// Root o Admin
Route::middleware(['auth:sanctum', 'role:root,admin', 'tenant.scope'])->group(function () {
    Route::apiResource('employees', EmployeeController::class);
    Route::post('documents/upload', [DocumentController::class, 'upload']);
});

// Todos los autenticados
Route::middleware(['auth:sanctum', 'role:root,admin,client'])->group(function () {
    Route::get('documents/my', [DocumentController::class, 'myDocuments']);
    Route::get('documents/{id}/download', [DocumentController::class, 'download']);
});
```

---

**Documento actualizado:** 1 de diciembre de 2025

**Documento actualizado:** 1 de diciembre de 2025