import { IDocumentRepository } from '@/core/domain/repositories';

/**
 * Use Case: Eliminar un documento
 */
export class DeleteDocumentUseCase {
  constructor(private documentRepository: IDocumentRepository) {}

  async execute(id: string): Promise<void> {
    // Verificar que el documento existe
    const document = await this.documentRepository.findById(id);
    if (!document) {
      throw new Error('Documento no encontrado');
    }

    // TODO: Eliminar archivo físico del storage
    
    await this.documentRepository.delete(id);
  }
}
