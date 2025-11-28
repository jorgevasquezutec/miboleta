import { IDocumentRepository, DocumentFilters, PaginatedDocuments } from '@/core/domain/repositories';
import { Document, CreateDocumentData, UpdateDocumentData } from '@/core/domain/entities';
import { mockApi } from '@/infrastructure/http/api';

/**
 * Implementación del repositorio de documentos
 */
export class DocumentRepository implements IDocumentRepository {
  async findAll(filters?: DocumentFilters): Promise<PaginatedDocuments> {
    const params = new URLSearchParams();
    
    if (filters?.page) params.append('page', filters.page.toString());
    if (filters?.pageSize) params.append('pageSize', filters.pageSize.toString());
    if (filters?.category) params.append('category', filters.category);
    if (filters?.status) params.append('status', filters.status);
    if (filters?.tenantId) params.append('tenantId', filters.tenantId);
    if (filters?.searchTerm) params.append('search', filters.searchTerm);

    const response = await mockApi.get<PaginatedDocuments>(
      `/documents?${params.toString()}`
    );
    
    return response.data;
  }

  async findById(id: string): Promise<Document | null> {
    try {
      const response = await mockApi.get<Document>(`/documents/${id}`);
      return response.data;
    } catch (error) {
      return null;
    }
  }

  async create(data: CreateDocumentData): Promise<Document> {
    const response = await mockApi.post<Document>('/documents', data);
    return response.data;
  }

  async update(id: string, data: UpdateDocumentData): Promise<Document> {
    const response = await mockApi.put<Document>(`/documents/${id}`, data);
    return response.data;
  }

  async delete(id: string): Promise<void> {
    await mockApi.delete(`/documents/${id}`);
  }
}

// Singleton instance
export const documentRepository = new DocumentRepository();
