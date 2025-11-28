import { IUserRepository } from '@/core/domain/repositories';
import { User, UpdateUserData } from '@/core/domain/entities';
import { isValidEmail } from '@/shared/utils';

/**
 * Use Case: Actualizar un usuario existente
 */
export class UpdateUserUseCase {
  constructor(private userRepository: IUserRepository) {}

  async execute(id: string, data: UpdateUserData): Promise<User> {
    // Verificar que el usuario existe
    const existingUser = await this.userRepository.findById(id);
    if (!existingUser) {
      throw new Error('Usuario no encontrado');
    }

    // Validaciones
    if (data.name && data.name.trim().length < 2) {
      throw new Error('El nombre debe tener al menos 2 caracteres');
    }

    if (data.email && !isValidEmail(data.email)) {
      throw new Error('Email inválido');
    }

    // Si cambia el email, verificar que no esté en uso
    if (data.email && data.email !== existingUser.email) {
      const emailInUse = await this.userRepository.findByEmail(data.email);
      if (emailInUse) {
        throw new Error('El email ya está en uso');
      }
    }

    // Actualizar usuario
    return await this.userRepository.update(id, data);
  }
}
