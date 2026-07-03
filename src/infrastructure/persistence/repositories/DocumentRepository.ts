import {
  IDocumentRepository,
  DocumentFilters,
  PaginatedDocuments,
  SignatureTermsResponse,
  RequestCodeResponse,
  SignDocumentResponse,
  SignatureStatusResponse,
  VerifySignatureResponse
} from '@/core/domain/repositories/IDocumentRepository';
import { Document } from '@/core/domain/entities/Document';
import { DocumentType } from '@/core/domain/entities/DocumentType';
import { DocumentBatch, BatchUploadRequest, ZipPreviewResponse } from '@/core/domain/entities/DocumentBatch';
import apiClient from '@/infrastructure/http/apiClient';

/**
 * Document Repository - Connected to real API
 */
export class DocumentRepository implements IDocumentRepository {

  // ============ Document Types ============

  async getDocumentTypes(): Promise<DocumentType[]> {
    const response = await apiClient.get<{ data: DocumentType[] }>('/document-types');
    return response.data.data.map((type) => this.mapDocumentType(type));
  }

  async getDocumentTypeById(id: number): Promise<DocumentType> {
    const response = await apiClient.get<{ data: DocumentType }>(`/document-types/${id}`);
    return this.mapDocumentType(response.data.data);
  }

  // ============ Documents ============

  async findAll(filters?: DocumentFilters): Promise<PaginatedDocuments> {
    const params = new URLSearchParams();

    if (filters?.page) params.append('page', filters.page.toString());
    if (filters?.perPage) params.append('per_page', filters.perPage.toString());
    if (filters?.docTypeId) params.append('doc_type_id', filters.docTypeId.toString());
    if (filters?.tenantId) params.append('tenant_id', filters.tenantId.toString());
    if (filters?.status) params.append('status', filters.status);
    if (filters?.period) params.append('period', filters.period);
    if (filters?.search) params.append('search', filters.search);
    if (filters?.dateFrom) params.append('date_from', filters.dateFrom);
    if (filters?.dateTo) params.append('date_to', filters.dateTo);
    if (filters?.myDocuments) params.append('my_documents', 'true');

    const response = await apiClient.get<{ data: Document[]; meta: any }>(
      `/documents?${params.toString()}`
    );

    return {
      data: response.data.data.map((doc) => this.mapDocument(doc)),
      meta: {
        currentPage: response.data.meta.current_page,
        lastPage: response.data.meta.last_page,
        perPage: response.data.meta.per_page,
        total: response.data.meta.total,
      },
    };
  }

  async findById(id: number): Promise<Document> {
    const response = await apiClient.get<{ data: Document }>(`/documents/${id}`);
    return this.mapDocument(response.data.data);
  }

  async getOrphans(filters?: DocumentFilters): Promise<PaginatedDocuments> {
    const params = new URLSearchParams();

    if (filters?.page) params.append('page', filters.page.toString());
    if (filters?.perPage) params.append('per_page', filters.perPage.toString());

    const response = await apiClient.get<{ data: Document[]; meta: any }>(
      `/documents/orphans?${params.toString()}`
    );

    return {
      data: response.data.data.map((doc) => this.mapDocument(doc)),
      meta: {
        currentPage: response.data.meta.current_page,
        lastPage: response.data.meta.last_page,
        perPage: response.data.meta.per_page,
        total: response.data.meta.total,
      },
    };
  }

