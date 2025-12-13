import { Clock, RefreshCw, CheckCircle, XCircle, AlertTriangle } from "lucide-react";
import { DocumentBatch } from "@/core/domain/entities/DocumentBatch";

/**
 * Configuración de estados para batches de carga
 */
export const BATCH_STATUS_CONFIG = {
  pending: {
    label: "Pendiente",
    bgColor: "#eab308",
    icon: Clock,
  },
  processing: {
    label: "Procesando",
    bgColor: "#3b82f6",
    icon: RefreshCw,
  },
  completed: {
    label: "Completado",
    bgColor: "#22c55e",
    icon: CheckCircle,
  },
  failed: {
    label: "Fallido",
    bgColor: "#ef4444",
    icon: XCircle,
  },
  partial: {
    label: "Parcial",
    bgColor: "#f97316",
    icon: AlertTriangle,
  },
} as const;

/**
 * Renderiza un badge de estado para batches
 * @param status - Estado del batch
 * @returns Span element con el badge
 */
export function getBatchStatusBadge(status: DocumentBatch['status']) {
  const config = BATCH_STATUS_CONFIG[status] || BATCH_STATUS_CONFIG.pending;
  const Icon = config.icon;

  return (
    <span
      style={{
        backgroundColor: config.bgColor,
        color: "white",
      }}
      className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium"
    >
      <Icon className={`w-3 h-3 ${status === 'processing' ? 'animate-spin' : ''}`} />
      {config.label}
    </span>
  );
}

/**
 * Obtiene solo el label del estado del batch
 * @param status - Estado del batch
 * @returns Label del estado
 */
export function getBatchStatusLabel(status: DocumentBatch['status']): string {
  return BATCH_STATUS_CONFIG[status]?.label || BATCH_STATUS_CONFIG.pending.label;
}
