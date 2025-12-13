# 📋 TODO - Sistema de Gestión Documental "MiBoleta"

**Última actualización:** 2025-12-13  
**Estado general del proyecto:** ~85% completado (Módulos 0-4 + Arquitectura)

---

## 📊 Resumen de Progreso

| Módulo | Estado | Completitud |
|--------|--------|-------------|
| **Módulo 0:** Base de Datos | ✅ Completado | 100% |
| **Módulo 1:** Autenticación | ✅ Completado | 100% |
| **Módulo 1.5:** Gestión de Contraseñas | ✅ Completado | 100% |
| **Módulo 2:** Multi-Tenancy | ✅ Completado | 100% |
| **Módulo 3:** Gestión de Usuarios | ✅ Completado | 100% |
| **Módulo 4:** Documentos | ✅ Completado | 100% |
| **Módulo 4+:** Arquitectura Backend | ✅ Completado | 100% |
| **Módulo 5:** Vacaciones | ⏳ Pendiente | 0% |
| **Módulo 6:** Notificaciones | ⏳ Pendiente | 0% |
| **Módulo 7:** Reportes | ⏳ Pendiente | 0% |
| **Módulo 8:** Testing/Deploy | ⏳ Pendiente | 0% |

---

## 🏗️ Arquitectura del Sistema

