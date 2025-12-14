# 🏖️ TASK: Módulo 5 - Sistema de Vacaciones

**Fecha:** 2025-12-14  
**Estimación:** 6-8 días  
**Estado:** ⏳ Pendiente

---

## 📋 Resumen del Módulo

Implementar un sistema completo de solicitud y gestión de vacaciones donde:
- **Empleados** solicitan vacaciones
- **Supervisores** aprueban/rechazan y confirman si fueron tomadas
- **Admins** ven reportes y estadísticas

---

## 🔄 Flujo Principal

```
Empleado                 Supervisor                Sistema
   │                         │                        │
   │──[1] Solicita──────────►│                        │
   │                         │──[2] Notifica──────────│
   │                         │                        │
   │◄──[3] Aprueba/Rechaza───│                        │
   │                         │                        │
   │  [4] Toma vacaciones    │                        │
   │                         │                        │
   │                         │──[5] Confirma──────────│
   │                         │  (Tomada/No Tomada)    │
   │                         │                        │
   │                         │◄──[6] Reportes─────────│
```

---

## ✅ Checklist de Implementación

### FASE 1: Backend - Base (Día 1-2)

#### 1.1 Migración
- [ ] Crear migración `create_vacation_requests_table`
  ```php
  Schema::create('vacation_requests', function (Blueprint $table) {
      $table->id();
      $table->uuid('user_id');              // Empleado que solicita
      $table->unsignedBigInteger('tenant_id');
      $table->date('start_date');
      $table->date('end_date');
      $table->decimal('days_requested', 5, 2);
      $table->text('reason')->nullable();
      $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled']);
      $table->uuid('approved_by')->nullable();
      $table->timestamp('approved_at')->nullable();
      $table->uuid('rejected_by')->nullable();
      $table->timestamp('rejected_at')->nullable();
      $table->text('rejection_reason')->nullable();
      $table->boolean('was_taken')->nullable();  // NULL=pendiente, TRUE/FALSE=confirmado
      $table->uuid('confirmed_by')->nullable();
      $table->timestamp('confirmed_at')->nullable();
      $table->timestamps();
      
      $table->foreign('user_id')->references('id')->on('users');
      $table->foreign('tenant_id')->references('id')->on('tenants');
      $table->foreign('approved_by')->references('id')->on('users');
      $table->foreign('rejected_by')->references('id')->on('users');
      $table->foreign('confirmed_by')->references('id')->on('users');
  });
  ```

#### 1.2 Modelo
- [ ] Crear `app/Models/VacationRequest.php`
  - Relaciones: user, tenant, approvedBy, rejectedBy, confirmedBy
  - Scopes: pending(), approved(), forUser(), forTenant(), pendingConfirmation()
  - Métodos: approve(), reject(), markAsTaken(), markAsNotTaken(), cancel()
  - Accesors: status_label, duration_text

#### 1.3 Service
- [ ] Crear `app/Services/VacationService.php`
  - createRequest()
  - approveRequest()
  - rejectRequest()
  - markAsTaken()
  - markAsNotTaken()
  - cancelRequest()
  - getRequestsForUser()
  - getRequestsForSupervisor()
  - getPendingApprovals()
  - getPendingConfirmations()
  - calculateAvailableDays()
  - validateNoOverlap()

#### 1.4 Form Requests
- [ ] `CreateVacationRequest` - validar fechas, días, etc.
- [ ] `RejectVacationRequest` - validar razón de rechazo

---

### FASE 2: Backend - API (Día 2-3)

#### 2.1 Controller
- [ ] Crear `app/Http/Controllers/Api/VacationRequestController.php`

| Método | Ruta | Descripción | Rol |
|--------|------|-------------|-----|
| index | GET /vacation-requests | Listar solicitudes | Todos |
| store | POST /vacation-requests | Crear solicitud | Empleado |
| show | GET /vacation-requests/{id} | Ver detalle | Todos |
| destroy | DELETE /vacation-requests/{id} | Cancelar | Empleado |
| approve | PUT /vacation-requests/{id}/approve | Aprobar | Supervisor |
| reject | PUT /vacation-requests/{id}/reject | Rechazar | Supervisor |
| markTaken | PUT /vacation-requests/{id}/mark-taken | Confirmar tomada | Supervisor |
| markNotTaken | PUT /vacation-requests/{id}/mark-not-taken | Confirmar NO tomada | Supervisor |
| pendingApprovals | GET /vacation-requests/pending-approval | Pendientes de aprobar | Supervisor |
| pendingConfirmation | GET /vacation-requests/pending-confirmation | Pendientes confirmar | Supervisor |
| myTeam | GET /vacation-requests/my-team | Vacaciones del equipo | Supervisor |

#### 2.2 API Resource
- [ ] Crear `app/Http/Resources/VacationRequestResource.php`

#### 2.3 Rutas
- [ ] Agregar rutas en `routes/api.php`

---

### FASE 3: Backend - Emails (Día 3)

#### 3.1 Mail Classes
- [ ] `VacationRequestCreatedMail` - Notifica al supervisor
- [ ] `VacationRequestApprovedMail` - Notifica al empleado
- [ ] `VacationRequestRejectedMail` - Notifica al empleado

#### 3.2 Email Templates
- [ ] `emails/vacation-request-created.blade.php`
- [ ] `emails/vacation-request-approved.blade.php`
- [ ] `emails/vacation-request-rejected.blade.php`

---

### FASE 4: Frontend - Entidades y Store (Día 4)

