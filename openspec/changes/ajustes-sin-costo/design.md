## Context

Cambio de mantenimiento (sin costo) que toca backend (Laravel) y frontend (React). El código ya
existe en su mayoría: el aviso de cambio de correo está implementado en el path de gestión de
usuarios (`UserService::updateUser`), pero el cambio de correo lo permiten hoy tanto root como admin
(`UpdateUserRequest`) cuando el cliente quiere que sea **solo root**; y el dashboard del empleado
deriva sus totales de una lista paginada en lugar de totales reales.

## Goals / Non-Goals

**Goals:**
- El dashboard del empleado muestra totales individuales reales (no de la página visible).
- El dashboard del admin refleja métricas a nivel organización del tenant activo.
- Solo el rol root puede cambiar el correo de un usuario, y al hacerlo se envía el aviso.
- Verificar (sin cambios) visor de PDF y registro de auditoría.

**Non-Goals:**
- Cambio de correo en el perfil de auto-servicio (queda deshabilitado, como hoy).
- Re-verificación del nuevo correo (doble opt-in): se mantiene el patrón actual (solo notificar).
- Rediseño de los dashboards o nuevas métricas.

## Decisions

- **Totales del empleado vía backend, no en el cliente.** Se exponen los totales individuales desde
  un endpoint de reportes (extender `ReportsService` con stats por usuario, p. ej.
  `GET /api/reports/my-stats`) y `employee/DashboardPage.tsx` los consume. *Alternativa descartada:*
  pedir todos los documentos al cliente y contarlos — no escala y repite el bug.
- **Cambio de correo solo root + aviso ya existente.** Se añade un guard (en `UserController::update`
  o `UpdateUserRequest`) que rechaza el cambio de `email` si el actor no es root. El envío del aviso
  con `EmailChangedNotificationMail` ya está en `UserService::updateUser` (a correo anterior y nuevo);
  solo se verifica. *Alternativa descartada:* habilitar email en el perfil de auto-servicio — el
  cliente indicó que solo root cambia el correo.
- **Endurecer el scoping de tenant** en `ReportsController::getTenantId` para usuarios no-root sin
  tenant primario (evitar `null` que rompe el filtrado por organización).

## Risks / Trade-offs

- [El dashboard "vacío" puede deberse a BD sin datos, no a un bug] → Validar con datos reales antes de
  cambiar queries; documentar lo que sea solo falta de data.
- [Restringir el cambio de correo a root cambia el comportamiento actual (admin ya no podrá)] →
  Es lo solicitado por el cliente; se comunica como parte del ajuste. El aviso a ambos correos
  mitiga el riesgo de secuestro de cuenta.
- [Tocar `ReportsService` podría afectar el dashboard admin] → Cambios mínimos y verificación manual
  por rol (admin de empresa y root).
