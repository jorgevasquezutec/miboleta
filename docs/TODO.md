# 📋 TODO - Sistema de Gestión Documental "MiBoleta"

**Última actualización:** 2025-12-12  
**Estado general del proyecto:** ~99% completado

**✅ Completado:**
- Módulo 0 (Base de Datos) ✅ 100%
- Módulo 1 (Autenticación Backend + Frontend + Seguridad HttpOnly Cookies) ✅ 100%
- Módulo 1.5 (Sistema de Gestión de Contraseñas) ✅ 100%
  - Backend: PasswordController, Mails, Email Templates (HTML completos)
  - Frontend: ForceChangePasswordPage, ForgotPasswordPage, ResetPasswordPage, PasswordResetModal
  - Emails: welcome.blade.php, forgot-password.blade.php, password-reset-admin.blade.php (HTML view)
  - **Sistema de Foto de Perfil (100%)** ✅
    - Migración avatar_url en users table
    - ProfileController con endpoints de upload/delete
    - Accessor getAvatarUrlAttribute() (genera URL completa como logo_url)
    - ProfilePage con drag & drop upload
    - Avatar en Navbar con fallback a iniciales
    - Cache-busting automático para recargas
- Módulo 2 (Multi-Tenancy Backend + TenantSwitcher) ✅ 100%
  - Backend: TenantController completo
  - Frontend: TenantSwitcher con logo_url, tema oscuro y reload automático ✅
  - Filtrado automático por tenant en toda la plataforma ✅
- Módulo 3 (Gestión de Usuarios) ✅ 100%
  - Backend: UserController completo con CRUD + supervisores + búsqueda corregida
  - Frontend: usersStore, UsersListPage, UsersPage, UserDetailPage, UserFormPage
  - Componentes: SupervisorSelector, SupervisorBadge, SubordinatesList, UserTenantsManager
  - Permisos: Solo ROOT puede crear/editar/eliminar usuarios ✅
  - **ConfirmDialog en todas las acciones** ✅
    - UsersPage: ConfirmDialog para eliminar usuarios
    - DocumentsListPage: ConfirmDialog para eliminar documentos
    - ZERO confirm() o alert() nativos en toda la aplicación
- Módulo 4 (Documentos) ✅ 100% - **COMPLETADO**
  
  **Backend (100% completado):**
  - ✅ DocumentController con filtros avanzados (status, tipo, periodo, búsqueda, fechas)
  - ✅ **Archivos protegidos** - Solo accesibles vía endpoints autenticados
  - ✅ Storage en `storage/app/documents/` (NO público, NO acceso directo)
  - ✅ Endpoints `/preview` y `/download` con validación de permisos
  - ✅ Clientes solo ven sus propios documentos
  - ✅ Filtrado por tenant_id en todos los endpoints (my_documents, client, admin)
  - ✅ DocumentBatchController con procesamiento asíncrono de ZIP
  - ✅ DocumentTypeController para gestión de tipos
  - ✅ DocumentSignatureController (firma digital con 2FA)
  - ✅ Sistema de cooldown (30 segundos) para solicitudes de código
  - ✅ Límite de 3 intentos por código de verificación
  - ✅ Parámetro `my_documents` para vista personal del usuario
  - ✅ Filtro por rango de fechas en documentos y lotes
  - ✅ Preview de PDF con Content-Disposition: inline
  - ✅ Procesamiento de batches con Jobs y colas
  
  **Frontend - Admin (100% completado):**
  - ✅ DocumentsListPage con filtros avanzados + DateRangePicker
  - ✅ ConfirmDialog para eliminar documentos (NO confirm() nativo)
  - ✅ BatchesListPage con DateRangePicker + filtro de estado
  - ✅ BatchDetailPage con detalles completos del lote
  - ✅ DocumentUploadView para carga masiva de ZIP
  - ✅ Sidebar con "Mis Documentos" como primer item (en vez de Dashboard)
  - ✅ Navegación completa (Usuarios, Lotes, Documentos)
  
  **Frontend - Employee/Client (100% completado):**
  - ✅ EmployeeDashboardView (Mis Documentos) con datos reales del API
  - ✅ Filtrado correcto por tenant en documentos personales
  - ✅ Botón "Firmar" visible solo para documentos pending + requiresSignature
  - ✅ DocumentViewerView con react-pdf (navegación, zoom, rotación)
  - ✅ PDFViewer component (fix ArrayBuffer detached error)
  - ✅ Badge de estado con colores inline (fix visibilidad)
  - ✅ Paginación visible siempre (estilo consistente)
  - ✅ Sidebar exclusivo para cliente (solo "Mis Documentos")
  - ✅ Navegación consistente (navigate vs window.open)
  - ✅ Filtros de búsqueda, estado y tipo de documento
  
  **Firma Digital 2FA (100% completado):**
  - ✅ DocumentSignatureModal con diseño compacto y responsive
  - ✅ Flujo completo: Términos → Solicitar código → Verificar → Éxito
  - ✅ Sistema de 3 intentos con mensajes de "Te quedan X intentos"
  - ✅ Cooldown con countdown visible (30 segundos)
  - ✅ Manejo de errores con toast (no cierra el modal)
  - ✅ Auto-limpieza del código después de intento fallido
  - ✅ Integración completa en DocumentViewerView
  - ✅ OTP Input de 6 dígitos con timer de expiración
  
  **Sistema Multi-Tenant (100% completado):**
  - ✅ TenantSwitcher con reload automático al cambiar empresa
  - ✅ Header X-Tenant-Id actualizado correctamente
  - ✅ Filtrado de documentos por user_id + tenant_id
  - ✅ Cada usuario ve solo sus documentos de la empresa actual
  
  **Documentos Huérfanos (100% completado):**
  - ✅ Auto-asignación cuando se crea usuario con document_text
  - ✅ Auto-asignación cuando se actualiza document_text de usuario
  - ✅ Documentos cambian de 'orphan' a 'pending' (si requiere firma) o 'active'
  - ✅ Filtrado por tenant_id para asignación correcta
  - ✅ Logs detallados de asignaciones automáticas
  
  **Seguridad de Documentos (100% completado):**
  - ✅ Archivos NO accesibles directamente por URL
  - ✅ Solo sirven vía endpoints autenticados con Sanctum
  - ✅ Validación de permisos antes de servir archivos
  - ✅ Clientes solo acceden a sus propios documentos
  - ✅ Storage privado (`storage/app/documents/` NO en public)

**📋 Módulo 4 - COMPLETADO AL 100%** ✅
**📋 Siguiente Paso:** Comenzar Módulo 5 (Vacaciones) o Módulo 6 (Notificaciones)
**📋 Pendiente:** Módulos 5-8


---

## 📚 ÍNDICE DE MÓDULOS