### Backend (Laravel 11)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php        # Login, logout, refresh, me
│   │   │   ├── PasswordController.php    # Forgot, reset, change, force-change
│   │   │   ├── ProfileController.php     # Perfil, avatar upload/delete
│   │   │   ├── UserController.php        # CRUD usuarios
│   │   │   ├── TenantController.php      # CRUD tenants, logo upload
│   │   │   ├── DocumentController.php    # Documentos, download, preview
│   │   │   ├── DocumentBatchController.php # Carga masiva ZIP
│   │   │   ├── DocumentTypeController.php  # Tipos de documento
│   │   │   ├── DocumentSignatureController.php # Firma 2FA
│   │   │   └── FileUploadController.php  # Upload de archivos  
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php             # Verificación de roles
│   │   │   ├── TenantScope.php           # Scope multi-tenant
│   │   │   └── EnsureCookieAccessToken.php # Auth cookies HttpOnly
│   │   ├── Requests/                     # Form Requests (validación)
│   │   └── Resources/                    # API Resources (transformación)
│   │       ├── UserResource.php
│   │       ├── UserSummaryResource.php
│   │       ├── TenantResource.php
│   │       ├── DocumentResource.php
│   │       ├── DocumentBatchResource.php
│   │       └── DocumentTypeResource.php
│   ├── Services/                         # Business Logic Layer
│   │   ├── AuthService.php
│   │   ├── PasswordService.php
│   │   ├── ProfileService.php
│   │   ├── UserService.php
│   │   ├── TenantService.php
│   │   ├── DocumentService.php
│   │   ├── DocumentBatchService.php
│   │   └── SignatureService.php
│   ├── Exceptions/                       # Custom Exceptions
│   │   ├── UserCreationException.php
│   │   ├── DocumentNotFoundException.php
│   │   └── UnauthorizedAccessException.php
│   ├── Jobs/
│   │   └── ProcessDocumentBatch.php      # Procesamiento async de ZIP
│   ├── Mail/
│   │   ├── WelcomeUserMail.php
│   │   ├── ForgotPasswordMail.php
│   │   ├── PasswordResetByAdminMail.php
│   │   ├── NewDocumentAvailableMail.php
│   │   └── SignatureCodeMail.php
│   └── Models/
│       ├── User.php
│       ├── Tenant.php
│       ├── Role.php
│       ├── Document.php
│       ├── DocumentType.php
│       ├── DocumentBatch.php
│       └── RefreshToken.php
├── resources/views/emails/
│   ├── layouts/
│   │   └── base.blade.php                # Layout base de emails
│   ├── components/                       # Componentes reutilizables
│   │   ├── button.blade.php
│   │   ├── alert-warning.blade.php
│   │   ├── alert-success.blade.php
│   │   ├── alert-danger.blade.php
│   │   ├── code-box.blade.php
│   │   └── info-box.blade.php
│   ├── welcome.blade.php
│   ├── forgot-password.blade.php
│   ├── password-reset-admin.blade.php
│   ├── signature-code.blade.php
│   └── new-document-available.blade.php
└── bootstrap/app.php                     # Global Exception Handler
```

### Frontend (React + TypeScript + Vite)

```
src/
├── domain/
│   ├── entities/                         # Interfaces/Types
│   │   ├── User.ts
│   │   ├── Tenant.ts
│   │   ├── Document.ts
│   │   └── ...
│   └── usecases/                         # Business Logic
│       ├── auth/
│       ├── users/
│       ├── tenants/
│       └── documents/
├── infrastructure/
│   ├── http/
│   │   └── apiClient.ts                  # Axios con interceptors
│   └── repositories/
│       ├── UserRepository.ts
│       ├── TenantRepository.ts
│       └── DocumentRepository.ts
├── presentation/
│   ├── components/
│   │   ├── ui/                           # UI Components (shadcn)
│   │   ├── layout/                       # Layout (AppSidebar, Navbar)
│   │   ├── shared/                       # TenantSwitcher, ConfirmDialog
│   │   └── features/
│   │       ├── users/
│   │       │   ├── PasswordResetModal.tsx
│   │       │   └── UserTenantsManager.tsx
│   │       └── documents/
│   │           ├── DocumentSignatureModal.tsx
│   │           └── PDFViewer.tsx
│   ├── pages/
│   │   ├── auth/
│   │   │   ├── LoginView.tsx
│   │   │   ├── ForgotPasswordPage.tsx
│   │   │   ├── ResetPasswordPage.tsx
│   │   │   └── ForceChangePasswordPage.tsx
│   │   ├── admin/
│   │   │   ├── UsersListPage.tsx
│   │   │   ├── UserDetailPage.tsx
│   │   │   ├── UserFormPage.tsx
│   │   │   ├── TenantsListPage.tsx
│   │   │   ├── TenantFormPage.tsx
│   │   │   ├── DocumentsListPage.tsx
│   │   │   ├── BatchesListPage.tsx
│   │   │   └── BatchDetailPage.tsx
│   │   ├── employee/
│   │   │   ├── DashboardPage.tsx         # Mis Documentos
│   │   │   ├── DocumentUploadView.tsx
│   │   │   └── DocumentViewerView.tsx
│   │   └── shared/
│   │       └── ProfilePage.tsx
│   ├── stores/                           # Zustand stores
│   │   ├── authStore.ts
│   │   ├── usersStore.ts
│   │   ├── tenantsStore.ts
│   │   └── documentsStore.ts
│   └── utils/
│       ├── formatters.ts                 # formatDate, formatFileSize, etc.
│       ├── documentStatus.ts             # Badges de estado
│       └── batchStatus.ts
└── index.tsx                             # App Router
```

---

## ✅ Módulos Completados

### Módulo 0: Base de Datos ✅
- Migraciones: tenants, users, roles, documents, document_types, document_batches
- Modelos Eloquent con relaciones
- Seeders de datos de prueba

### Módulo 1: Autenticación ✅
- Laravel Sanctum con HttpOnly Cookies
- Access Token (1h) + Refresh Token (30d)
- Auto-refresh transparente en frontend
- Protección XSS/CSRF

### Módulo 1.5: Gestión de Contraseñas ✅
- Creación de usuarios con password temporal
- Forgot/Reset password con tokens
- Force change password en primer login
- Admin puede resetear contraseñas

### Módulo 2: Multi-Tenancy ✅
- Usuarios pertenecen a múltiples tenants
- TenantSwitcher con logo y reload automático
- Scope automático por tenant

### Módulo 3: Gestión de Usuarios ✅
- CRUD completo con paginación server-side
- Jerarquía de supervisores
- Asignación de roles y tenants
- Búsqueda y filtros avanzados

### Módulo 4: Documentos ✅
- Carga masiva vía ZIP
- Preview PDF con react-pdf
- Firma digital 2FA (email code)
- Documentos huérfanos auto-asignados
- Seguridad: archivos privados (no públicos)

### Módulo 4+: Arquitectura ✅
- Service Layer (8 services)
- API Resources (transformers)
- Custom Exceptions + Global Handler
- Form Requests (validación)
- Swagger/OpenAPI documentación completa
- Email templates estandarizados con componentes

---

## ⏳ Módulos Pendientes

### Módulo 5: Vacaciones 🔜
**Estimación:** 6-8 días

```
Funcionalidades:
- Solicitud de vacaciones por empleado
- Aprobación por jefe inmediato (supervisor)
- Control de vacaciones tomadas/no tomadas
- Cálculo de días disponibles
- Calendario de vacaciones del equipo
- Notificaciones email

