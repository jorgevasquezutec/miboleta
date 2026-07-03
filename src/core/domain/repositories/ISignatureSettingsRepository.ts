import { SignatureSettings } from '../entities/SignatureSettings';

export interface UploadCertificateRequest {
  certificate: File;
  password: string;
  tsaUrl?: string;
}

export interface ISignatureSettingsRepository {
  getSettings(): Promise<SignatureSettings>;
  uploadCertificate(data: UploadCertificateRequest): Promise<SignatureSettings>;
  updateEnabled(enabled: boolean): Promise<SignatureSettings>;
  deleteCertificate(): Promise<SignatureSettings>;
}
