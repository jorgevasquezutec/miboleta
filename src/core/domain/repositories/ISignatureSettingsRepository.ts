import {
  CertificatePreview,
  SignatureSettings,
  TenantCertificateStatus,
  TenantCertificatesSummary,
} from '../entities/SignatureSettings';

export interface UploadCertificateRequest {
  certificate: File;
  password: string;
  tsaUrl?: string;
}

export interface UploadTenantCertificateRequest {
  certificate: File;
  password: string;
  /** TSA propia de la empresa (opcional); vacío = usa la TSA global */
  tsaUrl?: string;
}

export interface PreviewCertificateRequest {
  certificate: File;
  password: string;
  /** RUC de la empresa contra el que se compara (opcional) */
  ruc?: string;
}

export interface TenantCertificatesFilters {
  search?: string;
  onlyWarnings?: boolean;
  page?: number;
  perPage?: number;
}

export interface TenantCertificateMutationResult {
  message: string;
  item: TenantCertificateStatus;
}

export interface ISignatureSettingsRepository {
  getSettings(): Promise<SignatureSettings>;
  uploadCertificate(data: UploadCertificateRequest): Promise<SignatureSettings>;
  updateEnabled(enabled: boolean): Promise<SignatureSettings>;
  deleteCertificate(): Promise<SignatureSettings>;
  listTenantCertificates(
    filters?: TenantCertificatesFilters
  ): Promise<{ items: TenantCertificateStatus[]; summary: TenantCertificatesSummary }>;
  getTenantCertificate(tenantId: number): Promise<TenantCertificateStatus>;
  previewCertificate(data: PreviewCertificateRequest): Promise<CertificatePreview>;
  uploadTenantCertificate(
    tenantId: number,
    data: UploadTenantCertificateRequest
  ): Promise<TenantCertificateMutationResult>;
  deleteTenantCertificate(tenantId: number): Promise<TenantCertificateMutationResult>;
}