Endpoints a crear:
POST   /api/vacation-requests
GET    /api/vacation-requests
GET    /api/vacation-requests/{id}
PUT    /api/vacation-requests/{id}/approve
PUT    /api/vacation-requests/{id}/reject
PUT    /api/vacation-requests/{id}/mark-taken
GET    /api/vacation-requests/pending-approval
GET    /api/vacation-requests/not-taken

Modelos nuevos:
- VacationRequest (user_id, tenant_id, start_date, end_date, 
                   days_requested, status, approved_by, was_taken)

Páginas frontend:
- VacationRequestsListPage (empleado)
- VacationRequestFormPage
- VacationApprovalsPage (supervisor)
- VacationNotTakenPage (admin)
```

### Módulo 6: Notificaciones en Tiempo Real 🔜
**Estimación:** 5-6 días

```
Funcionalidades:
- Notificaciones push en navegador
- WebSockets con Laravel Reverb
- Bell icon con contador
- Historial de notificaciones
- Marcar como leídas

Eventos a crear:
- NewDocumentAvailable
- DocumentSigned
- VacationRequestCreated
- VacationRequestApproved/Rejected

Tecnologías:
- Laravel Reverb (WebSockets)
- Laravel Echo (frontend)
```

### Módulo 7: Reportes y Auditoría 🔜
**Estimación:** 4-5 días

```
Funcionalidades:
- Dashboard con métricas
- Reportes exportables (Excel/PDF)
- Auditoría de acciones
- Logs de acceso a documentos

Reportes:
- Documentos por período/tipo
- Firmas pendientes
- Vacaciones por equipo
- Actividad de usuarios
```

### Módulo 8: Testing y Deployment 🔜
**Estimación:** 5-7 días

```
Testing:
- Unit tests (PHPUnit)
- Feature tests (API)
- Frontend tests (Vitest)
- E2E tests (Playwright)

Deployment:
- Docker Compose producción
- CI/CD con GitHub Actions
- Variables de entorno
- SSL/TLS certificados
- Backup automático
```

---

## 🔧 Mejoras Técnicas Pendientes

### Backend
- [ ] Implementar rate limiting más estricto
- [ ] Agregar caché para queries frecuentes (Redis)
- [ ] Implementar soft deletes consistentes
- [ ] Agregar tests unitarios/feature

### Frontend
- [ ] Implementar PWA (service worker)
- [ ] Agregar dark mode
- [ ] Optimizar bundle size
- [ ] Implementar lazy loading de rutas
- [ ] Agregar tests con Vitest

### DevOps
- [ ] Configurar Docker para producción
- [ ] Implementar CI/CD
- [ ] Configurar backups automáticos
- [ ] Monitoreo con Sentry o similar

---

## 📝 Notas de Desarrollo

### Patrones Utilizados
- **Repository Pattern** - Abstracción de acceso a datos
- **Service Layer** - Lógica de negocio separada de controllers
- **Use Cases** - Frontend organizado por casos de uso
- **Zustand Stores** - Estado global en frontend

### Colores de la Plataforma
```
Primary Blue:     #2563EB
Primary Hover:    #1E40AF
Background:       #F1F5F9
Text Primary:     #334155
Text Secondary:   #64748B
Text Tertiary:    #94A3B8
Border:           #E2E8F0
Footer BG:        #F8FAFC
```

### Usuarios de Prueba
```
Root Admin:    root@example.com / password
Admin ABC:     admin@abc.com / password
Admin XYZ:     admin@xyz.com / password
Cliente 1:     juan.perez@abc.com / password
Cliente 2:     maria.garcia@xyz.com / password
Multi-tenant:  multi@tenant.com / password
```

---

## 📅 Próximos Pasos

1. **Inmediato:** Comenzar Módulo 5 (Vacaciones)
2. **Corto plazo:** Módulo 6 (Notificaciones)
3. **Mediano plazo:** Módulos 7-8 (Reportes, Testing)
4. **Largo plazo:** Deploy a producción

---

*Última actualización: 2025-12-13 08:30*
