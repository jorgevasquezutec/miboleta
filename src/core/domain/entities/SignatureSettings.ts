// Domain Entity - Configuración del certificado de firma digital GLOBAL de la
// plataforma (aligned with backend SignatureSettingsController::transform()).
// Se usa como fallback para las empresas que no tienen certificado propio.
// Solo root puede leer/editar esto (GET/PUT /signature/settings,
// POST/DELETE /signature/certificate). Nunca expone password ni el binario.
export interface SignatureSettings {
  signatureEnabled: boolean;
  hasCertificate: boolean;
  certificateSubject: string | null;
  certificateRuc: string | null;
  certificateOrganization: string | null;
  certificateExpiresAt: string | null;
  tsaUrl: string | null;
  uploadedAt: string | null;
}

// Origen del certificado efectivo de una empresa (GET /signature/tenants).
export type CertificateSource = 'tenant' | 'global' | 'none';

export type CertificateWarningCode =
  | 'ruc_mismatch'
  | 'ruc_not_found'
  | 'expired'
  | 'expiring_soon'
  | 'no_certificate';

export interface CertificateWarning {
  code: CertificateWarningCode;
  message: string;
}

// Datos del certificado PROPIO de una empresa (nunca password ni ruta).
export interface TenantCertificateDetails {
  certificateSubject: string | null;
  certificateRuc: string | null;
  certificateOrganization: string | null;
  certificateExpiresAt: string | null;
  /** TSA propia de la empresa; null = usa la TSA global */
  tsaUrl: string | null;
  uploadedAt: string | null;
  uploadedBy: { id: number; name: string } | null;
}

export interface TenantCertificateStatus {
  tenantId: number;
  tenantName: string;
  tenantRuc: string | null;
  tenantBusinessName: string | null;
  tenantStatus: string;
  certificateSource: CertificateSource;
  hasOwnCertificate: boolean;
  certificate: TenantCertificateDetails | null;
  rucMismatch: boolean;
  warnings: CertificateWarning[];
}

export interface TenantCertificatesSummary {
  total: number;
  currentPage: number;
  lastPage: number;
  perPage: number;
  withOwnCertificate: number;
  withWarnings: number;
  globalHasCertificate: boolean;
}

// Resultado de POST /signature/certificate/preview: inspección sin guardar nada.
export interface CertificatePreview {
  certificateSubject: string | null;
  certificateRuc: string | null;
  certificateOrganization: string | null;
  certificateExpiresAt: string | null;
  rucMismatch: boolean;
  warnings: CertificateWarning[];
}
