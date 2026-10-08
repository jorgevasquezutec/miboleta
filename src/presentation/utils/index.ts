// Document status utilities
export {
  DOCUMENT_STATUS_CONFIG,
  getDocumentStatusBadge,
  getDocumentStatusBadgeInline,
  getDocumentStatusLabel,
  getDocumentStatusColor,
  getDigitalSignatureBadge,
} from "./documentStatus";

// Batch status utilities
export {
  BATCH_STATUS_CONFIG,
  getBatchStatusBadge,
  getBatchStatusLabel,
} from "./batchStatus";

// Gating de UI por usuario objetivo (espeja UserService::canManageUser)
export { canEditTarget } from "./userPermissions";

// Formatters
export {
  formatDate,
  formatDateTime,
  formatPeriod,
  formatFileSize,
  formatVacationDays,
  truncateText,
} from "./formatters";

// Datos limpios del firmante (sello de firma)
export { formatSignerDetails, formatCountry } from "./signerDetails";
export type { SignerDetailRow } from "./signerDetails";
