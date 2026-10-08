import {
  ISignatureSettingsRepository,
  PreviewCertificateRequest,
  TenantCertificateMutationResult,
  TenantCertificatesFilters,
  UploadCertificateRequest,
  UploadTenantCertificateRequest,
} from '@/core/domain/repositories/ISignatureSettingsRepository';
import {
  CertificatePreview,
  CertificateSource,
  CertificateWarning,
  SignatureSettings,
  TenantCertificateStatus,
  TenantCertificatesSummary,
} from '@/core/domain/entities/SignatureSettings';
import apiClient, { toApiError } from '@/infrastructure/http/apiClient';

interface SignatureSettingsResponseData {
  signature_enabled: boolean;
  has_certificate: boolean;
  certificate_subject: string | null;
  certificate_ruc: string | null;
  certificate_organization: string | null;
  certificate_expires_at: string | null;
  tsa_url: string | null;
  uploaded_at: string | null;
}

interface TenantCertificateResponseData {
  tenant_id: number;
  tenant_name: string;
  tenant_ruc: string | null;
  tenant_business_name: string | null;
  tenant_status: string;
  certificate_source: CertificateSource;
  has_own_certificate: boolean;
  certificate: {
    certificate_subject: string | null;
    certificate_ruc: string | null;
    certificate_organization: string | null;
    certificate_expires_at: string | null;
    tsa_url?: string | null;
    uploaded_at: string | null;
    uploaded_by: { id: number; name: string } | null;
  } | null;
  ruc_mismatch: boolean;
  warnings: CertificateWarning[];
}

interface CertificatePreviewResponseData {
  certificate_subject: string | null;
  certificate_ruc: string | null;
  certificate_organization: string | null;
  certificate_expires_at: string | null;
  ruc_mismatch: boolean;
  warnings: CertificateWarning[];
}

interface TenantCertificatesMeta {
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
  with_own_certificate: number;
  with_warnings: number;
  global_has_certificate: boolean;
}

/**
 * Repository - Configuración del certificado de firma digital de plataforma.
 * Endpoints SOLO root: GET/PUT /signature/settings, POST/DELETE /signature/certificate
 * (certificado global) y GET /signature/tenants, POST/DELETE
 * /signature/tenants/{id}/certificate (certificado por empresa).
 */
