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
  status: 'pending' | 'signed' | 'orphan' | 'expired';
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

export interface SignatureData {
  ip: string;
  user_agent: string;
  timestamp: string;
  user_id: number;
  verification_method: 'email_2fa';
  code_id: number;
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
  pending: 'Pendiente',
  signed: 'Firmado',
  orphan: 'Huérfano',
  expired: 'Expirado',
};

export const documentStatusColors: Record<Document['status'], string> = {
  pending: 'warning',
  signed: 'success',
  orphan: 'secondary',
  expired: 'destructive',
};