#### 4.1 Entidades
- [ ] Crear `src/core/domain/entities/VacationRequest.ts`
  ```typescript
  interface VacationRequest {
    id: number;
    userId: string;
    user?: User;
    tenantId: number;
    startDate: string;
    endDate: string;
    daysRequested: number;
    reason?: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    approvedBy?: string;
    approvedByUser?: User;
    approvedAt?: string;
    rejectedBy?: string;
    rejectedByUser?: User;
    rejectedAt?: string;
    rejectionReason?: string;
    wasTaken?: boolean | null;
    confirmedBy?: string;
    confirmedByUser?: User;
    confirmedAt?: string;
    createdAt: string;
    updatedAt: string;
  }
  ```

#### 4.2 Repository
- [ ] Crear `src/infrastructure/repositories/VacationRepository.ts`
  - getRequests()
  - createRequest()
  - cancelRequest()
  - approveRequest()
  - rejectRequest()
  - markAsTaken()
  - markAsNotTaken()
  - getPendingApprovals()
  - getPendingConfirmations()

#### 4.3 Store
- [ ] Crear `src/presentation/stores/vacationsStore.ts`

---

### FASE 5: Frontend - Páginas Empleado (Día 5)

#### 5.1 VacationRequestsListPage
- [ ] Crear `src/presentation/pages/employee/VacationRequestsListPage.tsx`
  - Tabla de mis solicitudes
  - Filtros: estado, año
  - Botón "Nueva Solicitud"
  - Estados con badges de colores

#### 5.2 VacationRequestFormPage
- [ ] Crear `src/presentation/pages/employee/VacationRequestFormPage.tsx`
  - Date pickers (inicio/fin)
  - Cálculo automático de días
  - Campo razón (opcional)
  - Mostrar supervisor asignado
  - Validaciones

---

### FASE 6: Frontend - Páginas Supervisor (Día 6)

#### 6.1 VacationApprovalsPage
- [ ] Crear `src/presentation/pages/admin/VacationApprovalsPage.tsx`
  - Lista de solicitudes pendientes de MI equipo
  - Botones Aprobar/Rechazar
  - Modal de rechazo con razón

#### 6.2 VacationConfirmationPage  
- [ ] Crear `src/presentation/pages/admin/VacationConfirmationPage.tsx`
  - Lista de vacaciones aprobadas pendientes de confirmar
  - Botones "Tomada" / "No Tomada"

#### 6.3 VacationCalendarPage (Opcional)
- [ ] Vista calendario de vacaciones del equipo

---

### FASE 7: Frontend - Componentes (Día 6-7)

#### 7.1 Componentes UI
- [ ] `VacationStatusBadge.tsx` - Badge con estado y sub-badge tomada/no tomada
- [ ] `VacationRequestCard.tsx` - Card resumida
- [ ] `VacationApprovalModal.tsx` - Modal de aprobación
- [ ] `VacationRejectModal.tsx` - Modal de rechazo con razón

#### 7.2 Sidebar
- [ ] Agregar items en sidebar:
  - "Mis Vacaciones" (todos)
  - "Aprobar Vacaciones" (supervisores) con badge de pendientes

---

### FASE 8: Integración y Testing (Día 7-8)

#### 8.1 Testing Manual
- [ ] Flujo completo: Solicitar → Aprobar → Confirmar tomada
- [ ] Flujo rechazo: Solicitar → Rechazar
- [ ] Flujo cancelación: Solicitar → Cancelar
- [ ] Emails se envían correctamente
- [ ] Permisos por rol funcionan

#### 8.2 Edge Cases
- [ ] Usuario sin supervisor no puede solicitar
- [ ] No permite fechas solapadas
- [ ] Solo supervisor puede aprobar/rechazar
- [ ] Solo supervisor puede confirmar tomada/no tomada

---

## 📁 Archivos a Crear

### Backend
```
backend/
├── database/migrations/
│   └── xxxx_create_vacation_requests_table.php
├── app/
│   ├── Models/
│   │   └── VacationRequest.php
│   ├── Services/
│   │   └── VacationService.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── VacationRequestController.php
│   │   ├── Requests/
│   │   │   ├── CreateVacationRequest.php
│   │   │   └── RejectVacationRequest.php
│   │   └── Resources/
│   │       └── VacationRequestResource.php
│   └── Mail/
│       ├── VacationRequestCreatedMail.php
│       ├── VacationRequestApprovedMail.php
│       └── VacationRequestRejectedMail.php
└── resources/views/emails/
    ├── vacation-request-created.blade.php
    ├── vacation-request-approved.blade.php
    └── vacation-request-rejected.blade.php
```

### Frontend
```
src/
├── core/domain/entities/
│   └── VacationRequest.ts
├── infrastructure/repositories/
│   └── VacationRepository.ts
├── presentation/
│   ├── stores/
│   │   └── vacationsStore.ts
│   ├── pages/
│   │   ├── employee/
│   │   │   ├── VacationRequestsListPage.tsx
│   │   │   └── VacationRequestFormPage.tsx
│   │   └── admin/
│   │       ├── VacationApprovalsPage.tsx
│   │       └── VacationConfirmationPage.tsx
│   └── components/features/vacations/
│       ├── VacationStatusBadge.tsx
│       ├── VacationRequestCard.tsx
│       ├── VacationApprovalModal.tsx
│       └── VacationRejectModal.tsx
```

---

## 🎯 Próximo Paso Inmediato

**Comenzar con:** FASE 1.1 - Crear la migración de la tabla `vacation_requests`

```bash
cd backend
php artisan make:migration create_vacation_requests_table
```

---

*Última actualización: 2025-12-14 12:00*
