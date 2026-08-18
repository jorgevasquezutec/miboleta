## Context

El caso reportado: guardar un usuario con documento duplicado y fecha de inicio laboral futura
devuelve un 422 con dos errores, y el administrador solo ve el primero más el literal
"(and 1 more error)". Cadena exacta, verificada:

1. `StoreUserRequest` usa el formato por defecto de Laravel: `{message, errors}`. El `message` lo
   genera `Illuminate\Validation\ValidationException::summarize()`, que toma el primer mensaje y le
   concatena `" (and :count more error(s))"`. Ahí nace el texto en inglés.
2. `UserRepository.create()` (`UserRepository.ts:135`) llama a `toApiError`, y `getErrorMessage`
   (`apiClient.ts:257-288`) encuentra `data.message` en la rama 265-267 **antes** de mirar
   `data.errors`, así que devuelve el mensaje truncado con el literal. `getFieldErrors`
   (`apiClient.ts:296-310`) sí extrae los dos campos.
3. `UserFormPage.tsx:331-346` vuelca `error.fieldErrors` al estado (339) y tosta `error.message`
   (342): **primer síntoma**, el toast con "(and 1 more error)".
4. `errors['document_text']` se pinta porque el input de documento lee esa clave.
   `errors['tenants_config.0.hire_date']` también llega al estado, pero ningún control lo lee:
   `TenantAssignmentCard` solo recibe una prop plana `error?: string` (`TenantAssignmentCard.tsx:81`)
   alimentada por validación de cliente (`UserFormPage.tsx:450`), y el input de fecha
   (`TenantAssignmentCard.tsx:322-327`) no tiene ninguna prop de error. **Segundo síntoma**: el error
   muere en silencio.

Caso agravado en empresas: `CustomFormRequest.php:19-35` sobreescribe `failedValidation()` con dos
`break` y devuelve `{message}` con el primer error del primer campo, **sin `errors`**. Lo usan
`StoreTenantRequest` y `UpdateTenantRequest`, así que ahí el frontend no tiene datos que pintar.

Estado de la centralización, en números: 29 `throw new Error(getErrorMessage(...))` que descartan
`errors`; `toApiError` usado en 2 de 31 sitios; el interceptor de respuesta
(`apiClient.ts:145-252`) que para 403/404/422/500 solo hace `console.error` y re-lanza el error
crudo; 77 `toast.error(`; 6 estados de error locales; 10 copias del JSX de error; 3 parseos
distintos de `data.errors`; 5 formatos de error en el backend y 398 `response()->json(` a mano.

## Goals / Non-Goals

**Goals:**
- Ante un 422 con N errores, el usuario ve los N, siempre, sin importar en qué pantalla esté.
- Un error cuyo campo no tiene control visible se sigue viendo (toast y resumen del formulario), en
  vez de perderse.
- Los errores anidados por empresa se pintan junto al control de la empresa correcta.
- Una sola implementación de la normalización, imposible de saltarse por olvido.
- Los formularios dejan de mantener su propio estado de errores y su propio JSX.

**Non-Goals:**
- Unificar los 5 formatos de error del backend ni crear un trait `ApiResponse` sobre los 398
  `response()->json(`: el normalizador del cliente ya entiende esos formatos.
- Introducir una librería de formularios (`react-hook-form` u otra).
- Traducir al español los mensajes de validación por defecto de Laravel que hoy ya vienen definidos
  campo a campo en cada FormRequest.

## Decisions

- **La normalización vive en el interceptor de respuesta de axios**, con `toApiError` como helper
  idempotente que el interceptor usa y que sigue exportado. Con el interceptor, los repositorios no
  necesitan `try/catch` —los 29 `throw new Error(...)` se borran en vez de reescribirse— y un
  repositorio nuevo no puede olvidarse de normalizar. *Alternativa descartada:* helper por
  repositorio, que no impide el trigésimo `throw new Error(...)`: la garantía tiene que ser
  estructural.
- **Pero el interceptor se activa al final, no al principio.** Hay 17 consumidores fuera de
  `apiClient.ts` que leen `err.response?.data` o usan `axios.isAxiosError` directamente (entre ellos
  `authStore.ts`, `VacationRequestFormPage.tsx:219-236`, `useBatchProgress.ts`); activarlo antes de
  migrarlos los rompería. Por eso el orden es: ampliar `ApiError` → migrar consumidores → activar el
  interceptor. `ApiError.data` conserva el payload crudo como red durante la transición.
- **Forma del error normalizado** (misma clase `ApiError` de `apiClient.ts:316-321`, ampliada):

  ```ts
  export class ApiError extends Error {
    readonly name = 'ApiError';
    readonly status?: number;                    // undefined = error de red
    readonly code: 'validation' | 'forbidden' | 'not_found' | 'server' | 'network' | 'unknown';
    /** TODOS los mensajes, aplanados en orden de aparición. Nunca vacío. */
    readonly messages: string[];
    /** campo -> primer mensaje (claves tal cual llegan, incluidas las anidadas). */
    readonly fieldErrors: Record<string, string>;
    /** Payload crudo de la respuesta, para consumidores aún sin migrar. */
    readonly data?: unknown;
    // `message` (heredado) = primer mensaje REAL, nunca el "(and N more errors)" de Laravel.
  }
  export const toApiError: (error: unknown) => ApiError; // idempotente
  ```

