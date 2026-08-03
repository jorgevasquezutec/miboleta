import { parseISO, isValid } from "date-fns";

/**
 * Formatea una fecha a formato local peruano
 * @param dateString - Fecha en formato ISO string: solo fecha (`YYYY-MM-DD`)
 *   o timestamp completo con hora/zona (ej. `2026-07-16T14:30:00Z`)
 * @param includeTime - Si incluir hora y minutos
 * @returns Fecha formateada
 */
export function formatDate(
  dateString: string | null,
  options?: {
    includeTime?: boolean;
    locale?: string;
  }
): string {
  if (!dateString) return "-";

  const { includeTime = false, locale = "es-PE" } = options || {};

  // `parseISO` (a diferencia de `new Date(...)`) interpreta una fecha sin
  // componente horario (`YYYY-MM-DD`) como medianoche en la zona LOCAL, no
  // en UTC. `new Date('2026-07-16')` en cambio la trata como UTC, y al
  // mostrarla en una zona detrás de UTC (Perú, UTC-5) retrocede un día.
  // Para timestamps completos con hora y offset/"Z" el resultado es el
  // mismo que antes: se convierten correctamente a la hora local.
  const date = parseISO(dateString);
  if (!isValid(date)) return "-";

  return date.toLocaleDateString(locale, {
    day: "2-digit",
    month: includeTime ? "2-digit" : "short",
    year: "numeric",
    ...(includeTime && {
      hour: "2-digit",
      minute: "2-digit",
    }),
  });
}

/**
 * Formatea una fecha con hora completa
 * @param dateString - Fecha en formato ISO string
 * @returns Fecha con hora formateada
 */
export function formatDateTime(dateString: string | null): string {
  return formatDate(dateString, { includeTime: true });
}

/**
 * Formatea un período YYYY-MM a formato legible
 * @param period - Período en formato YYYY-MM
 * @returns Período formateado (ej: "Enero 2024")
 */
export function formatPeriod(period: string): string {
  const [year, month] = period.split("-");
  const monthNames = [
    "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
  ];

  const monthIndex = parseInt(month, 10) - 1;
  return `${monthNames[monthIndex]} ${year}`;
}

/**
 * Formatea bytes a tamaño legible
 * @param bytes - Tamaño en bytes
 * @returns Tamaño formateado (ej: "1.5 MB")
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return "0 Bytes";

  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

/**
 * Formatea una cifra de días de vacaciones recortando ceros sobrantes:
 * "70" en vez de "70.00", "27.5" en vez de "27.50". El backend
 * (VacationBalanceService) ya redondea a 2 decimales; esto solo limpia la
 * presentación.
 *
 * Vive aquí, y no en la página que lo estrenó (UsersListPage), porque las
 * cifras de vacaciones se pintan en más de un sitio —el listado de usuarios y
 * la tarjeta de solicitudes del aprobador— y deben verse igual en todos.
 * @param value - Días con hasta 2 decimales
 * @returns Cifra sin ceros de relleno
 */
export function formatVacationDays(value: number): string {
  return String(Number(value.toFixed(2)));
}

/**
 * Trunca un texto largo agregando puntos suspensivos
 * @param text - Texto a truncar
 * @param maxLength - Longitud máxima
 * @returns Texto truncado
 */
export function truncateText(text: string, maxLength: number = 50): string {
  if (text.length <= maxLength) return text;
  return `${text.substring(0, maxLength)}...`;
}
