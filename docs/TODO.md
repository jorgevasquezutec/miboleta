# 📋 TODO - Sistema de Gestión Documental "MiBoleta"

**Última actualización:** 2025-12-14  
**Estado general del proyecto:** ~95% completado (Módulos 0-5 + Arquitectura)

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
| **Módulo 5:** Vacaciones | ✅ Completado | 100% |
| **Módulo 6:** Notificaciones | ⏳ Pendiente | 0% |
| **Módulo 7:** Reportes | ⏳ Pendiente | 0% |
| **Módulo 8:** Testing/Deploy | ⏳ Pendiente | 0% |

---

## ✅ Módulos Completados

### Módulo 0: Base de Datos ✅
- Migraciones: tenants, users, roles, documents, document_types, document_batches, vacation_requests
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
- Service Layer (9 services incluyendo VacationService)
- API Resources (transformers)
- Custom Exceptions + Global Handler
- Form Requests (validación)
- Swagger/OpenAPI documentación completa
- Email templates estandarizados con componentes

### Módulo 5: Vacaciones ✅ (COMPLETADO 2025-12-14)

#### Backend Implementado:
- **Modelo:** VacationRequest con estados y flujo completo
- **Service:** VacationService.php con toda la lógica de negocio
- **Controller:** VacationRequestController.php con 12 endpoints
- **Validaciones:** CreateVacationRequestRequest.php, RejectVacationRequestRequest.php

#### Endpoints Implementados:
```
POST   /api/vacation-requests                    ✅ Crear solicitud
GET    /api/vacation-requests                    ✅ Listar (scope por rol)
GET    /api/vacation-requests/{id}               ✅ Ver detalle
PUT    /api/vacation-requests/{id}/approve       ✅ Aprobar (supervisor)
PUT    /api/vacation-requests/{id}/reject        ✅ Rechazar (supervisor)
PUT    /api/vacation-requests/{id}/mark-taken    ✅ Marcar tomada (supervisor)
PUT    /api/vacation-requests/{id}/mark-not-taken ✅ Marcar NO tomada (supervisor)
DELETE /api/vacation-requests/{id}               ✅ Cancelar (empleado, solo si pending)
GET    /api/vacation-requests/pending-approval   ✅ Pendientes de aprobar (supervisor)
GET    /api/vacation-requests/pending-confirmation ✅ Pendientes de confirmar (supervisor)
GET    /api/vacation-requests/my-team            ✅ Vacaciones de mi equipo
GET    /api/vacation-requests/my-decisions       ✅ Historial de decisiones del supervisor
```

#### Frontend Implementado:
- **VacationRequestsListPage.tsx** - Mis vacaciones (empleado/admin)
- **VacationRequestFormPage.tsx** - Nueva solicitud con calendario
- **TeamVacationsPage.tsx** - Vista consolidada con 3 tabs:
  - Tab "Pendientes": Solicitudes esperando aprobación
  - Tab "Por Confirmar": Vacaciones terminadas pendientes de confirmación
  - Tab "Mi Historial": Decisiones del supervisor (aprobadas/rechazadas)
- **VacationHistoryPage.tsx** - Histórico general para admin
- **VacationRequestCard.tsx** - Componente reutilizable para mostrar solicitudes
- **VacationRejectModal.tsx** - Modal para rechazar con motivo

#### Store y Repository:
- **vacationsStore.ts** - Estado global con Zustand
- **VacationRepository.ts** - Acceso a API

#### Navegación:
```
Sidebar "Vacaciones":
├── Mis Vacaciones     → /vacations
├── Mi Equipo          → /team-vacations (3 tabs)
└── Histórico General  → /vacation-history
```

#### Reglas de Negocio Implementadas:
- ✅ No se pueden solicitar vacaciones con fechas superpuestas
- ✅ Solo el supervisor puede aprobar/rechazar
- ✅ Validación de días máximos (0.5 - 30)
- ✅ "Mis Vacaciones" muestra solo las del usuario actual (incluso para admin)
- ✅ Histórico general filtra por tenant

