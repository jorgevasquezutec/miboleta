## Context

Corrección de tres bugs relacionados con el filtro de empresa (multi-tenant) y el soft delete de
usuarios. La cadena del incidente reportado está confirmada con código y con los audit_logs de la
copia de producción:

- El store del filtro (`src/presentation/stores/tenantFilterStore.ts:266-273`) persiste en
  `localStorage` (`tenant-filter-storage`) solo `{mode, tenantIds, tenants}`: no guarda a qué
  usuario pertenece ni valida nada al rehidratar.
- `authStore.login` (`authStore.ts:134-177`) no limpia el filtro. `logout` (`authStore.ts:200-204`)
  borra la clave de `localStorage` pero no resetea el estado en memoria del otro store (nunca llama
  a `clearFilter()`), y el logout navega por SPA sin reload (`RootLayout.tsx:459-460`).
- El interceptor (`src/infrastructure/http/apiClient.ts:52-63`) lee la clave y manda `X-Tenant-Ids`
  en TODAS las peticiones; en la rama de refresh 401 fallido solo borra `auth-storage` y deja el
  filtro atrás.
- `TenantFilter` está aplicado a toda la API (`backend/bootstrap/app.php:36-38`) y devuelve 403 con
  "Las empresas seleccionadas no están asociadas a tu cuenta" cuando la intersección queda vacía
  (`TenantFilter.php:85-99`). `ForceChangePasswordPage.tsx:46,56-58` postea con `apiClient` y pinta
  ese mensaje en un toast: de ahí el pantallazo del cliente.
- Incidente real: usuario #35 creado 11:09:02 con la empresa 5 (OVERHEAD, activa), login correcto
  11:10:24 desde Edge; en ese mismo Edge la sesión previa fue el usuario 17 con la empresa 6 (IPDA
  PERU) el 12/08 **sin logout**. El filtro heredado (empresa 6) viajó en las peticiones del usuario
  nuevo → 403 → cambio de contraseña obligatorio imposible de completar.

Segunda causa independiente del mismo síntoma: dos cachés de una hora por usuario
(`TenantFilter.php:167-182` → `user:{id}:active_tenant_ids`; `TenantFilterScope.php:81-88` →
`user:{id}:tenant_ids`) cuya única invalidación en todo el backend es
`TenantService.php:148-155`, y solo cuando cambia el `status` de una empresa. Asignar o quitar
empresas, crear, borrar o importar usuarios no invalida nada.

Tercer bug: las reglas `unique` de `StoreUserRequest.php:144,146` y el
`Rule::unique(...)->ignore($userId)` de `UpdateUserRequest.php:141-151` consultan la tabla directo,
sin respetar `SoftDeletes`, y los índices `users_email_unique` / `users_document_text_unique` son
UNIQUE simples de una columna. Un usuario eliminado bloquea su correo y su DNI con un mensaje
genérico, y es invisible: el listado excluye borrados por el scope global y no existe `restore` /
`withTrashed` / `onlyTrashed` en el backend ni filtro "ver eliminados" en el frontend.

**Hallazgo sobre la "demora al eliminar" mencionada por el cliente**: se investigó y NO existe una
caché de frontend que la explique. El `requestQueue` de `apiClient.ts:38` es código muerto (se hace
`has`/`get`/`delete` pero nunca `set`), la pantalla de usuarios no usa react-query (usa `usersStore`
de zustand) y `usersStore.deleteUser` ya quita la fila del estado local. La "demora" percibida es el
bloqueo del bug de soft delete. Detalle menor real: `UsersListPage.tsx:158` recarga con
`fetchUsers()` sin argumentos y pierde búsqueda, estado y página actuales.

## Goals / Non-Goals

**Goals:**
- Un filtro de empresa persistido nunca cruza de una sesión/usuario a otro, y aunque quedara uno
  corrupto, las rutas de cuenta (cambio de contraseña, `me`, `logout`, `refresh`) siguen funcionando.
- Los cambios de asignación de empresas de un usuario surten efecto en la siguiente petición, sin
  esperar a que expire una caché de una hora.
