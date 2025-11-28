import { IUserRepository } from '@/core/domain/repositories';
import { User, CreateUserData, UpdateUserData } from '@/core/domain/entities';
import { mockApi } from '@/infrastructure/http/api';

/**
 * Implementación del repositorio de usuarios
 * Conecta con la API (mock o real)
 */
export class UserRepository implements IUserRepository {
  async findAll(): Promise<User[]> {
    const response = await mockApi.get<User[]>('/users');
    return response.data;
  }

  async findById(id: string): Promise<User | null> {
    try {
      const response = await mockApi.get<User>(`/users/${id}`);
      return response.data;
    } catch (error) {
      return null;
    }
  }

  async findByEmail(email: string): Promise<User | null> {
    try {
      const users = await this.findAll();
      return users.find(user => user.email === email) || null;
    } catch (error) {
      return null;
    }
  }

  async create(data: CreateUserData): Promise<User> {
    const response = await mockApi.post<User>('/users', data);
    return response.data;
  }

  async update(id: string, data: UpdateUserData): Promise<User> {
    const response = await mockApi.put<User>(`/users/${id}`, data);
    return response.data;
  }

  async delete(id: string): Promise<void> {
    await mockApi.delete(`/users/${id}`);
  }
}

// Singleton instance
export const userRepository = new UserRepository();
