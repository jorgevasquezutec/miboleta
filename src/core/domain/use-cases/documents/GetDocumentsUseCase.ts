import { IDocumentRepository, DocumentFilters, PaginatedDocuments } from '@/core/domain/repositories';

/**
 * Use Case: Obtener documentos con filtros y paginación
 */
export class GetDocumentsUseCase {
  constructor(private documentRepository: IDocumentRepository) {}

  async execute(filters?: DocumentFilters): Promise<PaginatedDocuments> {
    return await this.documentRepository.findAll(filters);
  }
}
