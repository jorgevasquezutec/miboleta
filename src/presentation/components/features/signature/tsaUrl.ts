/** Valida una URL de TSA: vacía es válida (opcional); si no, debe ser http/https. */
export function isValidTsaUrl(value: string): boolean {
  const trimmed = value.trim();
  if (!trimmed) return true;
  try {
    const url = new URL(trimmed);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

export const TSA_URL_ERROR = 'Ingresa una URL válida que empiece con http:// o https://';
export const TSA_HELP_GLOBAL =
  'Opcional. Agrega una fecha y hora certificadas a la firma. Ej.: https://freetsa.org/tsr';
export const TSA_HELP_TENANT =
  'Opcional. Si lo dejas vacío se usa la TSA global de la plataforma.';