export class SignatureSettingsRepository implements ISignatureSettingsRepository {
  async getSettings(): Promise<SignatureSettings> {
    try {
      const response = await apiClient.get<{ data: SignatureSettingsResponseData }>('/signature/settings');
      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async uploadCertificate(data: UploadCertificateRequest): Promise<SignatureSettings> {
    const formData = new FormData();
    formData.append('certificate', data.certificate);
    formData.append('password', data.password);
    if (data.tsaUrl) {
      formData.append('tsa_url', data.tsaUrl);
    }

    try {
      const response = await apiClient.post<{ data: SignatureSettingsResponseData }>(
        '/signature/certificate',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );

      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async updateEnabled(enabled: boolean): Promise<SignatureSettings> {
    try {
      const response = await apiClient.put<{ data: SignatureSettingsResponseData }>('/signature/settings', {
        signature_enabled: enabled,
      });
      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async deleteCertificate(): Promise<SignatureSettings> {
    try {
      const response = await apiClient.delete<{ data: SignatureSettingsResponseData }>('/signature/certificate');
      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async listTenantCertificates(
    filters: TenantCertificatesFilters = {}
  ): Promise<{ items: TenantCertificateStatus[]; summary: TenantCertificatesSummary }> {
    try {
      const response = await apiClient.get<{
        data: TenantCertificateResponseData[];
        meta: TenantCertificatesMeta;
      }>('/signature/tenants', {
        params: {
          search: filters.search || undefined,
          only_warnings: filters.onlyWarnings ? 1 : undefined,
          page: filters.page || undefined,
          per_page: filters.perPage || undefined,
        },
      });
      const { data, meta } = response.data;
      return {
        items: data.map((item) => this.mapTenantCertificate(item)),
        summary: {
          total: meta.total,
          currentPage: meta.current_page,
          lastPage: meta.last_page,
          perPage: meta.per_page,
          withOwnCertificate: meta.with_own_certificate,
          withWarnings: meta.with_warnings,
          globalHasCertificate: meta.global_has_certificate,
        },
      };
    } catch (error) {
      throw toApiError(error);
    }
  }

  async getTenantCertificate(tenantId: number): Promise<TenantCertificateStatus> {
    try {
      const response = await apiClient.get<{ data: TenantCertificateResponseData }>(
        `/signature/tenants/${tenantId}/certificate`
      );
      return this.mapTenantCertificate(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async previewCertificate(data: PreviewCertificateRequest): Promise<CertificatePreview> {
    const formData = new FormData();
    formData.append('certificate', data.certificate);
    formData.append('password', data.password);
    if (data.ruc) {
      formData.append('ruc', data.ruc);
    }

    try {
      const response = await apiClient.post<{ data: CertificatePreviewResponseData }>(
        '/signature/certificate/preview',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      const d = response.data.data;
      return {
        certificateSubject: d.certificate_subject,
        certificateRuc: d.certificate_ruc,
        certificateOrganization: d.certificate_organization,
        certificateExpiresAt: d.certificate_expires_at,
        rucMismatch: d.ruc_mismatch,
        warnings: d.warnings,
      };
    } catch (error) {
      throw toApiError(error);
    }
  }

  async uploadTenantCertificate(
    tenantId: number,
    data: UploadTenantCertificateRequest
  ): Promise<TenantCertificateMutationResult> {
    const formData = new FormData();
    formData.append('certificate', data.certificate);
    formData.append('password', data.password);
    if (data.tsaUrl?.trim()) {
      formData.append('tsa_url', data.tsaUrl.trim());
    }

    try {
      const response = await apiClient.post<{ message: string; data: TenantCertificateResponseData }>(
        `/signature/tenants/${tenantId}/certificate`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      return { message: response.data.message, item: this.mapTenantCertificate(response.data.data) };
    } catch (error) {
      throw toApiError(error);
    }
  }

  async deleteTenantCertificate(tenantId: number): Promise<TenantCertificateMutationResult> {
    try {
      const response = await apiClient.delete<{ message: string; data: TenantCertificateResponseData }>(
        `/signature/tenants/${tenantId}/certificate`
      );
      return { message: response.data.message, item: this.mapTenantCertificate(response.data.data) };
    } catch (error) {
      throw toApiError(error);
    }
  }

  private mapTenantCertificate(data: TenantCertificateResponseData): TenantCertificateStatus {
    return {
      tenantId: data.tenant_id,
      tenantName: data.tenant_name,
      tenantRuc: data.tenant_ruc,
      tenantBusinessName: data.tenant_business_name,
      tenantStatus: data.tenant_status,
      certificateSource: data.certificate_source,
      hasOwnCertificate: data.has_own_certificate,
      certificate: data.certificate
        ? {
            certificateSubject: data.certificate.certificate_subject,
            certificateRuc: data.certificate.certificate_ruc,
            certificateOrganization: data.certificate.certificate_organization,
            certificateExpiresAt: data.certificate.certificate_expires_at,
            tsaUrl: data.certificate.tsa_url ?? null,
            uploadedAt: data.certificate.uploaded_at,
            uploadedBy: data.certificate.uploaded_by,
          }
        : null,
      rucMismatch: data.ruc_mismatch,
      warnings: data.warnings,
    };
  }

  private mapSettings(data: SignatureSettingsResponseData): SignatureSettings {
    return {
      signatureEnabled: data.signature_enabled,
      hasCertificate: data.has_certificate,
      certificateSubject: data.certificate_subject,
      certificateRuc: data.certificate_ruc,
      certificateOrganization: data.certificate_organization,
      certificateExpiresAt: data.certificate_expires_at,
      tsaUrl: data.tsa_url,
      uploadedAt: data.uploaded_at,
    };
  }
}

// Singleton instance
export const signatureSettingsRepository = new SignatureSettingsRepository();
