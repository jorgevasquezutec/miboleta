import { IUserRepository } from '@/core/domain/repositories';
import { User } from '@/core/domain/entities';

/**
 * Use Case: Obtener todos los usuarios
 */
export class GetUsersUseCase {
  constructor(private userRepository: IUserRepository) {}

  async execute(): Promise<User[]> {
    return await this.userRepository.findAll();
  }
}
