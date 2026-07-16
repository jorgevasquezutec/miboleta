import { DocumentType } from './DocumentType';

// Domain Entity - Document (aligned with backend)
export interface Document {
  id: number;
  tenantId: number;
  userId: number | null;
  batchId: number | null;
  docTypeId: number;
  employeeDocumentNumber: string;
  period: string; // YYYY-MM
  filePath: string;
  fileSize: number;
  originalName: string;
  status: 'pending' | 'signed' | 'active' | 'orphan' | 'expired';
  uploadedBy: number;
  requiresSignature: boolean;
  signature: SignatureData | null;
  signedAt: string | null;
  expiresAt: string | null;
  notified: boolean;
  notifiedAt: string | null;
  version: number;
  createdAt: string;
  updatedAt: string;

  // Relations (when loaded)
  documentType?: DocumentType;
  user?: DocumentUser;
  batch?: DocumentBatchSummary;
  uploader?: DocumentUser;
}

// Metadata guardada en Document.signature cuando el flujo de 2FA de email
// (App\Services\SignatureService) firma el documento: el propio empleado
// confirma su identidad con un código enviado a su correo. NO produce una
// firma criptográfica embebida en el PDF (solo agrega un watermark visual).
export interface Email2FASignatureData {
  ip: string;
  user_agent: string;
  timestamp: string;
  user_id: number;
  verification_method: 'email_2fa';
  code_id: number;
}

// Metadata guardada en Document.signature cuando el pipeline CRIPTOGRÁFICO
// (App\Services\DocumentSigningService, certificado único de plataforma vía
// el sidecar `signer`) firma el documento: sí produce una firma PAdES
// embebida y verificable en el PDF (ver GET /documents/{id}/verify-signature).
export interface PadesSignatureData {
  method: 'pades_pyhanko';
  signer_subject: string | null;
  signing_time: string | null;
  tsa_applied: boolean;
  tsa_time: string | null;
  digest_algo: string | null;
  sha256: string | null;
  covers_whole_file: boolean | null;
  intact: boolean | null;
  valid: boolean | null;
  trusted: boolean | null;
}

// Unión discriminada: Email2FASignatureData no trae 'method' (solo
// 'verification_method'), así que se distinguen por la ausencia/presencia
// de esa clave. Ver helpers isPadesSignature/isEmail2FASignature más abajo.
export type SignatureData = Email2FASignatureData | PadesSignatureData;

export function isPadesSignature(signature: SignatureData | null | undefined): signature is PadesSignatureData {
  return !!signature && (signature as PadesSignatureData).method === 'pades_pyhanko';
}

export function isEmail2FASignature(signature: SignatureData | null | undefined): signature is Email2FASignatureData {
  return !!signature && (signature as Email2FASignatureData).verification_method === 'email_2fa';
}

export interface DocumentUser {
  id: number;
  name: string;
  lastName?: string;
  documentText?: string;
  email?: string;
}

export interface DocumentBatchSummary {
  id: number;
  period: string;
  originalFilename: string;
}

// Status helpers
export const documentStatusLabels: Record<Document['status'], string> = {
  pending: 'Pendiente Firma',
  signed: 'Firmado',
  active: 'Disponible',
  orphan: 'Huérfano',
  expired: 'Expirado',
};

export const documentStatusColors: Record<Document['status'], string> = {
  pending: 'warning',
  signed: 'success',
  active: 'info',
  orphan: 'secondary',
  expired: 'destructive',
};
