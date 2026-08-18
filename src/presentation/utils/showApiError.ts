import { toast } from 'sonner';
import { ApiError, toApiError } from '@/infrastructure/http/apiClient';

/** Máximo de mensajes que se listan en el toast antes de resumir el resto en "+N más". */
const MAX_TOAST_LINES = 5;

/**
 * Muestra el error de una llamada a la API como toast, normalizándolo antes.
 *
 * Se apoya en `messages` (el aplanado completo de `ApiError`), nunca en
 * `data.message`: así un campo que ningún control del formulario pinta
 * (p.ej. un error anidado sin control aún) igual llega a la vista en vez de
 * perderse en silencio.
 *
 * - Un solo mensaje: toast simple con ese mensaje.
 * - Varios: título (`fallbackTitle` o uno genérico) + descripción con hasta
 *   `MAX_TOAST_LINES` mensajes, uno por línea, y "+N más" si sobran.
 *
 * Devuelve el `ApiError` normalizado para que el llamador lo siga usando
 * (p.ej. para pintar errores por campo) sin normalizar dos veces.
 */
export function showApiError(error: unknown, fallbackTitle?: string): ApiError {
  const apiError = toApiError(error);
  const { messages } = apiError;

  if (messages.length <= 1) {
    toast.error(messages[0]);
    return apiError;
  }

  const visible = messages.slice(0, MAX_TOAST_LINES);
  const remaining = messages.length - visible.length;
  const lines = remaining > 0 ? [...visible, `+${remaining} más`] : visible;

  toast.error(fallbackTitle ?? 'Se encontraron varios errores', {
    description: lines.join('\n'),
    descriptionClassName: 'whitespace-pre-line',
  });

  return apiError;
}
