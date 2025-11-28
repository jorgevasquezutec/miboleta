// Repository Interface - Document Repository
import { Document, CreateDocumentData, UpdateDocumentData } from '../entities/Document';

export interface DocumentFilters {
  tenantId?: string;
  category?: Document['category'];
  status?: Document['status'];
  searchTerm?: string;
  page?: number;
  pageSize?: number;
}

export interface PaginatedDocuments {
  data: Document[];
  total: number;
  page: number;
  pageSize: number;
  totalPages: number;
}

export interface IDocumentRepository {
  findAll(filters?: DocumentFilters): Promise<PaginatedDocuments>;
  findById(id: string): Promise<Document | null>;
  create(data: CreateDocumentData): Promise<Document>;
  update(id: string, data: UpdateDocumentData): Promise<Document>;
  delete(id: string): Promise<void>;
}
