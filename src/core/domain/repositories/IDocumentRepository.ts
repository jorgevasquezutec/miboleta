import { Document } from '../entities/Document';
import { DocumentType } from '../entities/DocumentType';
import { DocumentBatch, BatchUploadRequest, ZipPreviewResponse } from '../entities/DocumentBatch';

export interface DocumentFilters {
  page?: number;
  perPage?: number;
  docTypeId?: number;
  tenantId?: number;
  status?: string;
  period?: string;
  search?: string;
  dateFrom?: string;
  dateTo?: string;
  myDocuments?: boolean;
}

export interface PaginatedDocuments {
  data: Document[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}

export interface SignatureTermsResponse {
  accepted: boolean;
  acceptedAt: string | null;
}

export interface RequestCodeResponse {
  message: string;
  expiresIn: number;
  emailSentTo: string;
}

export interface SignDocumentResponse {
  message: string;
  signedAt: string;
  document: any;
}

export interface SignatureStatusResponse {
  documentId: number;
  requiresSignature: boolean;
  isSigned: boolean;
  signedAt: string | null;
  signature: any;
}

/**
 * Resultado de GET /documents/{id}/verify-signature. Cuando el documento no
 * tiene una firma criptográfica PAdES aplicada (p. ej. firmado por 2FA de
 * email, o aún sin firmar), `verifiable` es false y solo vienen `reason` y
 * `message`; el resto de campos vienen null/undefined.
 */
export interface VerifySignatureResponse {
  verifiable: boolean;
  reason?: string;
  message?: string;
  intact?: boolean | null;
  valid?: boolean | null;
  trusted?: boolean | null;
  coversWholeFile?: boolean | null;
  signerSubject?: string | null;
  signingTime?: string | null;
  tsaApplied?: boolean | null;
  tsaTime?: string | null;
}

export interface IDocumentRepository {
  // Document Types
  getDocumentTypes(): Promise<DocumentType[]>;
  getDocumentTypeById(id: number): Promise<DocumentType>;
  
  // Documents
  findAll(filters?: DocumentFilters): Promise<PaginatedDocuments>;
  findById(id: number): Promise<Document>;
  getOrphans(filters?: DocumentFilters): Promise<PaginatedDocuments>;
  assignOrphan(documentId: number, userId: number): Promise<Document>;
  delete(id: number): Promise<void>;
  getDownloadUrl(id: number): string;
  
  // Batches
  getBatches(filters?: { 
    status?: string; 
    typeId?: number; 
    page?: number; 
    perPage?: number; 
    dateFrom?: string; 
    dateTo?: string 
  }): Promise<{
    data: DocumentBatch[];
    meta: { currentPage: number; lastPage: number; perPage: number; total: number };
  }>;
  getBatchById(id: number): Promise<DocumentBatch & { 
    documentsSummary: { total: number; pending: number; signed: number; orphan: number } 
  }>;
  uploadBatch(data: BatchUploadRequest): Promise<{ batchId: number; status: string }>;
  previewZip(file: File): Promise<ZipPreviewResponse>;
  
  // Signature
  checkSignatureTerms(): Promise<SignatureTermsResponse>;
  acceptSignatureTerms(): Promise<{ message: string; acceptedAt: string }>;
  getSignatureStatus(documentId: number): Promise<SignatureStatusResponse>;
  requestSignatureCode(documentId: number): Promise<RequestCodeResponse>;
  signDocument(documentId: number, code: string): Promise<SignDocumentResponse>;

  // Firma digital criptográfica (PAdES) - verificación
  verifySignature(documentId: number): Promise<VerifySignatureResponse>;
}
