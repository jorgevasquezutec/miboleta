import { IUserRepository } from '@/core/domain/repositories';

/**
 * Use Case: Eliminar un usuario
 */
export class DeleteUserUseCase {
  constructor(private userRepository: IUserRepository) {}

  async execute(id: string): Promise<void> {
    // Verificar que el usuario existe
    const user = await this.userRepository.findById(id);
    if (!user) {
      throw new Error('Usuario no encontrado');
    }

    // TODO: Agregar validación para no eliminar el último admin
    
    await this.userRepository.delete(id);
  }
}
