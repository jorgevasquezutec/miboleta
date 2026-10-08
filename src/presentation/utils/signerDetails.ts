import type { SignerDetails } from "@/core/domain/entities/Document";

export interface SignerDetailRow {
  label: string;
  value: string;
}

const COUNTRY_NAMES: Record<string, string> = { PE: "Perú" };

/** "PE" -> "Perú"; otros códigos tal cual. */
export function formatCountry(code: string | null | undefined): string | null {
  const c = code?.trim();
  if (!c) return null;
  return COUNTRY_NAMES[c.toUpperCase()] ?? c;
}

/**
 * Filas limpias del firmante (sin el DN crudo), omitiendo las vacías.
 * La fecha de firma no va aquí: el visor ya la muestra desde signing_time.
 */
export function formatSignerDetails(details: SignerDetails | null | undefined): SignerDetailRow[] {
  if (!details) return [];
  const place = [formatCountry(details.country), details.locality?.trim()]
    .filter((v): v is string => !!v)
    .join(" / ");

  const rows: Array<[string, string | null | undefined]> = [
    ["Representante", details.name],
    ["Razón social", details.organization],
    ["RUC", details.ruc],
    ["Cargo", details.title],
    ["País/Provincia", place],
  ];

  return rows
    .map(([label, value]) => ({ label, value: value?.trim() ?? "" }))
    .filter((r) => r.value !== "");
}
