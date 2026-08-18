## 1. Normalización única del error (fase 1)

- [x] 1.1 Ampliar `ApiError` y `toApiError` en `src/infrastructure/http/apiClient.ts:316-325` con `status`, `code`, `messages` (todos los mensajes aplanados) y `data` (payload crudo), haciendo `toApiError` idempotente
- [x] 1.2 Corregir la prioridad del resumen en `getErrorMessage` (`apiClient.ts:257-288`, rama 265-267): si el status es 422 y hay `data.errors`, construir `message` y `messages` desde `errors` e ignorar `data.message` (de ahí sale el "(and N more errors)" de `ValidationException::summarize()`)
- [x] 1.3 Test unitario de `toApiError` con el payload exacto del incidente (`{"message":"Este número de documento ya está registrado (and 1 more error)","errors":{"document_text":[...],"tenants_config.0.hire_date":[...]}}`): `message` sin el literal "(and 1 more error)", `messages.length === 2`, `fieldErrors` con las dos claves, y `toApiError(toApiError(e)) === toApiError(e)`

## 2. Presentación: ningún error se pierde (fase 1)

- [x] 2.1 ★ Crear `showApiError(error, fallbackTitle?)`: arma el toast desde `messages` (uno solo → toast simple; varios → título + `description` con hasta 5 líneas y "+N más") y devuelve el `ApiError` normalizado
- [x] 2.2 Crear `src/presentation/components/shared/FieldError.tsx` con `FieldError` (sustituye las 10 copias de `<p className="text-sm text-red-500">`) y `FormErrorSummary` (bloque de errores sin control asignado)
- [x] 2.3 Crear `src/presentation/hooks/useFormErrors.ts` con el hook y el helper puro `extractNestedErrors`, según las firmas del design; `applyApiError` parte los errores en conocidos (a `errors`/`nestedErrors`) y desconocidos (a `formErrors`)
- [x] 2.4 Tests unitarios de `extractNestedErrors` (reagrupa `tenants_config.0.hire_date` bajo el id de empresa correcto y devuelve las claves consumidas) y de `useFormErrors` (la partición conocido/desconocido no pierde ningún mensaje)

## 3. Formulario de usuario y errores por empresa (fase 1)

- [x] 3.1 ★ Migrar `src/presentation/pages/admin/UserFormPage.tsx` al hook: quitar el `useState<Record<string,string>>` (65) y dejar el catch (331-346) en `applyApiError(error, { prefix: 'tenants_config', indexToKey: i => selectedTenantIds[i] })` + `showApiError`; pintar `FormErrorSummary` con `formErrors`
- [x] 3.2 ★ Añadir la prop `fieldErrorsByTenant` a `TenantAssignmentCard.tsx` (junto a la `error?: string` de la línea 81, que se conserva para la validación de cliente) y pintar el error bajo cada control por empresa, incluido el input de fecha de inicio (322-327), que hoy no tiene ninguna
- [x] 3.3 Verificar el caso del incidente: documento duplicado + fecha futura → toast con los dos mensajes, error bajo el documento y error bajo la fecha de la empresa correcta

## 4. Backend: formato de validación consistente (fase 1)

- [x] 4.1 ★ Retirar el override de `failedValidation()` en `backend/app/Http/Requests/CustomFormRequest.php:19-35` (o hacer que `StoreTenantRequest`/`UpdateTenantRequest` extiendan `FormRequest` y eliminar la clase) para que las empresas devuelvan `{message, errors}` como el resto
- [x] 4.2 Test Feature de Laravel: crear una empresa con RUC duplicado devuelve 422 con la clave `errors` y todos los campos que fallaron, no solo el primer mensaje

## 5. Repositorio de usuarios (fase 1)

- [x] 5.1 `src/infrastructure/persistence/repositories/UserRepository.ts`: sustituir los `throw new Error(getErrorMessage(error))` de las líneas 25, 53, 70, 79, 105 y 152 por `toApiError`, para que todo el flujo de usuarios conserve los errores por campo

## 6. Migración del resto (fase 2)

- [x] 6.1 Migrar los 17 consumidores que leen `err.response?.data` o usan `axios.isAxiosError` fuera de `apiClient.ts` (entre ellos `src/presentation/stores/authStore.ts`, `VacationRequestFormPage.tsx:219-236` y `src/presentation/hooks/useBatchProgress.ts`) para que usen `toApiError`
- [x] 6.2 Activar la normalización en el interceptor de respuesta (`apiClient.ts:250` → rechazar con `toApiError(error)`), respetando la rama de refresh 401 (168-217)
- [x] 6.3 Eliminar los 23 `throw new Error(getErrorMessage(...))` restantes (`RoleRepository.ts:17`, `TenantRepository.ts` 19/31/43/55/66/78/92/103/116, `ReportsRepository.ts` 32/45/58/71/86/98/110/125/152/179/207/222/237/252) y añadir el `try/catch` que falta en `VacationRepository.create()`
- [x] 6.4 ★ Migrar `TenantFormPage.tsx`: el catch vacío (171-173) pasa a usar el hook, y `tenantsStore.ts:128-133,153-158` deja de tostear y re-lanza el error para que la página pinte por campo
- [x] 6.5 ★ Migrar `VacationRequestFormPage.tsx`: sustituir el parseo manual de `err.response.data.errors` (219-236) por `applyApiError` + `showApiError`
- [x] 6.6 Migrar las tres páginas restantes con estado de errores propio: `ResetPasswordPage.tsx`, `ForceChangePasswordPage.tsx` y `AuditSettingsPage.tsx`
- [x] 6.7 ★ Añadir el `renderable` de `ValidationException` en `backend/bootstrap/app.php` (importada en la línea 12 y sin usar): mismo formato `{message, errors}` pero con `message` = primer mensaje real, sin el sufijo de `summarize()`
- [x] 6.8 Consolidar sobre `showApiError` los `toast.error(` que hoy muestran errores de API en los catch de las páginas ya migradas, y dejar constancia de cuáles quedan fuera

## 7. Cierre

- [x] 7.1 `openspec validate centralizar-manejo-de-errores-api --strict` en verde
- [x] 7.2 Suites completas en verde (`npx vitest run` y `php artisan test` en `miboleta_app`) y `tsc --noEmit` sin errores nuevos respecto de la base
- [ ] 7.3 Verificación manual del usuario: reproducir el incidente del formulario de usuario y comprobar además un 422 del formulario de empresas
