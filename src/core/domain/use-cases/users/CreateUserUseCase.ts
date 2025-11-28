import { IUserRepository } from '@/core/domain/repositories';
import { User, CreateUserData } from '@/core/domain/entities';
import { isValidEmail } from '@/shared/utils';

/**
 * Use Case: Crear un nuevo usuario
 */
export class CreateUserUseCase {
  constructor(private userRepository: IUserRepository) {}

  async execute(data: CreateUserData): Promise<User> {
    // Validaciones
    if (!data.name || data.name.trim().length < 2) {
      throw new Error('El nombre debe tener al menos 2 caracteres');
    }

    if (!isValidEmail(data.email)) {
      throw new Error('Email inválido');
    }

    // Verificar si el email ya existe
    const existingUser = await this.userRepository.findByEmail(data.email);
    if (existingUser) {
      throw new Error('El email ya está registrado');
    }

    // Crear usuario
    return await this.userRepository.create(data);
  }
}