  async assignOrphan(documentId: number, userId: number): Promise<Document> {
    const response = await apiClient.post<{ data: Document }>(`/documents/${documentId}/assign`, {
      user_id: userId,
    });
    return this.mapDocument(response.data.data);
  }

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/documents/${id}`);
  }

  getDownloadUrl(id: number): string {
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
    return `${baseUrl}/documents/${id}/download`;
  }

  // ============ Batches ============

  async getBatches(filters?: { status?: string; typeId?: number; page?: number; perPage?: number; dateFrom?: string; dateTo?: string }): Promise<{
    data: DocumentBatch[];
    meta: { currentPage: number; lastPage: number; perPage: number; total: number };
  }> {
    const params = new URLSearchParams();

    if (filters?.page) params.append('page', filters.page.toString());
    if (filters?.perPage) params.append('per_page', filters.perPage.toString());
    if (filters?.status) params.append('status', filters.status);
    if (filters?.typeId) params.append('type_id', filters.typeId.toString());
    if (filters?.dateFrom) params.append('date_from', filters.dateFrom);
    if (filters?.dateTo) params.append('date_to', filters.dateTo);

    const response = await apiClient.get<{ data: DocumentBatch[]; meta: any }>(
      `/document-batches?${params.toString()}`
    );

    return {
      data: response.data.data.map((batch) => this.mapBatch(batch)),
      meta: response.data.meta,
    };
  }

  async getBatchById(id: number): Promise<DocumentBatch & { documentsSummary: { total: number; pending: number; signed: number; orphan: number } }> {
    const response = await apiClient.get<{ data: any }>(`/document-batches/${id}`);
    return {
      ...this.mapBatch(response.data.data),
      documentsSummary: response.data.data.documents_summary,
    };
  }

  async uploadBatch(data: BatchUploadRequest): Promise<{ batchId: number; status: string }> {
    const formData = new FormData();
    formData.append('file', data.file);
    formData.append('type_id', data.typeId.toString());
    formData.append('period', data.period);
    formData.append('notify_employees', data.notifyEmployees ? '1' : '0');
    formData.append('requires_signature', data.requiresSignature ? '1' : '0');
    if (data.tenantId) {
      formData.append('tenant_id', data.tenantId.toString());
    }

    const response = await apiClient.post<{ data: { batch_id: number; status: string } }>(
      '/document-batches/upload',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );

    return {
      batchId: response.data.data.batch_id,
      status: response.data.data.status,
    };
  }

  async previewZip(file: File): Promise<ZipPreviewResponse> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await apiClient.post<{ data: any }>(
      '/document-batches/preview',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );

    return {
      totalFiles: response.data.data.total_files,
      validPdfs: response.data.data.valid_pdfs,
      invalidNames: response.data.data.invalid_names,
      invalidFormats: response.data.data.invalid_formats,
      files: response.data.data.files,
    };
  }

  // ============ Signature ============

  async checkSignatureTerms(): Promise<SignatureTermsResponse> {
    const response = await apiClient.get<{ accepted: boolean; accepted_at: string | null }>('/signature/terms');
    return {
      accepted: response.data.accepted,
      acceptedAt: response.data.accepted_at,
    };
  }

  async acceptSignatureTerms(): Promise<{ message: string; acceptedAt: string }> {
    const response = await apiClient.post<{ message: string; accepted_at: string }>('/signature/terms/accept');
    return {
      message: response.data.message,
      acceptedAt: response.data.accepted_at,
    };
  }

  async getSignatureStatus(documentId: number): Promise<SignatureStatusResponse> {
    const response = await apiClient.get<any>(`/documents/${documentId}/signature-status`);
    return {
      documentId: response.data.document_id,
      requiresSignature: response.data.requires_signature,
      isSigned: response.data.is_signed,
      signedAt: response.data.signed_at,
      signature: response.data.signature,
    };
  }

  async requestSignatureCode(documentId: number): Promise<RequestCodeResponse> {
    const response = await apiClient.post<{ message: string; expires_in: number; email_sent_to: string }>(
      `/documents/${documentId}/request-code`
    );
    return {
      message: response.data.message,
      expiresIn: response.data.expires_in,
      emailSentTo: response.data.email_sent_to,
    };
  }

  async signDocument(documentId: number, code: string): Promise<SignDocumentResponse> {
    const response = await apiClient.post<any>(`/documents/${documentId}/sign`, { code });
    return {
      message: response.data.message,
      signedAt: response.data.signed_at,
      document: response.data.document,
    };
  }

  async verifySignature(documentId: number): Promise<VerifySignatureResponse> {
    const response = await apiClient.get<{ data: any }>(`/documents/${documentId}/verify-signature`);
    const data = response.data.data;

    return {
      verifiable: data.verifiable,
      reason: data.reason,
      message: data.message,
      intact: data.intact,
      valid: data.valid,
      trusted: data.trusted,
      coversWholeFile: data.covers_whole_file,
      signerSubject: data.signer_subject,
      signingTime: data.signing_time,
      tsaApplied: data.tsa_applied,
      tsaTime: data.tsa_time,
    };
  }

  // ============ Mappers ============

  private mapDocumentType(data: any): DocumentType {
    return {
      id: data.id,
      name: data.name,
      displayName: data.display_name,
      description: data.description,
      requiresSignature: data.requires_signature,
      isActive: data.is_active,
      sortOrder: data.sort_order,
    };
  }

  private mapDocument(data: any): Document {
    return {
      id: data.id,
      tenantId: data.tenant_id,
      userId: data.user_id,
      batchId: data.batch_id,
      docTypeId: data.doc_type_id,
      employeeDocumentNumber: data.employee_document_number,
      period: data.period,
      filePath: data.file_path,
      fileSize: data.file_size,
      originalName: data.original_name,
      status: data.status,
      uploadedBy: data.uploaded_by,
      requiresSignature: data.requires_signature,
      signature: data.signature,
      signedAt: data.signed_at,
      expiresAt: data.expires_at,
      notified: data.notified,
      notifiedAt: data.notified_at,
      version: data.version,
      createdAt: data.created_at,
      updatedAt: data.updated_at,
      documentType: data.document_type ? this.mapDocumentType(data.document_type) : undefined,
      user: data.user ? {
        id: data.user.id,
        name: data.user.name,
        lastName: data.user.last_name,
        documentText: data.user.document_text,
        email: data.user.email,
      } : undefined,
      batch: data.batch ? {
        id: data.batch.id,
        period: data.batch.period,
        originalFilename: data.batch.original_filename,
      } : undefined,
    };
  }

  private mapBatch(data: any): DocumentBatch {
    return {
      id: data.id,
      tenantId: data.tenant_id,
      uploadedBy: data.uploaded_by,
      typeId: data.type_id,
      period: data.period,
      originalFilename: data.original_filename,
      totalFiles: data.total_files,
      processedFiles: data.processed_files,
      successCount: data.success_count,
      replacedCount: data.replaced_count,
      orphanCount: data.orphan_count,
      errorCount: data.error_count,
      errors: data.errors,
      notifyEmployees: data.notify_employees,
      notificationsSent: data.notifications_sent,
      requiresSignature: data.requires_signature,
      status: data.status,
      progressPercentage: data.progress_percentage,
      startedAt: data.started_at,
      completedAt: data.completed_at,
      createdAt: data.created_at,
      documentType: data.document_type ? {
        id: data.document_type.id,
        name: data.document_type.name,
        displayName: data.document_type.display_name,
      } : undefined,
      uploader: data.uploader ? {
        id: data.uploader.id,
        name: data.uploader.name,
      } : undefined,
    };
  }
}

// Singleton instance
export const documentRepository = new DocumentRepository();