1. [🗄️ Módulo 0: Base de Datos](#️-módulo-0-base-de-datos)
2. [🔐 Módulo 1: Autenticación y Autorización](#-módulo-1-autenticación-y-autorización)
3. [🔑 Módulo 1.5: Sistema de Gestión de Contraseñas](#-módulo-15-sistema-de-gestión-de-contraseñas)
4. [🏢 Módulo 2: Multi-Tenancy](#-módulo-2-multi-tenancy)
5. [👥 Módulo 3: Gestión de Usuarios](#-módulo-3-gestión-de-usuarios)
6. [📄 Módulo 4: Documentos](#-módulo-4-documentos)
7. [🏖️ Módulo 5: Vacaciones](#️-módulo-5-vacaciones)
8. [🔔 Módulo 6: Notificaciones en Tiempo Real](#-módulo-6-notificaciones-en-tiempo-real)
9. [📊 Módulo 7: Reportes y Auditoría](#-módulo-7-reportes-y-auditoría)
10. [🚀 Módulo 8: Testing y Deployment](#-módulo-8-testing-y-deployment)

---

## 🗄️ MÓDULO 0: BASE DE DATOS

**Objetivo:** Crear toda la estructura de base de datos con migraciones, modelos y seeders.

**Duración estimada:** 5-7 días  
**Estado:** ✅ COMPLETADO

### Backend - Migraciones

- [x] **Migración 01:** `create_tenants_table`
  ```php
  - id, name, ruc (UK), business_name, address, phone
  - logo_path, status (string), timestamps, soft deletes
  ```

- [x] **Migración 02:** `update_users_table_add_missing_fields`
  ```php
  - Agregar: document_type (string), document_text (UK)
  - Agregar: last_name, phone
  - Agregar: immediate_supervisor_id (FK self-reference)
  - Agregar: status (string), last_login_at
  - Agregar: soft deletes
  ```

- [x] **Migración 03:** `create_roles_table`
  ```php
  - id, name (UK: root/admin/client)
  - display_name, description, permissions (json)
  - guard_name, timestamps
  ```

- [x] **Migración 04:** `create_user_roles_table`
  ```php
  - id, user_id (FK), role_id (FK)
  - granted_by (FK), granted_at, timestamps
  - UNIQUE(user_id, role_id)
  ```

- [x] **Migración 05:** `create_user_tenants_table` ⭐
  ```php
  - id, user_id (FK), tenant_id (FK)
  - is_primary (boolean), timestamps
  - UNIQUE(user_id, tenant_id)
  ```

- [x] **Migración 06:** `create_personal_access_tokens_table` (Sanctum)

- [ ] **Migración 07:** `create_document_types_table`
  ```php
  - id, name (UK), display_name, description
  - requires_signature, is_active, timestamps
  ```

- [ ] **Migración 08:** `create_documents_table`
  ```php
  - id, tenant_id (FK), user_id (FK nullable)
  - employee_document_number, doc_type_id (FK)
  - period, file_path, file_size, original_name
  - status (enum), uploaded_by (FK), signature (json)
  - signed_at, expires_at, timestamps, soft deletes
  - UNIQUE(tenant_id, employee_document_number, doc_type_id, period)
  ```

- [ ] **Migración 09:** `create_vacation_requests_table`
  ```php
  - id, user_id (FK), tenant_id (FK), year
  - start_date, end_date, days_requested (decimal)
  - reason, status (enum)
  - approved_by (FK), approved_at
  - rejected_by (FK), rejected_at, rejected_reason
  - was_taken (boolean, default FALSE) ⭐
  - timestamps
  ```

- [ ] **Migración 10:** `create_notifications_table`
  ```php
  - id, user_id (FK), tenant_id (FK), actor_id (FK)
  - related_type, related_id, type, title, message
  - action_url, is_read, read_at, timestamps
  ```

- [ ] **Migración 11:** `create_audit_logs_table`
  ```php
  - id, user_id (FK nullable), tenant_id (FK nullable)
  - action, model, model_id
  - old_values (json), new_values (json)
  - ip_address, user_agent, created_at
  ```

### Backend - Modelos Eloquent

- [x] **Tenant.php**
  - Relaciones: users (via user_tenants), documents, vacationRequests
  - Métodos: isActive(), hasUser()
  - SoftDeletes habilitado

- [x] **User.php** (modificado)
  - Traits: HasApiTokens, Notifiable, SoftDeletes
  - Relaciones: tenants(), roles(), immediateSupervisor(), subordinates()
  - Métodos: hasRole(), isRoot(), isAdmin(), isClient(), primaryTenant(), belongsToTenant(), isActive(), getFullNameAttribute()

- [x] **Role.php**
  - Relaciones: users()
  - Métodos: hasPermission(), isRoot(), isAdmin(), isClient()

- [x] **UserRole.php** (pivot)
- [x] **UserTenant.php** (pivot) ⭐
- [ ] **DocumentType.php**
- [ ] **Document.php**
  - Métodos: generateFilePath(), sign(), isExpired()

- [ ] **VacationRequest.php**
  - Métodos: approve(), reject(), markAsTaken(), markAsNotTaken()

- [ ] **Notification.php**
- [ ] **AuditLog.php**

### Backend - Seeders

- [x] **RoleSeeder**
  - 3 roles: root, admin, client con permisos JSON
  - Client: view_own_documents, sign_documents, request_vacation, view_own_vacation_requests
  - Admin: manage_users, upload_documents, manage_documents, approve_vacations, view_reports, tenant_configuration

- [x] **TenantSeeder**
  - 3 tenants: Corporación ABC, Empresa XYZ, Tech Solutions

- [x] **UserSeeder**
  - 6 usuarios con roles, tenants, y supervisores:
    * Root Admin (sin tenant)
    * 2 Admins (uno por tenant)
    * 2 Clients (uno por tenant con supervisor)
    * 1 Cliente multi-tenant (ABC primario + XYZ secundario)

- [ ] **DocumentTypeSeeder**
  - 8 tipos: boleta, liquidacion, cts, gratificacion, utilidades, vacaciones, contrato, addendum

### ✅ Criterios de Completitud
- [x] Todas las migraciones ejecutan sin errores
- [x] Seeders populan datos correctamente
- [x] Relaciones de modelos funcionan (eager loading)
- [x] Multi-tenancy implementado con user_tenants
- [ ] Diagrama ER documentado completamente

---

## 🔐 MÓDULO 1: AUTENTICACIÓN Y AUTORIZACIÓN

**Objetivo:** Sistema completo de autenticación con Laravel Sanctum y gestión de roles/permisos.

**Duración estimada:** 5-7 días
**Estado:** ✅ COMPLETADO (Backend + Frontend + Sistema de Cookies Seguro + Auto-refresh)

**Dependencias:** Módulo 0 completado

**🔐 SEGURIDAD IMPLEMENTADA:**
- ✅ Sistema completo de HttpOnly cookies (access + refresh tokens)
- ✅ Access token: 1 hora, SameSite=Lax, HttpOnly
- ✅ Refresh token: 30 días, SameSite=Strict, HttpOnly
- ✅ Protección contra XSS (tokens no accesibles desde JavaScript)
- ✅ Protección contra CSRF (flags SameSite)
- ✅ Auto-refresh transparente de tokens en frontend
- ✅ Revocación de tokens en base de datos
- ✅ Auditoría con IP y User Agent

### Backend - Instalación y Configuración

- [x] Instalar Laravel Sanctum
  ```bash
  composer require laravel/sanctum
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
  php artisan migrate
  ```

- [x] Configurar CORS en `config/cors.php`
  ```php
  'supports_credentials' => true,
  'allowed_origins' => ['http://localhost:5173'],
  ```

- [x] Configurar Sanctum en `config/sanctum.php`
  ```php
### Backend - Controllers

- [x] **AuthController.php** ⭐ ACTUALIZADO CON COOKIES SEGURAS
  ```php
  POST   /api/login        → autenticación con HttpOnly cookies
  POST   /api/refresh      → renovar access token
  POST   /api/logout       → cerrar sesión (revocar todos los tokens)
  GET    /api/me           → usuario actual con roles y tenants
  ```
  - Validaciones: email, password
  - Retorna: user (con todos los campos), roles, tenants, primary_tenant
  - Cookies HttpOnly con access_token y refresh_token
  - Access token: 1 hora de duración
  - Refresh token: 30 días de duración
  - Actualiza last_login_at en cada login
  - Documentado en Swagger con ejemplos de usuarios
  - CSRF excluido para /api/login y /api/refresh

### Backend - Modelos Adicionales

- [x] **RefreshToken.php** ⭐ NUEVO
  ```php
  - Tabla: refresh_tokens
### Backend - Middleware

- [x] **CheckRole.php**
  ```php
  - Verificar si usuario tiene rol(es) requerido(s)
  - Root siempre pasa
  - Retornar 403 si no autorizado
  - Uso: Route::middleware('role:admin,root')
  ```

- [x] **TenantScope.php** ⭐
  ```php
  - Lee X-Tenant-ID header o tenant_id query param
  - Fallback a primary tenant del usuario
  - Verifica acceso via user_tenants
  - Root tiene acceso total
  - Agrega current_tenant_id al request
  ```

- [x] **EnsureCookieAccessToken.php** ⭐ NUEVO
  ```php
  - Lee access_token desde cookie HttpOnly
  - Inyecta como Bearer token en Authorization header
  - Permite que Sanctum autentique con cookies
  - Registrado en bootstrap/app.php (prepend a API middleware)
  ``` **CheckRole.php**
  ```php
  - Verificar si usuario tiene rol(es) requerido(s)
  - Root siempre pasa
  - Retornar 403 si no autorizado
  - Uso: Route::middleware('role:admin,root')
  ```

- [x] **TenantScope.php** ⭐
  ```php
  - Lee X-Tenant-ID header o tenant_id query param
  - Fallback a primary tenant del usuario
  - Verifica acceso via user_tenants
### Backend - Routes

- [x] Configurar rutas públicas
  ```php
  POST /api/login (CSRF excluido)
  POST /api/refresh (CSRF excluido) ⭐ NUEVO
  ```

- [x] Configurar rutas protegidas
  ```php
  middleware(['auth:sanctum'])
  GET /api/me
  POST /api/logout
  ```

- [x] Configurar Swagger
  ```php
  GET /api/documentation
  GET /api/docs
  ```
### Frontend - Auth Store

- [x] Actualizar `stores/authStore.ts` ⭐ COMPLETADO CON COOKIES
  ```typescript
  interface AuthState {
    user: User | null;
    // token eliminado - ahora se maneja con cookies HttpOnly
    currentTenant: Tenant | null;
    tenants: Tenant[];
    login(email, password): Promise<void>;
    logout(): Promise<void>;
    me(): Promise<User>;
    switchTenant(tenantId): void;
  }
  ```
  - ✅ Login guarda user y tenants (SIN token)
  - ✅ Cookies HttpOnly manejadas automáticamente por navegador
  - ✅ currentTenant persiste en localStorage
  - ✅ switchTenant implementado

### Frontend - API Client

- [x] Crear `infrastructure/http/apiClient.ts` con Axios ⭐ COMPLETADO
  ```typescript
  import axios from 'axios';
  
  const apiClient = axios.create({
    baseURL: 'http://localhost/api',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    withCredentials: true, // ⭐ CRÍTICO: permite cookies HttpOnly
  });
  
  // Interceptor de request
  - Solo agrega X-Tenant-ID header
  - NO agrega Authorization (lo hace la cookie automáticamente)
  
  // Interceptor de response
  - 401 → intenta refresh automático
  - Cola de requests durante refresh (evita race conditions)
  - Si refresh falla → limpia storage y redirect a /login
  - 403, 404, 422, 500 → manejo de errores apropiado
  ```
  
  **Características avanzadas:**
  - ✅ Auto-refresh transparente de tokens
  - ✅ Cola de requests durante refresh (failedQueue)
  - ✅ Previene múltiples llamadas simultáneas al endpoint /refresh
  - ✅ Reintentar requests originales después de refresh exitoso
  - ✅ Limpieza automática de storage si refresh fallauardar currentTenant en localStorage
  - Implementar switchTenant

### Frontend - API Client

- [ ] Crear `infrastructure/http/apiClient.ts` con Axios ⭐ NUEVO
  ```typescript
  import axios from 'axios';
  
  const apiClient = axios.create({
    baseURL: 'http://localhost/api',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  });
  
  // Interceptor para agregar token
### Frontend - Componentes

- [x] **LoginPage.tsx** ⭐ LISTO PARA CONECTAR
  - Form con email y password
  - Validaciones frontend
  - Loading state
  - Manejo de errores
  - Muestra lista de usuarios de prueba
  - **Pendiente:** Probar con API real

- [x] **ProtectedRoute.tsx** ⭐ ACTUALIZADO
  - Verifica autenticación sin token (basado en user)
  - Verifica roles permitidos
  - Redirect a login si no autenticado

- [ ] **TenantSwitcher.tsx** ⭐ PENDIENTE
  - Dropdown con lista de tenants del usuario
  - Cambiar tenant activo
  - Mostrar tenant actual en navbar
  - Solo mostrar si usuario tiene múltiples tenants

- [ ] **Navbar.tsx** ⭐ PENDIENTE ACTUALIZAR
  - Mostrar usuario logueado con full_name
  - Mostrar tenant actual
  - Integrar TenantSwitcher
  - Botón de logout funcional

### Frontend - Hooks

- [x] **useAuth.ts** ⭐ COMPLETADO
  ```typescript
  const { user, isAuthenticated, hasRole, currentTenant, logout } = useAuth();
  // isAuthenticated basado solo en user (no en token)
  ```

- [ ] **usePermission.ts** (FUTURO)
  ```typescript
  const { can, canAny, canAll } = usePermission();
  ```

### Frontend - Repositorios

- [x] **UserRepository** ⭐ ACTUALIZADO
  - ✅ Usa apiClient real (no mockApi)
  - ✅ login(): retorna solo user (cookies manejadas automáticamente)
  - ✅ logout(): llama backend para revocar tokens y limpiar cookies
### ✅ Criterios de Completitud Backend
- [x] Login funciona con HttpOnly cookies
- [x] Access token dura 1 hora, Refresh token 30 días
- [x] Logout revoca todos los tokens del usuario
- [x] Endpoint /refresh renueva access token automáticamente
- [x] Middleware EnsureCookieAccessToken inyecta Bearer token
- [x] RefreshToken model con auditoría (IP, User Agent)
- [x] Middleware bloquea rutas según rol
- [x] Policies verifican acceso a recursos
- [x] Swagger documenta todos los endpoints
- [x] CSRF excluido para /api/login y /api/refresh

### ✅ Criterios de Completitud Frontend
- [x] apiClient configurado con withCredentials: true
- [x] Interceptor de request agrega X-Tenant-ID
- [x] Interceptor de response con auto-refresh de tokens
- [x] Cola de requests durante refresh (failedQueue)
- [x] authStore trabaja sin token manual (cookies HttpOnly)
- [x] UserRepository actualizado para API real
- [x] useAuth hook basado en user (no en token)
- [x] ProtectedRoute valida autenticación sin token
- [x] Login page lista para pruebas

### ✅ Testing Completado
- [x] Probar login end-to-end ✅
- [x] Verificar cookies en DevTools (HttpOnly, Secure, SameSite) ✅
- [x] Probar auto-refresh cuando access token expire ✅
- [x] Probar logout y limpieza de cookies ✅
- [x] Verificar que frontend no puede acceder a tokens desde JavaScript ✅
- [x] Configurar duración de tokens (access: 1h, refresh: 30 días) ✅
- [x] Resolver problema de cookies encriptadas ✅
- [ ] Probar TenantSwitcher con usuario multi-tenant (PENDIENTE - Módulo 2)
- [ ] Tests unitarios de autenticación (PENDIENTE - Módulo 8)

### 📚 Documentación Creada
- [x] **AUTH_SYSTEM.md** - Documentación completa del sistema
  - Diagramas de arquitectura (backend + frontend)
  - Flujos detallados: Login, Authenticated Request, Token Refresh, Logout
  - Guía de testing con curl
  - Configuración y troubleshooting
  - Comandos de mantenimiento (cleanup tokens, revoke sessions))
  ```typescript
  const { can, canAny, canAll } = usePermission();
  ```

### Frontend - Repositorios

- [ ] **UserRepository** ⭐ ACTUALIZAR
  - Reemplazar mockApi con apiClient real
  - Implementar login(), logout(), me()
  - Manejo de errores

### ✅ Criterios de Completitud
- [x] Login funciona con token Bearer
- [x] Logout invalida token correctamente
- [x] Middleware bloquea rutas según rol
- [x] Policies verifican acceso a recursos
- [x] Swagger documenta todos los endpoints
- [x] CSRF excluido para endpoints públicos
- [ ] Frontend conecta con API real
- [ ] Login page muestra usuarios de prueba
- [x] TenantSwitcher funciona para multi-tenant ✅
- [x] Navbar muestra usuario y tenant actual ✅
- [ ] ProtectedRoute valida autenticación
- [ ] Tests de autenticación pasan

---

## 🔑 MÓDULO 1.5: SISTEMA DE GESTIÓN DE CONTRASEÑAS

**Objetivo:** Sistema integral de gestión de contraseñas con creación automática, recuperación y reset administrativo.

**Duración estimada:** 4-5 días  
**Estado:** ✅ COMPLETADO (100%)

**Dependencias:** Módulo 0 y Módulo 1 completados

### Backend - Migración

- [x] **Migración:** `add_password_management_to_users_table`
  ```php
  - must_change_password (boolean, default: false)
  - password_changed_at (datetime, nullable)
  - Verifica si columnas ya existen antes de crear
  ```

### Backend - Mail Classes

- [x] **WelcomeUserMail.php**
  - Enviar credenciales temporales a usuarios nuevos
  - Datos: email, password temporal, URL de login
  - Template: `resources/views/emails/welcome.blade.php`

- [x] **PasswordResetByAdminMail.php**
  - Notificar reset de contraseña por admin
  - Datos: password nueva (condicional), must_change_password flag
  - Template: `resources/views/emails/password-reset-admin.blade.php`

- [x] **ForgotPasswordMail.php**
  - Enviar link de recuperación con token
  - Datos: reset URL con token, expiración (60 minutos)
  - Template: `resources/views/emails/forgot-password.blade.php`

### Backend - Email Templates

- [x] **welcome.blade.php**
  - Diseño con gradiente azul (#2563EB → #1E40AF)
  - Panel de credenciales destacado
  - Alerta de seguridad para cambio obligatorio

- [x] **password-reset-admin.blade.php**
  - Condicional: muestra password solo si fue generada
  - Warning si debe cambiar en próximo login
  - Botón de acción para login

- [x] **forgot-password.blade.php**
  - Link de reset con token
  - Indicador de expiración (60 minutos)
  - Tips de seguridad

### Backend - Controllers

- [x] **PasswordController.php** - 5 endpoints
  ```php
  POST   /api/password/forgot          → Solicitar link de recuperación
  POST   /api/password/reset           → Restablecer con token
  POST   /api/password/change          → Cambio por usuario autenticado
  POST   /api/password/force-change    → Cambio forzado (primer login)
  POST   /api/users/{id}/reset-password → Admin reset con opciones
  ```
  
  **Validaciones:**
  - `forgotPassword`: email required|exists:users
  - `resetPassword`: token required, email required|exists, password min:8|confirmed
  - `changePassword`: current_password required, password min:8|confirmed
  - `forceChangePassword`: current_password required, password min:8|confirmed
  - `adminResetPassword`: action in:generate,manual,force_change_only, password required_if:action,manual

- [x] **AuthController.php** (modificado)
  - Login devuelve `must_change_password` field
  - Refresh devuelve `must_change_password` field
  - Me devuelve `must_change_password` field

- [x] **UserController.php** (modificado)
  - `store()` genera password aleatoria (12 caracteres)
  - Establece `must_change_password = true`
  - Envía `WelcomeUserMail` con credenciales
  - Elimina validación de password para creación

### Backend - Modelos

- [x] **User.php**
  - Añadir `must_change_password` a `$fillable`
  - Añadir `password_changed_at` a `$fillable`
  - Cast `must_change_password` como boolean
  - Cast `password_changed_at` como datetime

### Frontend - User Entity

- [x] **User.ts**
  - Añadir `must_change_password?: boolean` a interfaz

### Frontend - Páginas de Autenticación

- [x] **ForceChangePasswordPage.tsx**
  - Ruta: `/force-change-password`
  - Campos: password actual, nueva password, confirmar
  - POST `/api/password/force-change`
  - Recarga usuario con `useAuthStore().me()`
  - Diseño idéntico a LoginView (gradiente azul)

- [x] **ForgotPasswordPage.tsx**
  - Ruta: `/forgot-password`
  - Input email con validación
  - POST `/api/password/forgot`
  - Estado de éxito con tips de seguridad

- [x] **ResetPasswordPage.tsx**
  - Ruta: `/reset-password?token=xxx&email=xxx`
  - Valida token desde query params
  - Campos: nueva password, confirmar
  - POST `/api/password/reset`
  - Redirect a login tras éxito

### Frontend - Componentes Admin

- [x] **PasswordResetModal.tsx** ⭐ NUEVO
  - Modal estilo Amazon con 3 opciones:
    1. **Generar nueva** - Backend genera + envía email
    2. **Establecer manualmente** - Admin define password + email
    3. **Solo forzar cambio** - Mantiene actual, marca flag
  - Checkbox: "Requerir cambio en próximo login" (opciones 1 y 2)
  - Validaciones: password ≥8 chars en modo manual
  - POST `/api/users/{id}/reset-password`

### Frontend - Modificaciones

- [x] **LoginView.tsx**
  - Enlace "¿Olvidaste tu contraseña?" → `/forgot-password`
  - Redirección post-login si `must_change_password = true`
  - Toast informativo antes de redirect

- [x] **UserFormPage.tsx**
  - Ocultar campos de password al crear usuario
  - Mostrar alerta: "Se enviará email con credenciales"
  - Mantener campos de password al editar

- [x] **UserDetailPage.tsx**
  - Botón "Restablecer Contraseña" (naranja)
  - Integración de `PasswordResetModal`
  - Callback `onSuccess` recarga datos del usuario

### Frontend - Rutas

- [x] **index.tsx**
  - Ruta pública: `/forgot-password`
  - Ruta pública: `/reset-password`
  - Ruta pública: `/force-change-password`

### Frontend - Exports

- [x] **components/users/index.ts**
  - Export `PasswordResetModal`

### ✅ Criterios de Completitud

- [x] Creación de usuarios genera password automática ✅
- [x] Email de bienvenida se envía correctamente ✅
- [x] Primer login redirige a cambio forzado ✅
- [x] Flujo "Olvidé mi contraseña" completo ✅
- [x] Tokens de reset expiran en 60 minutos ✅
- [x] Admin puede resetear con 3 opciones ✅
- [x] Checkbox forzar cambio funciona ✅
- [x] Validaciones frontend y backend consistentes ✅
- [x] Templates de email estilizados ✅
- [x] Contraseñas hasheadas con bcrypt ✅
- [x] must_change_password se limpia después del cambio ✅

### 📚 Documentación

- [x] **PASSWORD_SYSTEM.md** (docs/)
  - Arquitectura completa (backend + frontend)
  - Diagramas de flujos con Mermaid
  - API endpoints documentados
  - Tablas de validaciones
  - Guía de testing
  - Comandos útiles para desarrollo
  - Instrucciones de deployment

### 🔒 Seguridad Implementada

- [x] Passwords hasheadas con `bcrypt`
- [x] Tokens de reset expiran automáticamente
- [x] Validación de password actual en cambios
- [x] Generación segura con `Str::random(12)`
- [x] Emails sanitizados con Blade
- [x] HttpOnly cookies (heredado de Módulo 1)

### 📊 Métricas

| Métrica | Valor |
|---------|-------|
| Archivos creados (Backend) | 10 |
| Archivos modificados (Backend) | 4 |
| Archivos creados (Frontend) | 4 |
| Archivos modificados (Frontend) | 5 |
| Endpoints nuevos | 5 |
| Email templates | 3 |
| Líneas de código total | ~1,500+ |

### 🧪 Testing Recomendado

- [ ] Crear usuario → Verificar email en logs ✅
- [ ] Primer login → Redirección a force-change ✅
- [ ] Cambio forzado → `must_change_password = false` ✅
- [ ] Forgot password → Token válido en email ✅
- [ ] Reset con token → Login con nueva password ✅
- [ ] Admin reset - Generar → Email enviado ✅
- [ ] Admin reset - Manual → Password funciona ✅
- [ ] Admin reset - Force only → Flag en próximo login ✅
- [ ] Checkbox force → Comportamiento correcto ✅

---


## 🏢 MÓDULO 2: MULTI-TENANCY

**Objetivo:** Sistema completo de gestión de múltiples organizaciones con usuarios compartidos.

**Duración estimada:** 4-5 días

**Dependencias:** Módulo 0 y Módulo 1 completados

### Backend - Controllers

- [ ] **TenantController.php**
  ```php
  GET    /api/tenants                    → listar (scope por user_tenants)
  POST   /api/tenants                    → crear (solo root)
  GET    /api/tenants/{id}               → detalle
  PUT    /api/tenants/{id}               → actualizar
  DELETE /api/tenants/{id}               → eliminar (solo root)
  GET    /api/tenants/{id}/users         → usuarios del tenant ⭐
  POST   /api/tenants/{id}/users         → agregar usuario ⭐
  DELETE /api/tenants/{id}/users/{uid}   → remover usuario ⭐
  ```
  - Validaciones: RUC único, nombre requerido
  - Aplicar TenantScope middleware

### Backend - Servicios

- [ ] **TenantService.php**
  ```php
  - assignUserToTenant($userId, $tenantId, $isPrimary)
  - removeUserFromTenant($userId, $tenantId)
  - getUserTenants($userId)
  - switchPrimaryTenant($userId, $tenantId)
  ```

### Frontend - Store

- [ ] **tenantsStore.ts**
  ```typescript
  interface TenantsState {
    tenants: Tenant[];
    currentTenant: Tenant | null;
    loading: boolean;
    
    fetchTenants(): Promise<void>;
    createTenant(data): Promise<Tenant>;
    updateTenant(id, data): Promise<Tenant>;
    deleteTenant(id): Promise<void>;
    
    // Gestión de usuarios ⭐
    getTenantUsers(tenantId): Promise<User[]>;
    addUserToTenant(tenantId, userId): Promise<void>;
    removeUserFromTenant(tenantId, userId): Promise<void>;
  }
  ```

### Frontend - Páginas

- [ ] **TenantsListPage.tsx**
  - Tabla con tenants (solo root ve todos)
  - Admin ve solo sus tenants
  - Botón "Crear Tenant" (solo root)
  - Filtros: estado, búsqueda por nombre/RUC

- [ ] **TenantFormPage.tsx**
  - Form para crear/editar tenant
  - Campos: name, ruc, business_name, address, phone, logo
  - Upload de logo
  - Validaciones

- [ ] **TenantDetailPage.tsx** ⭐
  - Información del tenant
  - Lista de usuarios asociados
  - Botón "Agregar Usuario"
  - Botón "Remover Usuario"
  - Solo root y admin del tenant pueden gestionar

### Frontend - Componentes

- [x] **TenantSwitcher.tsx** ✅ COMPLETADO
  - Dropdown para cambiar entre tenants
  - Muestra logo del tenant si está disponible
  - Tema oscuro (gray-800) para dropdown
  - Marca tenant primario con ★
  - Atajos de teclado (⌘1, ⌘2, ⌘3)
  - Solo visible para usuarios multi-tenant
  - Integrado en Navbar como logo principal

- [ ] **TenantCard.tsx**
  - Card con info resumida
  - Logo, nombre, RUC
  - Badge de estado

- [ ] **TenantUsersManager.tsx** ⭐ NUEVO
  - Tabla de usuarios del tenant
  - Botón para agregar usuarios existentes
  - Checkbox "Tenant Principal"
  - Botón remover usuario

- [ ] **AddUserToTenantModal.tsx** ⭐ NUEVO
  - Modal con lista de usuarios disponibles
  - Checkbox "Marcar como tenant principal"
  - Búsqueda de usuarios

### ✅ Criterios de Completitud
- [ ] Root puede crear/editar/eliminar cualquier tenant
- [ ] Admin solo ve y edita sus tenants
- [ ] Usuarios pueden ser asignados a múltiples tenants
- [ ] Cambio de tenant activo funciona correctamente
- [ ] Todas las queries respetan el tenant scope

---

## 👥 MÓDULO 3: GESTIÓN DE USUARIOS

**Objetivo:** CRUD completo de usuarios con asignación de roles, tenants y supervisores.

**Duración estimada:** 5-6 días  
**Estado:** ✅ 95% COMPLETADO (Backend 100%, Frontend 90%)

**Dependencias:** Módulos 0, 1 y 2 completados

### Backend - Controllers

- [x] **UserController.php** ✅ COMPLETADO
  ```php
  GET    /api/users                      → listar (scope por tenants) ✅
  POST   /api/users                      → crear ✅
  GET    /api/users/{id}                 → detalle ✅
  PUT    /api/users/{id}                 → actualizar ✅
  DELETE /api/users/{id}                 → soft delete ✅
  PUT    /api/users/{id}/supervisor      → asignar jefe ⭐ ✅
  GET    /api/users/{id}/subordinates    → listar subordinados ⭐ ✅
  ```
  - ✅ Paginación server-side implementada
  - ✅ Búsqueda por nombre, email, documento
  - ✅ Filtros por estado y tenant
  - ✅ Scope automático por tenants del usuario
  - ✅ Validación de ciclos en jerarquía de supervisores
  - ✅ Documentado con Swagger

### Backend - Validaciones

- [x] **Validaciones en UserController** ✅ COMPLETADO
  ```php
  - email: único, formato válido ✅
  - document_text: único ✅
  - document_type: enum válido (DNI, RUC, CE, Pasaporte) ✅
  - immediate_supervisor_id: existe en users ✅
  - password: mínimo 8 caracteres ✅
  - Prevención de ciclos en supervisión ✅
  ```

### Backend - Servicios

- [x] **Lógica en UserController** ✅ IMPLEMENTADO DIRECTAMENTE
  ```php
  - createUser($data, $tenantId) ✅
  - updateUser($id, $data) ✅
  - assignRole($userId, $roleId) ✅
  - assignSupervisor($userId, $supervisorId) ⭐ ✅
  - getSubordinates($userId) ⭐ ✅
  - assignToTenant($userId, $tenantId, $isPrimary) ✅
  ```
  **Nota:** Lógica implementada directamente en controller, no requiere service layer separado.

### Frontend - Store

- [x] **usersStore.ts** ✅ COMPLETADO
  ```typescript
  interface UsersState {
    users: User[];
    isLoading: boolean; ✅
    error: string | null; ✅
    pagination: PaginationMeta | null; ✅
    links: PaginationLinks | null; ✅
    
    fetchUsers(params?: GetUsersParams): Promise<void>; ✅
    createUser(data): Promise<User>; ✅
    updateUser(id, data): Promise<User>; ✅
    deleteUser(id): Promise<void>; ✅
    
    // Paginación ⭐
    goToPage(page: number): Promise<void>; ✅
    changePerPage(perPage: number): Promise<void>; ✅
    setSearch(search: string): Promise<void>; ✅
    setStatusFilter(status: string): Promise<void>; ✅
    
    getUsersByTenant(tenantId?: string): User[]; ✅
  }
  ```
  **Características:**
  - ✅ Paginación completa con meta y links
  - ✅ Búsqueda con debounce
  - ✅ Filtros por estado y tenant
  - ✅ Manejo de errores
  - ✅ Use cases pattern

### Frontend - Páginas

- [x] **UsersListPage.tsx** ✅ COMPLETADO
  - ✅ Tabla responsive con usuarios (scope por tenant actual)
  - ✅ Columnas: nombre, email, documento, rol, tenants, jefe inmediato ⭐, estado
  - ✅ Filtros: estado (active/inactive/suspended)
  - ✅ Búsqueda en tiempo real con debounce por nombre/email/documento
  - ✅ Paginación completa con controles (prev/next/números de página)
  - ✅ Selector de resultados por página (10/25/50/100)
  - ✅ Botón "Crear Usuario" (solo admin/root)
  - ✅ Acciones: Ver, Editar, Eliminar
  - ✅ Muestra supervisor inmediato
  - ✅ Muestra tenants con badge "★" para primario
  - ✅ Loading states y empty states

- [x] **UsersPage.tsx (con modales)** ✅ IMPLEMENTADO
  - ✅ Modal para crear usuario
  - ✅ Modal para editar usuario
  - ✅ Modal para cambiar rol
  - ✅ Campos: name, email, password, department
  - ✅ Selector de tenant (para platform admin)
  - ✅ Selector de rol (employee/manager/admin)
  - ✅ Validaciones frontend
  - ✅ Estadísticas (Total, Activos, Admins, Inactivos)
  - ⚠️ **Pendiente:** Formulario dedicado con immediate_supervisor_id

- [ ] **UserDetailPage.tsx**
  - Información completa del usuario
  - Card de datos personales
  - Card de tenants asignados ⭐
  - Card de subordinados (si tiene) ⭐
  - Card de documentos asociados
  - Botón editar/eliminar

### Frontend - Componentes

- [ ] **UserCard.tsx**
  - Avatar con iniciales
  - Nombre completo
  - Email y documento
  - Badges de roles

- [ ] **SupervisorSelector.tsx** ⭐ NUEVO
  - Select para elegir jefe inmediato
  - Solo muestra usuarios del mismo tenant
  - Filtra: no puede ser subordinado del usuario actual (evitar ciclos)
  - Puede ser NULL

- [ ] **SupervisorBadge.tsx** ⭐ NUEVO
  - Muestra jefe inmediato del usuario
  - Tooltip con datos del supervisor
  - Click → ir a perfil del supervisor

- [ ] **SubordinatesList.tsx** ⭐ NUEVO
  - Lista de subordinados directos
  - Indica si pueden aprobar vacaciones
  - Link a perfil de cada subordinado

- [ ] **UserTenantsManager.tsx** ⭐ NUEVO
  - Lista de tenants del usuario
  - Badge "Principal" en tenant primario
  - Botón agregar/remover tenant
  - Botón "Marcar como principal"

### ✅ Criterios de Completitud
- [x] CRUD de usuarios funciona correctamente ✅
- [x] Asignación de supervisor funciona sin ciclos ✅
- [x] Usuario puede pertenecer a múltiples tenants ✅
- [x] Filtros y búsqueda funcionan ✅
- [x] Validaciones frontend y backend consistentes ✅
- [x] Root ve todos los usuarios, admin solo de sus tenants ✅
- [x] Paginación server-side implementada ✅
- [x] Búsqueda con debounce ✅
- [x] Endpoints de supervisor y subordinados ✅

### ⚠️ Pendiente (5%)
- [ ] **SupervisorSelector.tsx** - Componente especializado
- [ ] **SupervisorBadge.tsx** - Badge con tooltip
- [ ] **SubordinatesList.tsx** - Lista de subordinados
- [ ] **UserTenantsManager.tsx** - Gestión de tenants
- [ ] **UserDetailPage.tsx** - Página de detalle completo
- [ ] **UserFormPage.tsx** - Formulario dedicado (actualmente usa modales)

---

## 📄 MÓDULO 4: DOCUMENTOS

**Objetivo:** Sistema completo de gestión documental con carga masiva, firma digital y documentos huérfanos.

**Duración estimada:** 10-12 días

**Dependencias:** Módulos 0, 1, 2 y 3 completados

### Backend - Instalación

- [ ] Instalar Laravel Horizon
  ```bash
  composer require laravel/horizon
  php artisan horizon:install
  php artisan migrate
  ```

- [ ] Configurar Redis como queue driver
  ```env
  QUEUE_CONNECTION=redis
  ```

- [ ] Configurar workers en `config/horizon.php`

### Backend - Controllers

- [ ] **DocumentController.php**
  ```php
  GET    /api/documents                       → listar (scope por tenant)
  POST   /api/documents/upload-zip            → carga masiva (dispatch Job)
  GET    /api/documents/{id}                  → detalle
  GET    /api/documents/{id}/download         → descargar (auditar)
  POST   /api/documents/{id}/sign             → firmar digitalmente
  GET    /api/documents/{id}/certificate      → certificado de firma
  GET    /api/documents/orphaned              → huérfanos (admin)
  POST   /api/documents/{id}/assign           → asignar huérfano a empleado
  GET    /api/documents/processing-status     → progreso de procesamiento
  DELETE /api/documents/{id}                  → soft delete
  ```

### Backend - Jobs

- [ ] **ProcessZipFile.php**
  ```php
  - Extraer archivos del ZIP
  - Validar formato de nombres (DNI_TIPO_PERIODO.pdf)
  - Dispatch ValidateEmployees para cada archivo
  - Logging de progreso
  ```

- [ ] **ValidateEmployees.php**
  ```php
  - Buscar empleado por document_number en tenant
  - Si existe: dispatch DistributeDocuments
  - Si no existe: dispatch CreateOrphanedDocuments
  ```

- [ ] **DistributeDocuments.php**
  ```php
  - Generar file_path: storage/documents/TENANT/DOC_NUMBER/TYPE/PERIOD/filename.pdf
  - Mover archivo a estructura correcta
  - Crear registro en tabla documents
  - Dispatch SendNotificationEmail
  - Evento: NewDocumentAvailable
  ```

- [ ] **CreateOrphanedDocuments.php**
  ```php
  - Crear registro con status='orphan'
  - Notificar admin del tenant
  - Guardar en carpeta temporal
  ```

- [ ] **SendNotificationEmail.php**
  ```php
  - Enviar email a empleado
  - Template: documento nuevo disponible
  - Link directo al documento
  - Si requiere firma: destacar
  ```

### Backend - Servicios

- [ ] **DocumentService.php**
  ```php
  - generateFilePath($tenant, $docNumber, $type, $period)
  - signDocument($documentId, $userId, $ip, $userAgent)
  - generateCertificate($documentId)
  - assignOrphanedDocument($documentId, $userId)
  - getProcessingStatus($batchId)
  ```

### Backend - Storage

- [ ] Configurar disk 'documents' en `config/filesystems.php`
  ```php
  'documents' => [
      'driver' => 'local',
      'root' => storage_path('app/documents'),
      'visibility' => 'private',
  ]
  ```

- [ ] Crear estructura de carpetas automáticamente

### Frontend - Store

- [ ] **documentsStore.ts**
  ```typescript
  interface DocumentsState {
    documents: Document[];
    orphanedDocuments: Document[];
    processingStatus: ProcessingStatus | null;
    loading: boolean;
    
    fetchDocuments(filters): Promise<void>;
    uploadZip(file, metadata): Promise<void>;
    downloadDocument(id): Promise<Blob>;
    signDocument(id): Promise<void>;
    
    // Huérfanos
    fetchOrphanedDocuments(): Promise<void>;
    assignOrphanedDocument(docId, userId): Promise<void>;
    
    // Procesamiento
    getProcessingStatus(batchId): Promise<ProcessingStatus>;
  }
  ```

### Frontend - Páginas

- [ ] **DocumentsListPage.tsx**
  - Tabla con documentos del tenant
  - Columnas: empleado, tipo, período, estado, fecha carga
  - Filtros: tipo, período, estado, empleado
  - Búsqueda por nombre empleado o documento
  - Badges de estado (pending, signed, expired, orphan)
  - Botón "Cargar Documentos" (admin)
  - Paginación

- [ ] **DocumentUploadPage.tsx**
  - Drag & drop para ZIP
  - Preview del contenido del ZIP
  - Select: tipo de documento
  - Select: período (mes/año)
  - Checkbox: "Requiere firma digital"
  - Botón "Procesar"
  - Modal de confirmación

- [ ] **DocumentProcessingPage.tsx**
  - Dashboard de procesamiento en tiempo real
  - Barra de progreso
  - Lista de archivos procesados (success/error)
  - Lista de empleados no encontrados
  - Métricas: procesados, errores, pendientes
  - WebSocket para actualización en vivo
  - Botón "Descargar Reporte"

- [ ] **OrphanedDocumentsPage.tsx**
  - Lista de documentos huérfanos
  - Tabla: nombre archivo, tipo, período, fecha carga
  - Botón "Asignar a Empleado"
  - Modal para buscar y seleccionar empleado
  - Filtros por tipo y período

- [ ] **DocumentDetailPage.tsx**
  - Visor PDF integrado
  - Información del documento
  - Estado de firma
  - Botón "Descargar"
  - Botón "Firmar" (si pending y es el empleado)
  - Historial de acciones (auditoría)

- [ ] **MyDocumentsPage.tsx** (vista empleado)
  - Solo documentos del usuario logueado
  - Filtros: tipo, período, firmado/sin firmar
  - Indicador visual de nuevos documentos
  - Botón "Firmar Pendientes"

### Frontend - Componentes

- [ ] **DocumentUploader.tsx**
  - Drag & drop zone con react-dropzone
  - Preview de archivos
  - Validación: solo ZIP, tamaño máximo
  - Progress bar durante upload

- [ ] **DocumentViewer.tsx**
  - Visor PDF con react-pdf
  - Controles: zoom, página siguiente/anterior
  - Fullscreen mode
  - Botón descarga

- [ ] **DocumentSignModal.tsx**
  - Modal de confirmación de firma
  - Términos y condiciones (primera vez)
  - Checkbox "He leído el documento"
  - Botón "Firmar Digitalmente"
  - Captura IP y geolocalización

- [ ] **ProcessingStatusCard.tsx**
  - Card con métricas de procesamiento
  - Progress bar animada
  - Lista de errores si hay

- [ ] **DocumentStatusBadge.tsx**
  - Badge con color según estado
  - pending (amarillo), signed (verde), expired (rojo), orphan (gris)

### ✅ Criterios de Completitud
- [x] Carga masiva de ZIP funciona ✅
- [x] Estructura de carpetas se crea automáticamente ✅
- [x] Jobs procesan documentos en background ✅
- [x] Documentos huérfanos se detectan correctamente ✅
- [ ] Firma digital guarda IP, timestamp y geolocalización (Backend listo, frontend pendiente)
- [ ] Empleados reciben email de documento nuevo (Pendiente)
- [x] Visor PDF funciona en frontend ✅ (react-pdf con navegación y zoom)
- [ ] Dashboard de procesamiento actualiza en tiempo real (Pendiente WebSockets)
- [ ] Auditoría registra todas las acciones (Parcial)

---

## 🏖️ MÓDULO 5: VACACIONES

**Objetivo:** Sistema completo de solicitud y aprobación de vacaciones con jefe inmediato.

**Duración estimada:** 6-8 días

**Dependencias:** Módulos 0, 1, 2 y 3 completados

### Backend - Controllers

- [ ] **VacationRequestController.php**
  ```php
  GET    /api/vacation-requests                   → listar (scope por tenant/usuario)
  POST   /api/vacation-requests                   → crear (envía a immediate_supervisor_id)
  GET    /api/vacation-requests/{id}              → detalle
  PUT    /api/vacation-requests/{id}/approve      → aprobar (solo supervisor) ⭐
  PUT    /api/vacation-requests/{id}/reject       → rechazar (solo supervisor) ⭐
  PUT    /api/vacation-requests/{id}/mark-taken   → marcar como tomadas ⭐
  PUT    /api/vacation-requests/{id}/mark-not-taken → marcar como NO tomadas ⭐
  DELETE /api/vacation-requests/{id}              → cancelar (solo si pending)
  GET    /api/vacation-requests/pending-approval  → pendientes de aprobar ⭐
  GET    /api/vacation-requests/not-taken         → aprobadas pero no tomadas ⭐
  GET    /api/vacation-requests/my-team           → solicitudes de mi equipo ⭐
  ```

### Backend - Jobs

- [ ] **SendVacationRequestEmail.php** ⭐
  ```php
  - Enviar email a immediate_supervisor_id (no lista de aprobadores)
  - Incluir datos: empleado, fechas, días, motivo
  - Link con token para aprobar/rechazar directo
  - Template personalizado
  ```

- [ ] **SendVacationStatusEmail.php**
  ```php
  - Notificar empleado de aprobación/rechazo
  - Incluir comentarios del supervisor
  - Notificar admin del tenant
  - Template según status
  ```

### Backend - Validaciones

- [ ] **VacationRequestRequest.php**
  ```php
  - start_date: fecha futura, no feriado
  - end_date: >= start_date
  - days_requested: >= 0.5
  - No solapamiento con vacaciones aprobadas del mismo usuario
  - Usuario debe tener immediate_supervisor_id asignado ⭐
  ```

### Backend - Servicios

- [ ] **VacationRequestService.php**
  ```php
  - createRequest($data, $userId, $tenantId)
  - approve($requestId, $approverId) → marca was_taken=TRUE ⭐
  - reject($requestId, $rejectorId, $reason)
  - markAsTaken($requestId) ⭐
  - markAsNotTaken($requestId) ⭐
  - calculateAvailableDays($userId, $year)
  - validateNonOverlapping($userId, $startDate, $endDate)
  - getRequestsForSupervisor($supervisorId) ⭐
  ```

### Frontend - Store

- [ ] **vacationRequestsStore.ts** ⭐ NUEVO
  ```typescript
  interface VacationRequestsState {
    requests: VacationRequest[];
    pendingApprovals: VacationRequest[];
    notTakenRequests: VacationRequest[];
    loading: boolean;
    
    fetchRequests(filters): Promise<void>;
    createRequest(data): Promise<VacationRequest>;
    approveRequest(id): Promise<void>;
    rejectRequest(id, reason): Promise<void>;
    cancelRequest(id): Promise<void>;
    
    // Control de vacaciones tomadas ⭐
    markAsTaken(id): Promise<void>;
    markAsNotTaken(id): Promise<void>;
    
    // Supervisores ⭐
    fetchPendingApprovals(): Promise<void>;
    fetchNotTakenRequests(): Promise<void>;
    fetchMyTeamRequests(): Promise<void>;
  }
  ```

### Frontend - Páginas

- [ ] **VacationRequestsListPage.tsx** (vista empleado)
  - Tabla de mis solicitudes
  - Columnas: fechas, días, estado, aprobador, fecha aprobación, tomadas ⭐
  - Botón "Nueva Solicitud"
  - Filtros: estado, año
  - Badge de estado con color
  - Indicador "Tomadas" / "No Tomadas" ⭐

- [ ] **VacationRequestFormPage.tsx**
  - Date picker: fecha inicio
  - Date picker: fecha fin
  - Cálculo automático de días
  - Textarea: motivo
  - Mostrar días disponibles restantes
  - Validación: no solapar con aprobadas
  - Mensaje: "Se enviará a [Nombre del Supervisor]" ⭐

- [ ] **VacationApprovalsPage.tsx** ⭐ (vista supervisor)
  - Tabla de solicitudes pendientes de MI EQUIPO ⭐
  - Columnas: empleado, fechas, días, motivo
  - Botón "Aprobar" y "Rechazar"
  - Contador de pendientes en navbar
  - Badge rojo con número
  - Filtros: empleado, fecha solicitud

- [ ] **VacationNotTakenPage.tsx** ⭐ NUEVO
  - Lista de vacaciones aprobadas pero NO tomadas
  - Tabla: empleado, fechas, días, fecha aprobación
  - Botón "Marcar como Tomadas"
  - Filtros: empleado, año, tenant (admin)
  - Exportar a Excel

- [ ] **VacationDetailPage.tsx**
  - Información completa de la solicitud
  - Timeline de estados
  - Datos del supervisor ⭐
  - Motivo, fechas, días
  - Comentarios del aprobador (si rechazado)
  - Estado "Tomadas" con toggle ⭐

### Frontend - Componentes

- [ ] **VacationRequestCard.tsx**
  - Card resumida de solicitud
  - Fechas, días, estado
  - Aprobador con avatar ⭐
  - Badge "Tomadas" / "No Tomadas" ⭐

- [ ] **VacationRequestForm.tsx**
  - Form completo de solicitud
  - Date range picker
  - Cálculo automático de días laborables
  - Validación: días disponibles
  - Preview de fechas

- [ ] **VacationApprovalModal.tsx**
  - Modal de confirmación de aprobación
  - Datos de la solicitud
  - Textarea: comentarios (opcional)
  - Checkbox "Marcar como tomadas" (default TRUE) ⭐
  - Botón confirmar

- [ ] **VacationRejectModal.tsx**
  - Modal de rechazo
  - Textarea: motivo del rechazo (obligatorio)
  - Botón confirmar

- [ ] **VacationStatusBadge.tsx** ⭐ ACTUALIZADO
  - Badge con estado
  - pending (amarillo), approved (verde), rejected (rojo), cancelled (gris)
  - Sub-badge "Tomadas" (verde) / "No Tomadas" (naranja) ⭐

- [ ] **SupervisorCard.tsx** ⭐ NUEVO
  - Card con info del supervisor
  - Avatar, nombre, email
  - Usado en detalle de solicitud

- [ ] **MyTeamVacationsWidget.tsx** ⭐ NUEVO
  - Widget para dashboard de supervisor
  - Muestra pendientes de aprobación
  - Contador de subordinados
  - Link a página completa

### ✅ Criterios de Completitud
- [ ] Solicitud se envía automáticamente al immediate_supervisor_id
- [ ] Solo el supervisor puede aprobar/rechazar
- [ ] Al aprobar, was_taken se marca TRUE por defecto
- [ ] Admin puede marcar vacaciones como tomadas/no tomadas
- [ ] Reporte de vacaciones no tomadas funciona
- [ ] Emails de notificación se envían correctamente
- [ ] Validación de días disponibles funciona
- [ ] No permite solapamiento de fechas
- [ ] WebSocket notifica cambios en tiempo real

---

## 🔔 MÓDULO 6: NOTIFICACIONES EN TIEMPO REAL

**Objetivo:** Sistema de notificaciones en tiempo real con Laravel Reverb y WebSockets.

**Duración estimada:** 5-6 días

**Dependencias:** Módulos 1, 4 y 5 completados

### Backend - Instalación

- [ ] Instalar Laravel Reverb
  ```bash
  composer require laravel/reverb
  php artisan reverb:install
  php artisan migrate
  ```

- [ ] Configurar broadcasting en `.env`
  ```env
  BROADCAST_DRIVER=reverb
  REVERB_APP_ID=your-app-id
  REVERB_APP_KEY=your-key
  REVERB_APP_SECRET=your-secret
  ```

- [ ] Iniciar servidor Reverb
  ```bash
  php artisan reverb:start
  ```

### Backend - Eventos

- [ ] **DocumentProcessed**
  ```php
  - Broadcast cuando termina procesamiento de ZIP
  - Canal: tenant.{tenantId}.admin
  - Data: totalProcessed, errors, orphaned
  ```

- [ ] **NewDocumentAvailable**
  ```php
  - Broadcast cuando hay documento nuevo para empleado
  - Canal: user.{userId}
  - Data: document, requires_signature
  ```

- [ ] **DocumentSigned**
  ```php
  - Broadcast cuando empleado firma documento
  - Canal: tenant.{tenantId}.admin
  - Data: document, user, signed_at
  ```

- [ ] **VacationRequestCreated** ⭐
  ```php
  - Broadcast a supervisor cuando llega solicitud
  - Canal: user.{supervisorId}
  - Data: vacation_request, employee
  ```

- [ ] **VacationRequestApproved**
  ```php
  - Broadcast a empleado cuando aprueban
  - Canal: user.{userId}
  - Data: vacation_request, approver, was_taken ⭐
  ```

- [ ] **VacationRequestRejected**
  ```php
  - Broadcast a empleado cuando rechazan
  - Canal: user.{userId}
  - Data: vacation_request, rejector, reason
  ```

- [ ] **ProcessingError**
  ```php
  - Broadcast cuando hay error crítico
  - Canal: tenant.{tenantId}.admin
  - Data: error, job, context
  ```

### Backend - Listeners

- [ ] Asociar eventos con listeners
- [ ] Crear notificación en DB cuando llega evento
- [ ] Enviar email según preferencias del usuario

### Backend - Controllers

- [ ] **NotificationController.php**
  ```php
  GET    /api/notifications              → mis notificaciones (scope por tenant)
  GET    /api/notifications/unread-count → contador
  PUT    /api/notifications/{id}/read    → marcar como leída
  DELETE /api/notifications/{id}         → eliminar
  POST   /api/notifications/mark-all-read → marcar todas
  ```

### Frontend - Instalación

- [ ] Instalar Laravel Echo
  ```bash
  npm install laravel-echo pusher-js
  ```

- [ ] Configurar Echo en `bootstrap.ts`
  ```typescript
  import Echo from 'laravel-echo';
  import Pusher from 'pusher-js';
  
  window.Pusher = Pusher;
  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
  });
  ```

### Frontend - Store

- [ ] **notificationsStore.ts**
  ```typescript
  interface NotificationsState {
    notifications: Notification[];
    unreadCount: number;
    loading: boolean;
    
    fetchNotifications(): Promise<void>;
    markAsRead(id): Promise<void>;
    markAllAsRead(): Promise<void>;
    deleteNotification(id): Promise<void>;
    
    // WebSocket
    incrementUnread(): void;
    addNotification(notification): void;
  }
  ```

### Frontend - Hooks

- [ ] **useWebSocket.ts**
  ```typescript
  useEffect(() => {
    // Conectar a canal privado del usuario
    Echo.private(`user.${user.id}`)
      .listen('NewDocumentAvailable', (e) => {
        // Mostrar toast
        // Incrementar contador
        // Agregar a lista
      })
      .listen('VacationRequestApproved', (e) => { ... })
      .listen('VacationRequestRejected', (e) => { ... });
    
    // Conectar a canal del tenant (si admin)
    if (isAdmin) {
      Echo.private(`tenant.${tenantId}.admin`)
        .listen('DocumentProcessed', (e) => { ... })
        .listen('DocumentSigned', (e) => { ... });
    }
    
    return () => {
      Echo.leave(`user.${user.id}`);
      if (isAdmin) Echo.leave(`tenant.${tenantId}.admin`);
    };
  }, [user, tenantId]);
  ```

### Frontend - Componentes

- [ ] **NotificationCenter.tsx**
  - Dropdown en navbar
  - Badge con contador de no leídas
  - Lista de notificaciones recientes
  - Botón "Ver todas"
  - Botón "Marcar todas como leídas"

- [ ] **NotificationItem.tsx**
  - Item de notificación
  - Icono según tipo
  - Título y mensaje
  - Timestamp relativo
  - Click → marcar como leída y navegar

- [ ] **NotificationsList.tsx**
  - Lista completa de notificaciones
  - Filtros: leídas/no leídas, tipo
  - Paginación
  - Borrar notificación

- [ ] **ToastNotification.tsx**
  - Toast animado para notificaciones nuevas
  - Auto-dismiss en 5 segundos
  - Sonido opcional
  - Click → navegar a recurso relacionado

### Frontend - Integración

- [ ] Agregar listener en `App.tsx`
- [ ] Conectar WebSocket al hacer login
- [ ] Desconectar al hacer logout
- [ ] Manejo de reconexión automática
- [ ] Fallback a polling si WebSocket falla

### ✅ Criterios de Completitud
- [ ] WebSocket conecta correctamente
- [ ] Notificaciones llegan en tiempo real
- [ ] Toast aparece al recibir notificación
- [ ] Contador de no leídas actualiza automáticamente
- [ ] Centro de notificaciones funciona
- [ ] Marcar como leída funciona
- [ ] Reconexión automática funciona
- [ ] Sonido de notificación (opcional) funciona

---

## 📊 MÓDULO 7: REPORTES Y AUDITORÍA

**Objetivo:** Sistema de reportes, dashboards y auditoría completa del sistema.

**Duración estimada:** 5-6 días

**Dependencias:** Todos los módulos anteriores

### Backend - Instalación

- [ ] Instalar Maatwebsite Excel
  ```bash
  composer require maatwebsite/excel
  ```

### Backend - Controllers

- [ ] **ReportController.php**
  ```php
  GET /api/reports/documents              → reporte de documentos
  GET /api/reports/vacations              → reporte de vacaciones ⭐
  GET /api/reports/vacations-not-taken    → vacaciones no tomadas ⭐
  GET /api/reports/users                  → reporte de usuarios
  GET /api/reports/dashboard              → métricas para dashboard
  
  // Exportaciones
  GET /api/reports/documents/export       → Excel de documentos
  GET /api/reports/vacations/export       → Excel de vacaciones
  GET /api/reports/audit/export           → Excel de auditoría
  ```

- [ ] **AuditLogController.php**
  ```php
  GET /api/audit-logs → consultar logs (solo root/admin)
  ```
  - Filtros: user, model, action, date_range, tenant

### Backend - Exports

- [ ] **DocumentsExport.php**
  - Exportar documentos filtrados a Excel
  - Columnas personalizables

- [ ] **VacationsExport.php** ⭐
  - Exportar vacaciones a Excel
  - Incluir columna `was_taken`
  - Totales por empleado

- [ ] **AuditLogsExport.php**
  - Exportar logs de auditoría
  - Incluir cambios (old_values, new_values)

### Backend - Servicios

- [ ] **DashboardService.php**
  ```php
  - getTenantMetrics($tenantId)
  - getDocumentStats($tenantId, $period)
  - getVacationStats($tenantId, $year) ⭐
  - getUserActivity($userId)
  ```

### Frontend - Store

- [ ] **reportsStore.ts**
  ```typescript
  interface ReportsState {
    dashboardData: DashboardData | null;
    loading: boolean;
    
    fetchDashboardData(tenantId): Promise<void>;
    exportDocuments(filters): Promise<Blob>;
    exportVacations(filters): Promise<Blob>;
    exportAuditLogs(filters): Promise<Blob>;
  }
  ```

### Frontend - Páginas

- [ ] **AdminDashboardPage.tsx**
  - Métricas generales del tenant
  - Card: Total documentos
  - Card: Documentos pendientes de firma
  - Card: Solicitudes de vacaciones pendientes
  - Card: Vacaciones no tomadas ⭐
  - Gráfico: Documentos por mes (línea)
  - Gráfico: Documentos por tipo (pie)
  - Gráfico: Vacaciones aprobadas vs tomadas (barras) ⭐
  - Tabla: Últimas actividades

- [ ] **EmployeeDashboardPage.tsx**
  - Métricas personales
  - Card: Mis documentos totales
  - Card: Documentos pendientes de firma
  - Card: Días de vacaciones disponibles
  - Card: Vacaciones tomadas este año
  - Lista: Documentos recientes
  - Lista: Mis solicitudes de vacaciones

- [ ] **ReportsPage.tsx**
  - Tabs: Documentos, Vacaciones, Usuarios, Auditoría
  - Filtros avanzados por tab
  - Preview de datos
  - Botón "Exportar a Excel"
  - Date range picker
  - Scope por tenant

- [ ] **DocumentsReportPage.tsx**
  - Filtros: tipo, período, estado, empleado
  - Tabla con resultados
  - Totales: documentos, firmados, pendientes
  - Gráficos de distribución
  - Exportar Excel

- [ ] **VacationsReportPage.tsx** ⭐
  - Filtros: año, estado, empleado, tomadas/no tomadas ⭐
  - Tabla: empleado, fechas, días, estado, tomadas ⭐
  - Totales por empleado
  - Gráfico: vacaciones por mes
  - Exportar Excel con columna `was_taken` ⭐

- [ ] **VacationsNotTakenReportPage.tsx** ⭐ NUEVO
  - Lista de vacaciones aprobadas pero NO tomadas
  - Filtros: empleado, año, tenant
  - Alertas para vacaciones próximas a vencer
  - Exportar Excel

- [ ] **AuditLogsPage.tsx**
  - Solo root y admin
  - Tabla de logs
  - Filtros: usuario, acción, modelo, fecha
  - Ver cambios (diff de old_values y new_values)
  - Exportar Excel

### Frontend - Componentes

- [ ] **DashboardCard.tsx**
  - Card con métrica
  - Icono, título, valor
  - Porcentaje de cambio
  - Link a detalle

- [ ] **MetricsChart.tsx**
  - Gráficos con Recharts
  - Line chart, Bar chart, Pie chart
  - Responsive
  - Tooltips

- [ ] **ReportFilters.tsx**
  - Componente de filtros avanzados
  - Date range, selects, búsqueda
  - Botón aplicar/limpiar

- [ ] **ExportButton.tsx**
  - Botón con loading state
  - Descarga automática del archivo
  - Manejo de errores

- [ ] **ActivityTimeline.tsx**
  - Timeline de actividades recientes
  - Iconos por tipo de acción
  - Timestamps relativos

### ✅ Criterios de Completitud
- [ ] Dashboard muestra métricas correctas
- [ ] Gráficos se renderizan correctamente
- [ ] Filtros de reportes funcionan
- [ ] Exportación a Excel funciona
- [ ] Reporte de vacaciones no tomadas funciona ⭐
- [ ] Auditoría registra todas las acciones críticas
- [ ] Solo usuarios autorizados ven logs de auditoría

---

## 🚀 MÓDULO 8: TESTING Y DEPLOYMENT

**Objetivo:** Testing completo y preparación para producción.

**Duración estimada:** 6-8 días

**Dependencias:** Todos los módulos anteriores

### Backend - Testing

- [ ] **Feature Tests - Auth**
  ```php
  - test_user_can_login
  - test_user_cannot_login_with_wrong_credentials
  - test_user_can_logout
  - test_me_endpoint_returns_user_with_tenants ⭐
  - test_rate_limiting_works
  ```

- [ ] **Feature Tests - Tenants**
  ```php
  - test_root_can_create_tenant
  - test_admin_cannot_create_tenant
  - test_user_can_access_only_assigned_tenants ⭐
  - test_user_can_switch_tenant ⭐
  ```

- [ ] **Feature Tests - Users**
  ```php
  - test_admin_can_create_user
  - test_supervisor_assignment_works ⭐
  - test_supervisor_cannot_be_subordinate ⭐
  - test_user_can_belong_to_multiple_tenants ⭐
  ```

- [ ] **Feature Tests - Documents**
  ```php
  - test_zip_upload_processes_correctly
  - test_orphaned_documents_are_detected
  - test_document_sign_captures_metadata
  - test_file_path_structure_is_correct ⭐
  ```

- [ ] **Feature Tests - Vacations**
  ```php
  - test_vacation_request_goes_to_immediate_supervisor ⭐
  - test_only_supervisor_can_approve ⭐
  - test_approve_marks_was_taken_true ⭐
  - test_can_mark_as_taken_or_not_taken ⭐
  - test_validation_prevents_overlapping
  ```

- [ ] **Unit Tests - Models**
  ```php
  - test_user_relationships
  - test_document_file_path_generation
  - test_vacation_request_calculations
  - test_role_permissions
  ```

- [ ] **Unit Tests - Services**
  ```php
  - test_tenant_service_methods
  - test_vacation_service_methods
  - test_document_service_methods
  ```

### Frontend - Testing

- [ ] Configurar Jest y Testing Library
  ```bash
  npm install -D @testing-library/react @testing-library/jest-dom vitest
  ```

- [ ] **Component Tests**
  ```typescript
  - LoginPage.test.tsx
  - TenantSwitcher.test.tsx ⭐
  - SupervisorSelector.test.tsx ⭐
  - VacationStatusBadge.test.tsx ⭐
  - DocumentUploader.test.tsx
  - NotificationCenter.test.tsx
  ```

- [ ] **Store Tests**
  ```typescript
  - authStore.test.ts
  - tenantsStore.test.ts
  - vacationRequestsStore.test.ts ⭐
  - documentsStore.test.ts
  ```

- [ ] **E2E Tests con Playwright**
  ```typescript
  - Login flow
  - Create user with supervisor ⭐
  - Upload documents
  - Request vacation
  - Approve vacation as supervisor ⭐
  - Mark vacation as taken/not taken ⭐
  - Switch tenant ⭐
  ```

### Backend - Optimizaciones

- [ ] Implementar cache de consultas frecuentes
- [ ] Optimizar queries N+1 con eager loading
- [ ] Índices de base de datos completos
- [ ] Compresión de respuestas (gzip)
- [ ] Rate limiting en todas las APIs
- [ ] HTTPS enforced

### Backend - Seguridad

- [ ] CSRF protection habilitado
- [ ] XSS protection
- [ ] SQL injection prevention (usar Eloquent)
- [ ] File upload validation (tipo, tamaño)
- [ ] Encriptación de datos sensibles
- [ ] Headers de seguridad configurados

### DevOps - Docker

- [ ] Dockerfile optimizado
- [ ] Docker Compose para desarrollo
- [ ] Docker Compose para producción
- [ ] Multi-stage builds
- [ ] Health checks

### DevOps - CI/CD

- [ ] GitHub Actions workflow
  ```yaml
  - Run tests on PR
  - Build Docker image
  - Deploy to staging
  - Deploy to production (manual approval)
  ```

- [ ] Configurar entornos
  - Development
  - Staging
  - Production

### DevOps - Monitoring

- [ ] Configurar Laravel Telescope (desarrollo)
- [ ] Configurar Sentry para error tracking
- [ ] Logs centralizados
- [ ] Health check endpoint
- [ ] Uptime monitoring

### Documentación

- [ ] README.md actualizado
- [ ] Documentación de API (Swagger/OpenAPI)
- [ ] Manual técnico de arquitectura
- [ ] Manual de instalación
- [ ] Manual de usuario (con capturas)
- [ ] Guía de troubleshooting
- [ ] Documentación de variables de entorno

### ✅ Criterios de Completitud
- [ ] Coverage de tests >= 70%
- [ ] Todos los tests pasan
- [ ] No hay vulnerabilidades críticas
- [ ] Performance aceptable (< 200ms respuesta API)
- [ ] Docker funciona en producción
- [ ] CI/CD pipeline funciona
- [ ] Documentación completa
- [ ] Monitoring configurado

---

## 📊 RESUMEN EJECUTIVO

### Estimación de Tiempo por Módulo

| Módulo | Backend | Frontend | Testing | Total |
|--------|---------|----------|---------|-------|
| **0. Base de Datos** | 5-6 días | - | 1 día | 6-7 días |
| **1. Auth** | 3-4 días | 2-3 días | 1 día | 6-8 días |
| **2. Multi-Tenancy** | 2-3 días | 2 días | 1 día | 5-6 días |
| **3. Usuarios** | 3-4 días | 2-3 días | 1 día | 6-8 días |
| **4. Documentos** | 6-7 días | 4-5 días | 2 días | 12-14 días |
| **5. Vacaciones** | 4-5 días | 3-4 días | 1 día | 8-10 días |
| **6. Notificaciones** | 3 días | 2-3 días | 1 día | 6-7 días |
| **7. Reportes** | 3 días | 2-3 días | 1 día | 6-7 días |
| **8. Testing/Deploy** | 3 días | 2 días | 3 días | 8 días |
| **TOTAL** | **32-37 días** | **19-24 días** | **12 días** | **63-75 días** |

### Tiempo Total Estimado
- **Desarrollo:** 63-75 días laborables
- **Calendario:** 12-15 semanas (3-4 meses)
- **Equipo:** 1 desarrollador full-stack

### Plan de Ejecución Recomendado

**Mes 1: Fundamentos**
- Semana 1-2: Módulo 0 + Módulo 1
- Semana 3: Módulo 2
- Semana 4: Módulo 3

**Mes 2: Features Core**
- Semana 5-6: Módulo 4 (parte 1)
- Semana 7: Módulo 4 (parte 2)
- Semana 8: Módulo 5

**Mes 3: Features Avanzados**
- Semana 9: Módulo 6
- Semana 10: Módulo 7
- Semana 11-12: Módulo 8

### Prioridades de Implementación

**🔴 CRÍTICO (Mes 1)**
- Base de datos completa
- Autenticación y autorización
- Multi-tenancy básico
- CRUD de usuarios

**🟡 IMPORTANTE (Mes 2)**
- Sistema de documentos completo
- Módulo de vacaciones
- Jobs y procesamiento

**🟢 COMPLEMENTARIO (Mes 3)**
- WebSockets y notificaciones
- Reportes y dashboards
- Testing completo
- Deployment

---

**Documento generado:** 4 de diciembre de 2025  
**Próxima revisión:** Al completar cada módulo

### ✅ COMPLETADO
- [x] Modelado de base de datos actualizado
- [x] Tabla `user_tenants` para multi-tenancy
- [x] Campo `immediate_supervisor_id` en users
- [x] Campo `was_taken` en vacation_requests
- [x] Estructura de carpetas de documentos definida
- [x] Diagrama Mermaid actualizado

### 🔴 PENDIENTE - Migraciones Laravel

- [ ] **Migración 01:** `create_tenants_table`
  - Campos: id, name, ruc (UK), business_name, address, phone, logo_path, status, timestamps, soft deletes
  
- [ ] **Migración 02:** `create_users_table` (modificar la existente)
  - Agregar: document_type (enum), document_text (UK), name, last_name, phone
  - Agregar: immediate_supervisor_id (FK self-reference)
  - Quitar: tenant_id (movido a user_tenants)
  - Quitar: is_vacation_approver (reemplazado por immediate_supervisor_id)
  
- [ ] **Migración 03:** `create_roles_table`
  - Campos: id, name (UK), display_name, description, permissions (json), guard_name, timestamps
  
- [ ] **Migración 04:** `create_user_roles_table`
  - Campos: id, user_id (FK), role_id (FK), granted_by (FK), granted_at, timestamps
  - UNIQUE(user_id, role_id)
  
- [ ] **Migración 05:** `create_user_tenants_table` ⭐ NUEVA
  - Campos: id, user_id (FK), tenant_id (FK), is_primary (bool), timestamps
  - UNIQUE(user_id, tenant_id)
  - Índices: tenant_id, is_primary
  
- [ ] **Migración 06:** `create_document_types_table`
  - Campos: id, name (UK), display_name, description, requires_signature, is_active, timestamps
  
- [ ] **Migración 07:** `create_documents_table`
  - Campos: id, tenant_id (FK), user_id (FK nullable), employee_document_number
  - doc_type_id (FK), period, file_path, file_size, original_name
  - status (enum), uploaded_by (FK), signature (json), signed_at, expires_at
  - timestamps, soft deletes
  - UNIQUE(tenant_id, employee_document_number, doc_type_id, period)
  
- [ ] **Migración 08:** `create_vacation_requests_table`
  - Campos: id, user_id (FK), tenant_id (FK), year, start_date, end_date
  - days_requested (decimal), reason, status (enum)
  - approved_by (FK), approved_at, rejected_by (FK), rejected_at, rejected_reason
  - **was_taken (boolean, default FALSE)** ⭐ NUEVO
  - timestamps
  
- [ ] **Migración 09:** `create_notifications_table`
  - Campos: id, user_id (FK), tenant_id (FK), actor_id (FK)
  - related_type, related_id, type, title, message, action_url
  - is_read, read_at, timestamps
  
- [ ] **Migración 10:** `create_audit_logs_table`
  - Campos: id, user_id (FK nullable), tenant_id (FK nullable), action
  - model, model_id, old_values (json), new_values (json)
  - ip_address, user_agent, created_at

---

## 🔴 PRIORIDAD ALTA - MODELOS LARAVEL

### Modelos Base

- [ ] **Tenant.php**
  - Relaciones: users (via user_tenants), documents, vacation_requests, notifications, audit_logs
  - Scopes: active, suspended
  - Accessors: logo_url
  
- [ ] **User.php** (modificar existente)
  - Traits: HasApiTokens, Notifiable, SoftDeletes
  - Relaciones: 
    - tenants (via user_tenants) ⭐ NUEVO
    - roles (via user_roles)
    - immediateSupervior (belongsTo User) ⭐ NUEVO
    - subordinates (hasMany User) ⭐ NUEVO
    - documents, vacation_requests, notifications, uploaded_documents
  - Métodos: 
    - hasRole($role), isRoot(), isAdmin(), isClient()
    - getTenants(), getPrimaryTenant() ⭐ NUEVO
    - canApproveVacations() → verifica si tiene subordinados ⭐ NUEVO
  - Accessors: full_name, document_number
  
- [ ] **Role.php**
  - Relaciones: users (via user_roles)
  - Métodos: hasPermission($permission)
  - Seeders: 3 roles fijos (root, admin, client)
  
- [ ] **UserRole.php** (pivot)
  - Relaciones: user, role, granted_by_user
  
- [ ] **UserTenant.php** (pivot) ⭐ NUEVO
  - Relaciones: user, tenant
  - Métodos: setPrimary() → marca como tenant principal
  
- [ ] **DocumentType.php**
  - Relaciones: documents
  - Scopes: active, requiresSignature
  - Seeders: 8 tipos (boleta, liquidacion, cts, gratificacion, utilidades, vacaciones, contrato, addendum)
  
- [ ] **Document.php**
  - Relaciones: tenant, user, documentType, uploader
  - Scopes: pending, signed, expired, orphan, byPeriod, byEmployee
  - Métodos: 
    - generateFilePath() → storage/documents/TENANT/DOC_NUMBER/TYPE/PERIOD/filename.pdf
    - sign($user, $ip, $userAgent) → firma digital
    - isExpired(), canBeSignedBy($user)
  - Accessors: file_url, status_label
  
- [ ] **VacationRequest.php**
  - Relaciones: user, tenant, approver, rejector
  - Scopes: pending, approved, rejected, byYear, byUser
  - Métodos:
    - approve($approverId) → marca status=approved, was_taken=TRUE ⭐ ACTUALIZADO
    - reject($rejectorId, $reason)
    - markAsTaken() → marca was_taken=TRUE ⭐ NUEVO
    - markAsNotTaken() → marca was_taken=FALSE ⭐ NUEVO
    - calculateDays() → calcula días entre fechas
  - Accessors: status_label, is_past_due
  
- [ ] **Notification.php**
  - Relaciones: user, tenant, actor, related (polymorphic)
  - Scopes: unread, byUser, byType
  - Métodos: markAsRead()
  
- [ ] **AuditLog.php**
  - Relaciones: user, tenant
  - Scopes: byUser, byModel, byAction, lastWeek, lastMonth

---

## 🟡 PRIORIDAD MEDIA - SEEDERS

- [ ] **RoleSeeder**
  - 3 roles: root, admin, client
  - Con permisos JSON definidos
  
- [ ] **DocumentTypeSeeder**
  - 8 tipos de documentos
  - Configurar cuáles requieren firma
  
- [ ] **DevelopmentSeeder** (solo desarrollo)
  - 1 usuario root
  - 2 tenants de prueba
  - 5 usuarios (1 root, 2 admins, 2 empleados)
  - Asignar immediate_supervisor_id ⭐
  - Relaciones user_tenants ⭐
  - 10 documentos de ejemplo
  - 5 solicitudes de vacaciones (con was_taken variado) ⭐

---

## 🔴 PRIORIDAD ALTA - AUTENTICACIÓN Y AUTORIZACIÓN

### Backend - Laravel Sanctum

- [ ] Instalar Sanctum: `composer require laravel/sanctum`
- [ ] Publicar configuración y ejecutar migraciones
- [ ] Configurar CORS en `config/cors.php`
- [ ] Configurar dominios stateful en `config/sanctum.php`
- [ ] Agregar trait `HasApiTokens` a modelo User
- [ ] Crear `AuthController`:
  - `POST /api/auth/login` → autenticación con cookies
  - `POST /api/auth/logout` → cerrar sesión
  - `GET /api/auth/me` → usuario actual con roles y tenants ⭐
  - `POST /api/auth/refresh` → refrescar CSRF token
- [ ] Rate limiting en login (5 intentos/minuto)
- [ ] Logging de intentos fallidos

### Middleware

- [ ] **CheckRole.php**
  - Verificar si usuario tiene rol(es) requerido(s)
  - Root siempre pasa
  
- [ ] **TenantScope.php** ⭐ ACTUALIZADO
  - Filtrar queries por tenant_id del contexto actual
  - Considerar tabla user_tenants para verificar acceso
  - Excepto para rol root
  
- [ ] **EnsureTenantAccess.php** ⭐ NUEVO
  - Verificar que el usuario pertenece al tenant solicitado
  - Consultar tabla user_tenants

### Policies

- [ ] **TenantPolicy**
  - Solo root y usuarios con acceso (user_tenants) pueden gestionar
  
- [ ] **UserPolicy**
  - Root ve todo
  - Admin ve usuarios de sus tenants (via user_tenants) ⭐
  - Client solo su perfil
  
- [ ] **DocumentPolicy**
  - Root ve todo
  - Admin ve documentos de sus tenants ⭐
  - Client solo sus propios documentos
  
- [ ] **VacationRequestPolicy**
  - Root ve todo
  - Admin ve solicitudes de sus tenants ⭐
  - Supervisores (immediate_supervisor_id) pueden aprobar/rechazar ⭐ NUEVO
  - Client solo sus propias solicitudes

### Frontend - Auth Store

- [ ] Actualizar `authStore.ts`:
  - Eliminar campo `token` (usar cookies)
  - Método `login()`: GET csrf-cookie + POST login
  - Método `logout()`: POST logout + limpiar estado
  - Método `me()`: GET usuario con tenants ⭐
  - Método `switchTenant(tenantId)`: cambiar tenant activo ⭐ NUEVO
  - Campo `currentTenant` en store ⭐ NUEVO
  - Guardar currentTenant en localStorage
  
- [ ] Configurar `apiClient.ts`:
  - `withCredentials: true`
  - Interceptor 401 → redirect a login
  - Interceptor 403 → mostrar error
  - Header `X-Current-Tenant` en todas las requests ⭐ NUEVO

---

## 🔴 PRIORIDAD ALTA - CONTROLLERS Y API

### TenantController

- [ ] `GET /api/tenants` → listar (solo root o usuarios con acceso) ⭐
- [ ] `POST /api/tenants` → crear (solo root)
- [ ] `GET /api/tenants/{id}` → detalle (verificar acceso via user_tenants) ⭐
- [ ] `PUT /api/tenants/{id}` → actualizar (solo root o admin del tenant) ⭐
- [ ] `DELETE /api/tenants/{id}` → eliminar (solo root)
- [ ] `GET /api/tenants/{id}/users` → usuarios del tenant ⭐ NUEVO
- [ ] `POST /api/tenants/{id}/users` → agregar usuario al tenant ⭐ NUEVO
- [ ] `DELETE /api/tenants/{id}/users/{userId}` → remover usuario del tenant ⭐ NUEVO

### UserController

- [ ] `GET /api/users` → listar (scope por tenants del usuario) ⭐
- [ ] `POST /api/users` → crear (asignar a tenant actual) ⭐
- [ ] `GET /api/users/{id}` → detalle (verificar acceso) ⭐
- [ ] `PUT /api/users/{id}` → actualizar
- [ ] `DELETE /api/users/{id}` → eliminar (soft delete)
- [ ] `PUT /api/users/{id}/supervisor` → asignar jefe inmediato ⭐ NUEVO
- [ ] `GET /api/users/{id}/subordinates` → listar subordinados ⭐ NUEVO
- [ ] `GET /api/users/{id}/tenants` → listar tenants del usuario ⭐ NUEVO
- [ ] `POST /api/users/{id}/tenants/{tenantId}` → agregar a tenant ⭐ NUEVO

### DocumentController

- [ ] `GET /api/documents` → listar (scope por tenant actual) ⭐
- [ ] `POST /api/documents/upload-zip` → carga masiva (Job ProcessZipFile)
- [ ] `GET /api/documents/{id}` → detalle (verificar acceso)
- [ ] `GET /api/documents/{id}/download` → descargar (auditar)
- [ ] `POST /api/documents/{id}/sign` → firmar (capturar IP, user_agent)
- [ ] `GET /api/documents/orphaned` → documentos huérfanos (admin)
- [ ] `POST /api/documents/{id}/assign` → asignar huérfano a empleado
- [ ] `GET /api/documents/processing-status` → progreso de procesamiento

### VacationRequestController

- [ ] `GET /api/vacation-requests` → listar (scope por tenant/usuario) ⭐
- [ ] `POST /api/vacation-requests` → crear (envía a immediate_supervisor_id) ⭐
- [ ] `GET /api/vacation-requests/{id}` → detalle
- [ ] `PUT /api/vacation-requests/{id}/approve` → aprobar (solo supervisor) ⭐
  - Marca `was_taken = TRUE` por defecto ⭐
- [ ] `PUT /api/vacation-requests/{id}/reject` → rechazar (solo supervisor) ⭐
- [ ] `PUT /api/vacation-requests/{id}/mark-taken` → marcar como tomadas ⭐ NUEVO
- [ ] `PUT /api/vacation-requests/{id}/mark-not-taken` → marcar como NO tomadas ⭐ NUEVO
- [ ] `DELETE /api/vacation-requests/{id}` → cancelar (solo si pending)
- [ ] `GET /api/vacation-requests/pending-approval` → pendientes de aprobar ⭐
- [ ] `GET /api/vacation-requests/not-taken` → aprobadas pero no tomadas ⭐ NUEVO

### NotificationController

- [ ] `GET /api/notifications` → mis notificaciones (scope por tenant) ⭐
- [ ] `PUT /api/notifications/{id}/read` → marcar como leída
- [ ] `DELETE /api/notifications/{id}` → eliminar
- [ ] `GET /api/notifications/unread-count` → contador

### AuditLogController

- [ ] `GET /api/audit-logs` → consultar logs (solo root/admin)
- [ ] Filtros: user, model, action, date_range, tenant ⭐

---

## 🟡 PRIORIDAD MEDIA - JOBS Y PROCESAMIENTO

### Instalación

- [ ] Instalar Horizon: `composer require laravel/horizon`
- [ ] Publicar configuración de Horizon
- [ ] Configurar Redis como queue driver
- [ ] Configurar workers en `config/horizon.php`

### Jobs

- [ ] **ProcessZipFile**
  - Extraer archivos del ZIP
  - Validar formato de nombres
  - Dispatch job para cada archivo
  
- [ ] **ValidateEmployees**
  - Verificar DNI en nombres de archivos
  - Buscar empleado en base de datos
  - Marcar como huérfano si no existe
  
- [ ] **DistributeDocuments**
  - Mover archivo a estructura correcta: storage/documents/TENANT/DOC_NUMBER/TYPE/PERIOD/
  - Crear registro en tabla documents
  - Generar file_path según estructura ⭐
  
- [ ] **CreateOrphanedDocuments**
  - Guardar documentos sin empleado
  - Notificar admin
  
- [ ] **SendNotificationEmail**
  - Notificar empleado de documento nuevo
  - Notificar solicitud de firma
  
- [ ] **SendVacationRequestEmail** ⭐ ACTUALIZADO
  - Enviar a immediate_supervisor_id (no a lista de aprobadores)
  - Incluir link con token para aprobar/rechazar
  
- [ ] **SendVacationStatusEmail**
  - Notificar empleado de aprobación/rechazo
  - Notificar admin del tenant ⭐

---

## 🟡 PRIORIDAD MEDIA - WEBSOCKETS (Laravel Reverb)

### Instalación

- [ ] Instalar Reverb: `composer require laravel/reverb`
- [ ] Publicar configuración
- [ ] Ejecutar servidor Reverb

### Eventos

- [ ] **DocumentProcessed** → termina procesamiento de ZIP
- [ ] **NewDocumentAvailable** → nuevo documento para empleado
- [ ] **DocumentSigned** → empleado firma documento
- [ ] **VacationRequestCreated** → nueva solicitud (broadcast a supervisor) ⭐
- [ ] **VacationRequestApproved** → solicitud aprobada
- [ ] **VacationRequestRejected** → solicitud rechazada
- [ ] **ProcessingError** → error crítico en procesamiento

### Frontend

- [ ] Instalar Laravel Echo: `npm install laravel-echo pusher-js`
- [ ] Configurar Echo client
- [ ] Conectar a Reverb server
- [ ] Escuchar eventos por tenant ⭐
- [ ] Actualizar UI en tiempo real
- [ ] Manejo de reconexión

---

## 🟢 PRIORIDAD BAJA - FRONTEND UPDATES

### Componentes Nuevos

- [ ] **TenantSwitcher** ⭐ NUEVO
  - Dropdown para cambiar de tenant (si tiene múltiples)
  - Mostrar tenant actual en navbar
  - Guardar selección en localStorage
  
- [ ] **SupervisorBadge** ⭐ NUEVO
  - Indicador visual de jefe inmediato en lista de usuarios
  - Tooltip con nombre del supervisor
  
- [ ] **VacationStatusBadge** ⭐ ACTUALIZADO
  - Incluir indicador de `was_taken`
  - Colores: Aprobada + Tomada (verde), Aprobada + No Tomada (amarillo)

### Páginas a Actualizar

- [ ] **Users Management**
  - Mostrar tenants del usuario (badges) ⭐
  - Botón "Gestionar Tenants" ⭐
  - Campo "Jefe Inmediato" (select de usuarios del mismo tenant) ⭐
  - Filtro por tenant ⭐
  
- [ ] **Vacation Requests** ⭐ ACTUALIZADO
  - Columna "Tomadas" (checkbox) ⭐
  - Botón "Marcar como Tomadas/No Tomadas" ⭐
  - Filtro "Aprobadas pero No Tomadas" ⭐
  - Reporte de vacaciones no utilizadas ⭐
  
- [ ] **Vacation Approvals** ⭐ ACTUALIZADO
  - Solo mostrar solicitudes de subordinados directos ⭐
  - No hay lista de aprobadores, va directo al supervisor ⭐

### Stores a Actualizar

- [ ] **authStore**
  - Agregar `currentTenant` ⭐
  - Método `switchTenant(tenantId)` ⭐
  - Método `getUserTenants()` ⭐
  
- [ ] **usersStore**
  - Conectar con API real
  - Filtro por tenant ⭐
  - CRUD de immediate_supervisor_id ⭐
  
- [ ] **vacationRequestsStore** ⭐ NUEVO
  - CRUD de solicitudes
  - Método `approve(id)` → marca was_taken=TRUE
  - Método `markAsTaken(id)` ⭐
  - Método `markAsNotTaken(id)` ⭐
  - Filtro por `was_taken` ⭐

---

## 🟢 PRIORIDAD BAJA - REPORTES Y ANALYTICS

- [ ] Dashboard de admin:
  - Documentos por mes (gráfico)
  - Documentos pendientes de firma
  - Vacaciones aprobadas vs tomadas ⭐ NUEVO
  - Vacaciones no utilizadas por empleado ⭐ NUEVO
  
- [ ] Exportación a Excel:
  - Reporte de documentos por empleado
  - Reporte de vacaciones (incluyendo was_taken) ⭐
  - Reporte de documentos huérfanos
  - Logs de auditoría

---

## 🟢 PRIORIDAD BAJA - TESTING

### Backend

- [ ] Feature tests de autenticación
- [ ] Feature tests de multi-tenancy ⭐
- [ ] Feature tests de immediate_supervisor_id ⭐
- [ ] Feature tests de vacation requests (was_taken) ⭐
- [ ] Unit tests de modelos
- [ ] Tests de políticas de acceso
- [ ] Tests de Jobs

### Frontend

- [ ] Tests de componentes React
- [ ] Tests de stores de Zustand
- [ ] E2E tests con Playwright

---

## 📦 DEPENDENCIAS FALTANTES

### Backend

```bash
composer require laravel/sanctum
composer require laravel/horizon
composer require laravel/reverb
composer require maatwebsite/excel
```

### Frontend

```bash
npm install axios
npm install laravel-echo pusher-js
npm install react-pdf
npm install react-dropzone
npm install xlsx
npm install date-fns
```

---

## 🎯 RESUMEN DE CAMBIOS ARQUITECTURALES

### ✅ Completado
1. **Multi-tenancy flexible:** Tabla `user_tenants` permite múltiples organizaciones por usuario
2. **Aprobación automática:** Campo `immediate_supervisor_id` reemplaza lista de aprobadores
3. **Control de vacaciones:** Campo `was_taken` para mapear vacaciones no tomadas

### 🔴 Pendiente de Implementación
1. Crear todas las migraciones con los cambios
2. Actualizar modelos Laravel con nuevas relaciones
3. Implementar lógica de multi-tenancy en controllers y policies
4. Frontend: selector de tenant y gestión de supervisor
5. Jobs y procesamiento de documentos
6. WebSockets con Laravel Reverb
7. Testing completo

---

## 📊 ESTIMACIÓN DE ESFUERZO

| Categoría | Tareas | Horas Estimadas |
|-----------|--------|-----------------|
| **Migraciones y Modelos** | 30 | 40-50h |
| **Autenticación y Autorización** | 25 | 35-40h |
| **Controllers y API** | 40 | 50-60h |
| **Jobs y Procesamiento** | 15 | 30-35h |
| **WebSockets** | 10 | 20-25h |
| **Frontend Updates** | 30 | 40-50h |
| **Reportes** | 10 | 15-20h |
| **Testing** | 20 | 30-40h |
| **TOTAL** | **180 tareas** | **260-320h** |

**Tiempo estimado:** 6-8 semanas (1 desarrollador full-time)

---

## 🚀 PLAN DE EJECUCIÓN SUGERIDO

### Fase 1: Base de Datos (Semana 1)
- Crear todas las migraciones
- Seeders con datos de prueba
- Modelos Laravel con relaciones

### Fase 2: Autenticación y Multi-Tenancy (Semana 2)
- Laravel Sanctum
- Middleware y Policies
- Frontend auth con cookies
- Selector de tenant

### Fase 3: CRUD Básico (Semana 3-4)
- Controllers de Users, Tenants, Documents
- Frontend conectado a API real
- Gestión de supervisor

### Fase 4: Módulo de Vacaciones (Semana 5)
- Controller de VacationRequests
- Lógica de aprobación con immediate_supervisor_id
- Frontend de solicitudes y aprobaciones
- Control de was_taken

### Fase 5: Procesamiento y Jobs (Semana 6)
- Laravel Horizon
- Jobs de procesamiento de ZIP
- Email notifications
- Documentos huérfanos

### Fase 6: WebSockets y Finalización (Semana 7-8)
- Laravel Reverb
- Eventos en tiempo real
- Reportes y analytics
- Testing y deployment

---

**Documento generado:** 4 de diciembre de 2025  
**Próxima revisión:** Al completar Fase 1
