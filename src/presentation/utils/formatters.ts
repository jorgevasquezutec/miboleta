/**
 * Formatea una fecha a formato local peruano
 * @param dateString - Fecha en formato ISO string
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

  return new Date(dateString).toLocaleDateString(locale, {
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
 * Trunca un texto largo agregando puntos suspensivos
 * @param text - Texto a truncar
 * @param maxLength - Longitud máxima
 * @returns Texto truncado
 */
export function truncateText(text: string, maxLength: number = 50): string {
  if (text.length <= maxLength) return text;
  return `${text.substring(0, maxLength)}...`;
}
