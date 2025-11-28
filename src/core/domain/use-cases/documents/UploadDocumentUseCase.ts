import { IDocumentRepository } from '@/core/domain/repositories';
import { Document, CreateDocumentData } from '@/core/domain/entities';

/**
 * Use Case: Subir un nuevo documento
 */
export class UploadDocumentUseCase {
  constructor(private documentRepository: IDocumentRepository) {}

  async execute(data: CreateDocumentData): Promise<Document> {
    // Validaciones
    if (!data.title || data.title.trim().length < 3) {
      throw new Error('El título debe tener al menos 3 caracteres');
    }

    if (!data.category) {
      throw new Error('La categoría es requerida');
    }

    if (!data.fileUrl) {
      throw new Error('El archivo es requerido');
    }

    // Crear documento
    return await this.documentRepository.create(data);
  }
}
