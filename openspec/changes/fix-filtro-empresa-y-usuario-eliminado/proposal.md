## Why

El cliente reportó que un usuario recién creado, correctamente asociado a su empresa (OVERHEAD),
no podía entrar: en la pantalla de cambio de contraseña obligatorio aparecía el toast "Las empresas
seleccionadas no están asociadas a tu cuenta" y el flujo no se podía completar. No es solo un
mensaje confuso: el cambio de contraseña obligatorio queda **imposible de completar**, así que el
usuario nuevo queda bloqueado sin salida.

La investigación (con los audit_logs de la copia de producción) confirmó la causa: en el mismo
navegador hubo antes una sesión de otro usuario con otra empresa que terminó **sin logout**. El
filtro de empresa persistido en `localStorage` (`tenant-filter-storage`) sobrevivió a esa sesión,
viajó como header `X-Tenant-Ids` en las peticiones del usuario nuevo y el middleware `TenantFilter`
—aplicado a toda la API, incluida la ruta de cambio de contraseña— respondió 403 porque la empresa
heredada no pertenece al usuario nuevo.

Además se encontraron dos problemas relacionados: (a) las cachés de empresas por usuario duran una
hora y casi nunca se invalidan, por lo que asignar o quitar empresas puede tardar hasta 60 minutos
en surtir efecto (mismo síntoma de 403 aunque la ficha esté bien); y (b) el correo y el DNI de un
usuario **eliminado** (soft delete) siguen bloqueando la creación de usuarios con un mensaje
genérico ("ya está registrado") que no permite entender qué pasa, porque el usuario eliminado no
aparece en ningún listado.

## What Changes

- **Filtro de empresa a prueba de sesiones cruzadas (frontend + backend)**: limpiar y resembrar el
  filtro al iniciar sesión; sellar el filtro persistido con el id del usuario dueño y descartarlo al
  rehidratar si no coincide; limpiar el filtro también cuando muere la sesión por refresh 401
  fallido; resetear el estado en memoria del store en el logout (hoy solo se borra la clave de
  `localStorage`); intersecar los `tenantIds` con las empresas del usuario autenticado antes de
  enviar el header; y excluir del middleware `TenantFilter` las rutas de cuenta
  (`password/force-change`, `password/change`, `me`, `logout`, `refresh`) para que un filtro
  corrupto no pueda volver a encerrar a nadie. El 403 se mantiene en las rutas de datos: es una
  comprobación de seguridad legítima.
- **Invalidación de las cachés de empresas por usuario**: un helper único que borre las dos claves
  (`user:{id}:tenant_ids` y `user:{id}:active_tenant_ids`), invocado en todos los puntos que tocan
  `user_tenants`/`user_tenant_roles` (alta, edición y borrado de usuario, asignación/retiro de
  empresa, carga masiva), conservando la invalidación existente al cambiar el estado de una empresa.
- **Mensaje claro ante correo/DNI de un usuario eliminado**: validación propia en
  `StoreUserRequest`/`UpdateUserRequest` que detecte la colisión contra una fila soft-deleted y
  devuelva un 422 que nombre al usuario eliminado y su fecha de eliminación. NO se implementa
  restaurar ni recrear usuarios (decisión del cliente): esos correos y DNIs siguen bloqueados.
- **Menor**: el refetch de la lista de usuarios tras acciones conserva búsqueda, estado y página
  actuales (`UsersListPage.tsx`).

## Capabilities

### New Capabilities
- `tenant-filter`: aislamiento del filtro de empresa entre sesiones, no-encierro en rutas de cuenta
  y frescura de las empresas del usuario tras un cambio de asignación.
- `user-management`: mensaje de validación claro cuando el correo o el DNI colisionan con un usuario
  eliminado.

### Modified Capabilities
<!-- Las specs existentes (audit-log, dashboard, user-notifications) no cubren estos comportamientos; no hay capabilities que modificar. -->

## Impact

- **Frontend**: `src/presentation/stores/tenantFilterStore.ts` (sello por usuario + validación al
  rehidratar), `src/presentation/stores/authStore.ts` (limpiar/resembrar en login, reset completo en
  logout), `src/infrastructure/http/apiClient.ts` (intersección antes de enviar `X-Tenant-Ids`;
  limpiar el filtro en refresh 401 fallido), `src/presentation/pages/admin/UsersListPage.tsx`
  (refetch con filtros).
- **Backend**: `app/Http/Middleware/TenantFilter.php` y `bootstrap/app.php` (exclusión de rutas de
  cuenta), helper de invalidación de caché invocado desde `UserController`, `TenantController`,
  la carga masiva de usuarios y `TenantService`, y validación de soft-deleted en
  `app/Http/Requests/StoreUserRequest.php` / `UpdateUserRequest.php`.
- **Sin migraciones de base de datos.** Tests: vitest para el store del filtro y tests Feature de
  Laravel para el no-encierro, la frescura de caché y el mensaje de usuario eliminado.
