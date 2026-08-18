## 1. Filtro de empresa a prueba de sesiones cruzadas

- [x] 1.1 En `src/presentation/stores/authStore.ts` (`login`, líneas 134-177): limpiar el filtro persistido y resembrarlo para el usuario que entra (llamando al store del filtro, no solo tocando `localStorage`)
- [x] 1.2 En `src/presentation/stores/tenantFilterStore.ts` (persist, líneas 266-273): incluir en el `partialize` el id del usuario dueño del filtro y, al rehidratar, descartar el filtro si ese id no coincide con el usuario de `auth-storage`
- [x] 1.3 En `src/infrastructure/http/apiClient.ts` (rama de refresh 401 fallido): además de `auth-storage`, eliminar `tenant-filter-storage` para que una sesión que muere sin logout no deje el filtro atrás
- [x] 1.4 En `src/presentation/stores/authStore.ts` (`logout`, líneas 200-204): resetear el estado en memoria del store del filtro (invocar `clearFilter()`), no solo borrar la clave de `localStorage` (el logout navega por SPA sin reload, `RootLayout.tsx:459-460`)
- [x] 1.5 En `src/infrastructure/http/apiClient.ts` (interceptor de request, líneas 52-63): intersecar los `tenantIds` persistidos con las empresas del usuario autenticado antes de enviar `X-Tenant-Ids`; si la intersección queda vacía, no enviar el header
- [x] 1.6 En backend (`backend/app/Http/Middleware/TenantFilter.php` y/o `backend/bootstrap/app.php:36-38`): excluir del middleware las rutas de cuenta `password/force-change`, `password/change`, `me`, `logout` y `refresh`, manteniendo el 403 actual (`TenantFilter.php:85-99`) en las rutas de datos
- [x] 1.7 Test vitest del store del filtro: rehidratar un `tenant-filter-storage` sellado con otro dueño → el filtro se descarta (y uno del mismo dueño se conserva)
- [x] 1.8 Test Feature de Laravel: un usuario autenticado que envía `X-Tenant-Ids` con una empresa ajena puede completar `POST /password/force-change` (no recibe el 403 del `TenantFilter`)

## 2. Invalidación de las cachés de empresas por usuario

- [x] 2.1 Crear un helper único que borre las dos claves de caché de un usuario: `user:{id}:tenant_ids` (`TenantFilterScope.php:81-88`) y `user:{id}:active_tenant_ids` (`TenantFilter.php:167-182`)
- [x] 2.2 Invocar el helper en todos los puntos que tocan `user_tenants`/`user_tenant_roles`: alta de usuario (`UserController::store`), edición (`UserController::update`), borrado (`UserController::destroy`, líneas 424-455), `TenantController::addUser` y `removeUser`, y la carga masiva de usuarios
- [x] 2.3 Conservar la invalidación existente de `TenantService::updateTenant` (`TenantService.php:148-155`) al cambiar el estado de una empresa
- [x] 2.4 Test Feature de Laravel: con la caché caliente, asignar una empresa a un usuario y verificar que la siguiente petición con `X-Tenant-Ids` de esa empresa responde 200 sin esperar a que expire la caché

## 3. Mensaje claro ante correo/DNI de un usuario eliminado

- [x] 3.1 En `backend/app/Http/Requests/StoreUserRequest.php` (reglas en líneas 144,146): añadir validación propia que detecte la colisión de `email` y `document_text` contra una fila soft-deleted y devuelva 422 con un mensaje que nombre al usuario eliminado y su fecha de eliminación, indicando a quién dirigirse; mantener el mensaje actual para colisiones con usuarios activos
- [x] 3.2 En `backend/app/Http/Requests/UpdateUserRequest.php` (líneas 141-151): misma validación sobre el `Rule::unique(...)->ignore($userId)` existente
- [x] 3.3 Verificar que el formulario del frontend de crear/editar usuario pinta el mensaje del 422 por campo (email y documento)
- [x] 3.4 Test Feature de Laravel: crear un usuario con el correo de uno eliminado devuelve 422 con el mensaje específico (nombre y fecha de eliminación), no el genérico "ya está registrado"
- [x] 3.5 En `src/presentation/pages/admin/UsersListPage.tsx:158`: el refetch tras acciones conserva búsqueda, estado y página actuales (hoy llama `fetchUsers()` sin argumentos)

## 4. Cierre

- [x] 4.1 `openspec validate fix-filtro-empresa-y-usuario-eliminado --strict` en verde
- [ ] 4.2 Verificación manual del usuario: reproducir el escenario del incidente (sesión previa de otro usuario sin logout en el mismo navegador → login del usuario nuevo → cambio de contraseña obligatorio completa sin 403) y el mensaje de usuario eliminado en el formulario de creación
