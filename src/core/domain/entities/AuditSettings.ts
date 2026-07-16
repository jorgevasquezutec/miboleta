// Domain Entity - Mantenedor de auditoría. Cada ítem describe un tipo de log
// (acción) con su estado de captura. Las acciones `locked` (críticas de
// seguridad/legal) no se pueden desactivar. Solo root (GET/PUT /audit/settings).
export interface AuditActionSetting {
  action: string;
  label: string;
  category: string;
  enabled: boolean;
  locked: boolean;
  count: number;
}
