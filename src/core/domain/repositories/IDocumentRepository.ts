// Repository Interface - Document Repository (aligned with backend)
import { Document } from '../entities/Document';
import { DocumentType } from '../entities/DocumentType';
import { DocumentBatch, BatchUploadRequest, ZipPreviewResponse } from '../entities/DocumentBatch';

// Filters for document listing
export interface DocumentFilters {
  tenantId?: number;
  docTypeId?: number;
  status?: Document['status'];
  period?: string;
  search?: string;
  dateFrom?: string;
  dateTo?: string;
  page?: number;
  perPage?: number;
  myDocuments?: boolean; // Force filter to show only logged user's documents
}

// Paginated response
export interface PaginatedDocuments {
  data: Document[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
}

// Signature request/response
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
  document: {
    id: number;
    type: string;
    period: string;
    status: Document['status'];
  };
}

export interface SignatureStatusResponse {
  documentId: number;
  requiresSignature: boolean;
  isSigned: boolean;
  signedAt: string | null;
  signature: {
    timestamp: string;
    verificationMethod: string;
  } | null;
}

// Repository interface
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
  getBatches(filters?: { status?: string; typeId?: number; page?: number; perPage?: number }): Promise<{
    data: DocumentBatch[];
    meta: { currentPage: number; lastPage: number; perPage: number; total: number };
  }>;
  getBatchById(id: number): Promise<DocumentBatch & { documentsSummary: { total: number; pending: number; signed: number; orphan: number } }>;
  uploadBatch(data: BatchUploadRequest): Promise<{ batchId: number; status: string }>;
  previewZip(file: File): Promise<ZipPreviewResponse>;

  // Signature
  checkSignatureTerms(): Promise<SignatureTermsResponse>;
  acceptSignatureTerms(): Promise<{ message: string; acceptedAt: string }>;
  getSignatureStatus(documentId: number): Promise<SignatureStatusResponse>;
  requestSignatureCode(documentId: number): Promise<RequestCodeResponse>;
  signDocument(documentId: number, code: string): Promise<SignDocumentResponse>;
}