---

## ⏳ Módulos Pendientes

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
- [ ] Emails de notificación para vacaciones (aprobación/rechazo)

### Frontend
- [ ] Implementar PWA (service worker)
- [ ] Agregar dark mode
- [ ] Optimizar bundle size
- [ ] Implementar lazy loading de rutas
- [ ] Agregar tests con Vitest
- [ ] Calendario visual de vacaciones del equipo

### DevOps
- [ ] Configurar Docker para producción
- [ ] Implementar CI/CD
- [ ] Configurar backups automáticos
- [ ] Monitoreo con Sentry o similar

---

## 🐛 Bugs Conocidos / Por Arreglar

- [ ] Lint warning: `@theme` en index.css (falso positivo, Tailwind v4 lo soporta)
- [ ] Limpiar imports no usados (USER_ROLE_DISPLAY_LABELS, location, etc.)

---

## 🏗️ Arquitectura del Sistema

### Backend (Laravel 11)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── PasswordController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── UserController.php
│   │   │   ├── TenantController.php
│   │   │   ├── DocumentController.php
│   │   │   ├── DocumentBatchController.php
│   │   │   ├── DocumentTypeController.php
│   │   │   ├── DocumentSignatureController.php
│   │   │   ├── FileUploadController.php
│   │   │   └── VacationRequestController.php  # NUEVO
│   │   ├── Requests/
│   │   │   ├── CreateVacationRequestRequest.php  # NUEVO
│   │   │   └── RejectVacationRequestRequest.php   # NUEVO
│   │   └── Resources/
│   │       └── VacationRequestResource.php  # NUEVO
│   ├── Services/
│   │   └── VacationService.php  # NUEVO
│   └── Models/
│       └── VacationRequest.php  # NUEVO
```

### Frontend (React + TypeScript + Vite)

```
src/
├── core/domain/entities/
│   └── VacationRequest.ts  # NUEVO
├── infrastructure/persistence/repositories/
│   └── VacationRepository.ts  # NUEVO
├── presentation/
│   ├── components/features/vacations/  # NUEVO
│   │   ├── VacationRequestCard.tsx
│   │   └── VacationRejectModal.tsx
│   ├── pages/
│   │   ├── admin/
│   │   │   ├── TeamVacationsPage.tsx  # NUEVO (consolidado)
│   │   │   └── VacationHistoryPage.tsx  # NUEVO
│   │   └── employee/
│   │       ├── VacationRequestsListPage.tsx  # NUEVO
│   │       └── VacationRequestFormPage.tsx  # NUEVO
│   └── stores/
│       └── vacationsStore.ts  # NUEVO
```

---

## 📝 Notas de Desarrollo

### Layout Fijo (actualizado 2025-12-14)
- **Navbar:** Fixed top, z-50, siempre visible
- **Sidebar:** Fixed left, z-40, debajo del navbar
- **Contenido:** Scroll interno, margen izquierdo dinámico

### Colores de la Plataforma
```
Primary Blue:     #2563EB
Primary Hover:    #1E40AF
Background:       #F8FAFC (content), #FFFFFF (cards)
Text Primary:     #334155
Text Secondary:   #64748B
```

### Usuarios de Prueba
```
Root Admin:    root@example.com / password
Admin ABC:     admin@abc.com / password
Admin XYZ:     admin@xyz.com / password
Cliente 1:     juan.perez@abc.com / password
Cliente 2:     maria.garcia@xyz.com / password
```

---

## 📅 Próximos Pasos

1. **Inmediato:** Pruebas de vacaciones end-to-end
2. **Corto plazo:** Módulo 6 (Notificaciones)
3. **Mediano plazo:** Módulos 7-8 (Reportes, Testing)
4. **Largo plazo:** Deploy a producción

---

*Última actualización: 2025-12-14 23:10*
