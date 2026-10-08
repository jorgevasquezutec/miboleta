import { Badge } from "@/presentation/components/ui/badge";
import { ShieldCheck, Loader2, ShieldAlert } from "lucide-react";
import { Document, DigitalSignatureStatus } from "@/core/domain/entities/Document";

/**
 * Configuración de colores para badges de estado de documentos
 */
export const DOCUMENT_STATUS_CONFIG = {
  pending: {
    label: "Pendiente Firma",
    className: "bg-yellow-500 text-white",
    bgColor: "#eab308",
  },
  signed: {
    // Conformidad del trabajador (código por correo); no es la firma digital de la empresa
    label: "Firmado por trabajador",
    className: "bg-green-500 text-white",
    bgColor: "#22c55e",
  },
  active: {
    label: "Disponible",
    className: "bg-blue-500 text-white",
    bgColor: "#3b82f6",
  },
  orphan: {
    label: "Huérfano",
    className: "bg-orange-500 text-white",
    bgColor: "#f97316",
  },
  expired: {
    label: "Expirado",
    className: "bg-red-500 text-white",
    bgColor: "#ef4444",
  },
} as const;

/**
 * Renderiza un badge de estado para documentos usando shadcn/ui Badge
 * @param status - Estado del documento
 * @returns Badge component
 */
export function getDocumentStatusBadge(status: Document['status']) {
  if (!status) {
    return (
      <Badge
        className="text-white border-none"
        style={{ backgroundColor: '#3b82f6' }}
      >
        Disponible
      </Badge>
    );
  }
  const config = DOCUMENT_STATUS_CONFIG[status] || DOCUMENT_STATUS_CONFIG.active;
  return (
    <Badge
      className="text-white border-none"
      style={{ backgroundColor: config.bgColor }}
    >
      {config.label}
    </Badge>
  );
}

/**
 * Renderiza un badge de estado usando estilos inline (para tablas o contextos sin shadcn)
 * @param status - Estado del documento
 * @returns Span element con estilos inline
 */
export function getDocumentStatusBadgeInline(status: Document['status']) {
  const config = DOCUMENT_STATUS_CONFIG[status] || DOCUMENT_STATUS_CONFIG.pending;
  return (
    <span
      style={{ backgroundColor: config.bgColor, color: "white" }}
      className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium"
    >
      {config.label}
    </span>
  );
}

/**
 * Obtiene solo el label del estado
 * @param status - Estado del documento
 * @returns Label del estado
 */
export function getDocumentStatusLabel(status: Document['status']): string {
  return DOCUMENT_STATUS_CONFIG[status]?.label || DOCUMENT_STATUS_CONFIG.pending.label;
}

/**
 * Obtiene solo el color del estado
 * @param status - Estado del documento
 * @returns Color hexadecimal del estado
 */
export function getDocumentStatusColor(status: Document['status']): string {
  return DOCUMENT_STATUS_CONFIG[status]?.bgColor || DOCUMENT_STATUS_CONFIG.pending.bgColor;
}

/**
 * Insignia de la firma digital (PAdES) de la empresa, independiente de la
 * conformidad del trabajador. Devuelve null si el documento no tiene firma
 * digital ni intento en curso.
 *
 * - signed  → verde "Firma digital"
 * - pending → ámbar "Firmando…"
 * - failed  → rojo "Error firma" (si ya había firma previa se sigue sirviendo)
 */
export function getDigitalSignatureBadge(
  doc: Pick<Document, 'digitalSignature' | 'digitalSignatureStatus'>
) {
  const status: DigitalSignatureStatus | null =
    doc.digitalSignatureStatus ?? (doc.digitalSignature ? 'signed' : null);
  if (!status) return null;

  const config = {
    signed: { label: "Firma digital", bgColor: "#16a34a", Icon: ShieldCheck, spin: false },
    pending: { label: "Firmando…", bgColor: "#d97706", Icon: Loader2, spin: true },
    failed: { label: "Error firma", bgColor: "#dc2626", Icon: ShieldAlert, spin: false },
  }[status];

  return (
    <span
      data-testid="digital-signature-badge"
      style={{ backgroundColor: config.bgColor, color: "white" }}
      className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium"
    >
      <config.Icon className={`w-3 h-3${config.spin ? " animate-spin" : ""}`} />
      {config.label}
    </span>
  );
}