- **Regla de construcción del resumen**: si `status === 422` y existe `data.errors`, `message` y
  `messages` se construyen desde `data.errors` **ignorando `data.message`**. Para el resto de
  respuestas, la prioridad sigue siendo `data.error` → `data.message` → `error.message`, que cubre
  los otros cuatro formatos del backend sin tocarlos. *Alternativa descartada:* que el backend
  aplane todos los mensajes en `message`, que rompería el contrato `{message, errors}` estándar.
- **Ningún error se pierde en silencio**, con dos redes independientes:
  1. `showApiError(error, fallbackTitle?)` construye el toast desde `messages`, no desde
     `data.message`: con un mensaje, toast simple; con varios, título más `description` (sonner, ya
     en el stack) listando hasta 5 y "+N más". Como `messages` es el aplanado completo, **una clave
     que ningún control mapea aparece igualmente en el toast**.
  2. `useFormErrors.applyApiError` parte los errores en conocidos —los que el formulario sabe
     pintar, incluidos los anidados que consume `extractNestedErrors`— y desconocidos, que van a
     `formErrors` y se pintan en un `FormErrorSummary` encima del formulario.
- **Claves anidadas**: `tenants_config[i]` equivale a `selectedTenantIds[i]` porque el payload se
  construye con ese mismo array (`UserFormPage.tsx:303-316`). Un helper puro las reagrupa por
  empresa y la card recibe el mapa ya resuelto:

  ```ts
  export function extractNestedErrors(
    fieldErrors: Record<string, string>,
    prefix: string,                                 // 'tenants_config'
    indexToKey: (i: number) => string | undefined,  // i => selectedTenantIds[i]
  ): { nested: Record<string, Record<string, string>>; consumed: string[] };

  // TenantAssignmentCard, junto a la prop `error?: string` ya existente (:81):
  fieldErrorsByTenant?: Record<string, Record<string, string>>; // tenantId -> campo backend -> mensaje
  ```

  La card indexa por `tenant.id` y pinta con la clave **del backend** (`hire_date`, `role_ids`,
  `vacation_balance_initial`, `supervisor_id`, `department`, `position`): usar los nombres del
  payload evita una tabla de traducción a los nombres del formulario.
- **Piezas compartidas de formulario**:

  ```ts
  // src/presentation/hooks/useFormErrors.ts
  export function useFormErrors(options?: { knownFields?: string[] }): {
    errors: Record<string, string>;
    formErrors: string[];
    nestedErrors: Record<string, Record<string, string>>;
    hasErrors: boolean;
    setFieldError(field: string, message: string): void;
    setErrors(errors: Record<string, string>): void;
    clearError(field: string): void;
    clearAll(): void;
    applyApiError(
      error: unknown,
      nested?: { prefix: string; indexToKey: (i: number) => string | undefined },
    ): ApiError;
  };

  // src/presentation/components/shared/FieldError.tsx
  export function FieldError(props: { message?: string }): JSX.Element | null;
  export function FormErrorSummary(props: { messages: string[] }): JSX.Element | null;
  ```

- **Backend, solo lo que destruye información**: se retira el override de
  `CustomFormRequest::failedValidation()` (`CustomFormRequest.php:19-35`) para que las empresas
  devuelvan `{message, errors}` como el resto, y se añade el `renderable` de `ValidationException`
  en `bootstrap/app.php` (ya importada en la línea 12 y sin usar) para que el `message` de resumen
  sea el primer mensaje real, en español, sin el sufijo de `summarize()`. *Descartado:* unificar los
  5 formatos y los 398 `response()->json(`, 21 archivos de superficie por un beneficio que el
  normalizador ya da.

## Risks / Trade-offs

- [Activar el interceptor antes de migrar los 17 lectores directos de `AxiosError` rompería su
  parseo] → Es el motivo del orden 6.1 antes que 6.2, y `ApiError.data` conserva el payload crudo
  mientras tanto.
- [El emparejamiento `tenants_config[i]` ↔ `selectedTenantIds[i]` es un acoplamiento implícito: si
  el payload se reordenara, el error se pintaría en la empresa equivocada] → `indexToKey` se
  construye desde el mismo array que arma el payload, con un comentario en el sitio que lo ata.
- [Retirar `CustomFormRequest` cambia la respuesta de dos endpoints] → Ganan la clave `errors`; el
  frontend actual la ignora, así que no rompe nada, pero es cambio de contrato y va marcado como
  visible.
- [Un 422 con muchos errores podría producir un toast enorme] → Se capan a 5 líneas más "+N más"; el
  detalle completo está en el resumen del formulario y junto a cada campo.
- [La migración toca muchos archivos a la vez] → Se ordena en dos fases: la primera resuelve el caso
  reportado y deja la base; la segunda es mecánica y verificable archivo por archivo con las suites
  existentes.