- Al crear o editar un usuario cuyo correo/DNI colisiona con un usuario **eliminado**, el
  administrador recibe un 422 que dice quién es el usuario eliminado y cuándo se eliminó.
- El refetch de la lista de usuarios conserva búsqueda, estado y página.

**Non-Goals:**
- **Restaurar usuarios eliminados o permitir recrear con el mismo correo/DNI** (decisión del
  cliente). En consecuencia, esos correos y DNIs siguen bloqueados —incluidos los cuatro usuarios ya
  eliminados en producción (#7, #23, #34, #35)—; el alcance es solo que el mensaje lo explique.
- Cambiar los índices UNIQUE de la base de datos (sin migraciones).
- Degradar el 403 del `TenantFilter` en rutas de datos a un fallback silencioso: es una comprobación
  de seguridad legítima y se mantiene.
- Pantalla o filtro de "usuarios eliminados".

## Decisions

- **Defensa en capas para el filtro cruzado, no un solo parche.** El bug tiene varias puertas de
  entrada (login sin logout previo, sesión muerta por 401, estado en memoria tras logout), así que
  se cierran todas: resembrado en login, sello `userId` en lo persistido con descarte al rehidratar
  si no coincide con `auth-storage`, limpieza también en la rama de refresh 401 fallido, y reset del
  estado en memoria en logout. *Alternativa descartada:* solo limpiar en login — deja vivas las
  otras rutas del bug.
- **El interceptor interseca antes de enviar.** `X-Tenant-Ids` se calcula como la intersección de
  los `tenantIds` persistidos con las empresas del usuario autenticado; si queda vacía, no se envía
  el header. Es la red de seguridad del lado cliente para cualquier estado residual que sobreviva a
  lo anterior.
- **Exclusión de rutas de cuenta en el middleware, manteniendo el 403 en rutas de datos.**
  `password/force-change`, `password/change`, `me`, `logout` y `refresh` no dependen de la empresa
  seleccionada; excluirlas de `TenantFilter` garantiza que un filtro corrupto nunca vuelva a
  encerrar a nadie fuera de su cuenta. En rutas de datos el 403 se conserva tal cual.
- **Un helper único de invalidación de caché.** Borra las dos claves (`user:{id}:tenant_ids` y
  `user:{id}:active_tenant_ids`) en un solo lugar y se invoca desde todos los puntos que tocan
  `user_tenants`/`user_tenant_roles`. *Alternativa descartada:* bajar el TTL — reduce la ventana
  pero no la elimina y encarece todas las peticiones.
- **Validación propia de soft-deleted en los FormRequest, no `withoutTrashed()`.** Con
  `withoutTrashed()` la regla `unique` dejaría pasar la validación y el INSERT reventaría contra el
  índice UNIQUE de la BD (500). En su lugar, la validación detecta la colisión con la fila
  soft-deleted y devuelve un 422 con nombre del usuario eliminado y fecha de eliminación, asociado al
  campo correspondiente (que el formulario lo pinte por campo se verifica en la tarea 3.3).

## Risks / Trade-offs

- [Excluir rutas del `TenantFilter` podría relajar el aislamiento] → Las rutas excluidas son
  exclusivamente de cuenta propia (no devuelven datos de empresa); las rutas de datos conservan el
  403.
- [Invalidar caché en la carga masiva (~50k usuarios) añade escrituras a Redis] → Son deletes
  puntuales por usuario importado, coste marginal frente al job en sí; la alternativa (60 min de
  datos rancios tras un import) es peor.
- [El mensaje de 422 revela que existió un usuario con ese correo/DNI] → Lo ven solo
  administradores en el formulario de gestión de usuarios, que ya pueden ver esos datos; es
  justamente la información que necesitan para entender el bloqueo.
- [Descartar el filtro al rehidratar puede hacer que un usuario legítimo pierda su selección al
  volver a entrar tras una sesión expirada] → Aceptable: el filtro se resiembra con un valor por
  defecto válido y la selección se rehace en dos clics; lo contrario (heredar un filtro ajeno) es el
  bug que se está corrigiendo.
