import { DocumentType } from './DocumentType';

// Estado de la firma PAdES de la empresa: null = no aplica o nunca se intentó;
// pending = en cola o en proceso; signed = el archivo coincide con lo deseado;
// failed = falló la última (re)firma (se sigue sirviendo la última firma válida).
export type DigitalSignatureStatus = 'pending' | 'signed' | 'failed';

// Filtro de listado por estado de firma digital ('none' = sin firma digital)
export type DigitalStatusFilter = DigitalSignatureStatus | 'none';

// Domain Entity - Document (aligned with backend).
// Dos firmas independientes:
//  - signature / signedAt / status='signed': conformidad del trabajador (2FA por correo).
//  - digitalSignature / digitallySignedAt / digitalSignatureStatus: firma PAdES de la empresa.
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
  // Conformidad del trabajador (código por correo). NO es criptográfica.
  signature: Email2FASignatureData | null;
  signedAt: string | null;
  // Firma digital PAdES de la empresa (columnas digital_*). Independiente de la
  // conformidad del trabajador: ambas conviven en el mismo documento.
  digitalSignature: PadesSignatureData | null;
  digitallySignedAt: string | null;
  digitalSignatureStatus: DigitalSignatureStatus | null;
  // Solo llega con valor a quien tiene documents.sign_digital
  digitalSignatureError?: string | null;
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
  user_name?: string;
  // Cómo se dibujó el nombre en el PDF: sidecar (PAdES), FPDI, o FPDI falló
  pdf_mark?: 'pades' | 'fpdi' | 'fpdi_failed';
  document_sha256?: string;
}

// Metadata guardada en Document.signature cuando el pipeline CRIPTOGRÁFICO
// (App\Services\DocumentSigningService, con el certificado de la empresa o,
// como fallback, el de la plataforma, vía el sidecar `signer`) firma el documento: sí produce una firma PAdES
// embebida y verificable en el PDF (ver GET /documents/{id}/verify-signature).
export interface SignerDetails {
  name: string | null;
  organization: string | null;
  ruc: string | null;
  title: string | null;
  country: string | null;
  locality: string | null;
  signed_at_local: string | null;
}

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
  // Sello visible en el pie y datos limpios del firmante. Ausentes en
  // documentos firmados antes de este cambio (usar signer_subject).
  stamp_applied?: boolean;
  signer_details?: SignerDetails | null;
  // Ausentes en documentos firmados antes de existir el certificado por empresa
  // (tratar como 'global'). No hay flag de RUC no coincidente a propósito.
  certificate_source?: 'tenant' | 'global';
  certificate_ruc?: string | null;
  certificate_organization?: string | null;
  // La firma de la empresa se regenera para incluir el nombre del trabajador.
  includes_conformity?: boolean;
  conformity_signed_at?: string | null;
  first_signed_at?: string | null;
  resign_count?: number;
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
  signed: 'Firmado por trabajador',
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
