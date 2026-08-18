## Why

Al guardar un usuario con dos errores de validación —documento duplicado y fecha de inicio laboral
futura— el administrador solo ve un toast con el primer mensaje y el literal en inglés
"(and 1 more error)". El segundo error no aparece en ninguna parte: es un campo anidado por empresa
(`tenants_config.0.hire_date`) y ningún control de la interfaz sabe pintarlo. El usuario corrige lo
que ve, vuelve a guardar y vuelve a fallar sin saber por qué.

La causa no es un fallo puntual de esa pantalla, sino que **el manejo de errores no está
centralizado**, aunque exista un intento de helpers en `src/infrastructure/http/apiClient.ts`:

- `throw new Error(getErrorMessage(error))` se repite **29 veces** en 4 repositorios y en todas
  ellas se descarta el objeto `errors` del 422: solo sobrevive el primer mensaje como string.
- `toApiError`, que sí conserva los errores por campo, se usa en **2 de 31** sitios.
- Hay **3 implementaciones distintas** del mismo parseo de `data.errors` (`apiClient.ts`,
  `VacationRequestFormPage.tsx:219-236`, `useEditableUsers.ts:327-350`).
- **77** llamadas a `toast.error(`, **6** páginas con su propio `useState<Record<string, string>>` y
  **10** copias literales del mismo JSX de error, sin ningún componente compartido.
- En el backend conviven **5 formatos** de error distintos. Uno de ellos,
  `CustomFormRequest::failedValidation()`, devuelve solo el primer mensaje y **sin la clave
  `errors`**, así que el formulario de empresas no podría pintar errores por campo ni queriendo.

## What Changes

- **Un único punto de normalización en el cliente HTTP**: `ApiError` pasa a llevar `status`, `code`,
  `fieldErrors` y `messages` (todos los mensajes aplanados), y `toApiError` se vuelve idempotente.
  Para un 422 con `errors`, el resumen se construye desde `errors` e **ignora `data.message`**, que
  es de donde sale el "(and N more errors)" que genera Laravel. Al final de la migración, la
  normalización se activa en el interceptor de respuesta, de modo que ningún repositorio pueda
  olvidarse de aplicarla.
- **Ningún error se pierde en silencio**, con doble red: el toast se construye siempre desde
  `messages` (varios mensajes, no solo el primero) y el formulario separa los errores que sabe
  pintar de los que no, mandando estos últimos a un resumen visible encima del formulario.
- **Errores anidados por empresa**: `tenants_config.{i}.{campo}` se reagrupa por empresa y se pinta
  en el control correcto de esa empresa, incluida la fecha de inicio laboral, que hoy no tiene
  ninguna propiedad de error.
- **Piezas compartidas**: un hook `useFormErrors` y los componentes `FieldError` /
  `FormErrorSummary` que sustituyen los 6 estados de error locales y las 10 copias del JSX.
- **Backend, mínimo**: se retira el override de `CustomFormRequest`, único punto que destruye
  información, y se añade un `renderable` para `ValidationException` con el mensaje de resumen en
  español. No se tocan los 398 `response()->json(` manuales ni los renderable existentes.
- **Migración completa**: los 17 consumidores que hoy leen `err.response.data` a mano pasan por el
  normalizador, y desaparecen los 29 `throw new Error(getErrorMessage(...))`.

## Capabilities

### New Capabilities
- `api-error-handling`: contrato de errores de la API y su presentación en la interfaz — todos los
  mensajes visibles, ningún error huérfano, errores anidados en su control, y normalización única.

### Modified Capabilities
<!-- Las specs existentes (audit-log, dashboard, user-notifications, tenant-filter, user-management)
     no describen el manejo de errores; no hay capabilities que modificar. -->

## Impact

- **Frontend**: `src/infrastructure/http/apiClient.ts` (ApiError, toApiError, getErrorMessage,
  interceptor de respuesta), nuevos `src/presentation/hooks/useFormErrors.ts` y
  `src/presentation/components/shared/FieldError.tsx`, los 4 repositorios
  (`UserRepository`, `TenantRepository`, `ReportsRepository`, `RoleRepository`) y `VacationRepository`,
  los stores que degradan el error a string (`tenantsStore`, `usersStore`, `auditSettingsStore`,
  `vacationsStore`), y las 6 páginas con estado de errores propio, además de
  `TenantAssignmentCard.tsx`.
- **Backend**: `app/Http/Requests/CustomFormRequest.php` (se retira el override) y
  `bootstrap/app.php` (renderable de `ValidationException`, ya importada y sin usar).
- **Sin migraciones de base de datos.** Tests: unitarios de `toApiError`, `extractNestedErrors` y
  `useFormErrors` en vitest, y un test Feature de Laravel para el formato de validación de empresas.
