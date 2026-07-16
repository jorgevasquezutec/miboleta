import {
  ISignatureSettingsRepository,
  UploadCertificateRequest,
} from '@/core/domain/repositories/ISignatureSettingsRepository';
import { SignatureSettings } from '@/core/domain/entities/SignatureSettings';
import apiClient from '@/infrastructure/http/apiClient';

interface SignatureSettingsResponseData {
  signature_enabled: boolean;
  has_certificate: boolean;
  certificate_subject: string | null;
  tsa_url: string | null;
  uploaded_at: string | null;
}

/**
 * Repository - Configuración del certificado de firma digital de plataforma.
 * Endpoints SOLO root: GET/PUT /signature/settings, POST/DELETE /signature/certificate.
 */
export class SignatureSettingsRepository implements ISignatureSettingsRepository {
  async getSettings(): Promise<SignatureSettings> {
    const response = await apiClient.get<{ data: SignatureSettingsResponseData }>('/signature/settings');
    return this.mapSettings(response.data.data);
  }

  async uploadCertificate(data: UploadCertificateRequest): Promise<SignatureSettings> {
    const formData = new FormData();
    formData.append('certificate', data.certificate);
    formData.append('password', data.password);
    if (data.tsaUrl) {
      formData.append('tsa_url', data.tsaUrl);
    }

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
  }

  async updateEnabled(enabled: boolean): Promise<SignatureSettings> {
    const response = await apiClient.put<{ data: SignatureSettingsResponseData }>('/signature/settings', {
      signature_enabled: enabled,
    });
    return this.mapSettings(response.data.data);
  }

  async deleteCertificate(): Promise<SignatureSettings> {
    const response = await apiClient.delete<{ data: SignatureSettingsResponseData }>('/signature/certificate');
    return this.mapSettings(response.data.data);
  }

  private mapSettings(data: SignatureSettingsResponseData): SignatureSettings {
    return {
      signatureEnabled: data.signature_enabled,
      hasCertificate: data.has_certificate,
      certificateSubject: data.certificate_subject,
      tsaUrl: data.tsa_url,
      uploadedAt: data.uploaded_at,
    };
  }
}

// Singleton instance
export const signatureSettingsRepository = new SignatureSettingsRepository();
