## Why

El cliente reportó que algunos tableros no muestran datos reales y que, al cambiar el correo de un
usuario, no llega la notificación correspondiente. Son correcciones de mantenimiento (sin costo)
comprometidas antes de los sprints de desarrollo nuevo; conviene resolverlas y dejarlas trazables.

## What Changes

- **Dashboard del empleado**: dejar de calcular los totales (documentos totales, firmados y
  pendientes) a partir de la lista **paginada**; mostrar los **totales reales** del usuario.
- **Dashboard del admin**: asegurar que las métricas reflejan el nivel **organización** según el
  tenant activo (endurecer el cálculo de tenant para usuarios sin tenant primario y revisar el
  filtrado por fechas en vacaciones).
- **Cambio de correo restringido a root + aviso**: restringir el cambio del correo de un usuario al
  rol **root** (hoy también lo permite admin) y confirmar que al cambiarlo se envía
  `EmailChangedNotificationMail` al correo anterior y al nuevo. El perfil de auto-servicio no permite
  cambiar el correo (sin cambios ahí).
- **Auditoría del CRUD de usuarios**: la verificación reveló que la creación/edición/eliminación de
  usuarios **no se registraba** (los métodos existían en `AuditService` pero no se invocaban). Se
  cablea para cumplir R4 (registrar el evento y el autor del cambio).
- **Verificación (sin cambio de comportamiento)**: confirmar que el visor de PDF en línea funciona.

## Capabilities

### New Capabilities
- `dashboard`: comportamiento esperado de los tableros de admin (nivel organización) y de empleado
  (totales individuales reales).
- `user-notifications`: política de cambio de correo (solo root) y su aviso al correo anterior y nuevo.
- `audit-log`: registro de auditoría del CRUD de usuarios (creación, edición, eliminación) con el autor.

### Modified Capabilities
<!-- No existen specs previas en openspec/specs/; no hay capabilities existentes que modificar. -->

## Impact

- **Backend**: `app/Http/Controllers/Api/UserController.php` / `app/Services/UserService.php` (guard
  root-only para el cambio de correo; el aviso ya existe), `app/Services/ReportsService.php` y
  `app/Http/Controllers/Api/ReportsController.php` (stats por usuario / scoping por tenant). Reúsa
  `app/Mail/EmailChangedNotificationMail.php` (ya existente).
- **Frontend**: `src/presentation/pages/employee/DashboardPage.tsx` (consumir totales reales) y, de
  ser necesario, `src/presentation/stores/reportsStore.ts`.
- **Sin migraciones de base de datos.** Verificación: visor PDF (`PDFViewer.tsx`) y auditoría
  (`AuditService`) — solo confirmación, sin cambios.
