import { IUserRepository, LoginResponse, GetUsersParams } from '@/core/domain/repositories/IUserRepository';
import { User, CreateUserData, UpdateUserData } from '@/core/domain/entities';
import apiClient, { getErrorMessage } from '@/infrastructure/http/apiClient';
import { PaginatedResponse } from './types';

/**
 * Implementación del repositorio de usuarios
 * Conecta con la API real de Laravel
 * Los tokens se manejan automáticamente vía cookies HttpOnly
 */
export class UserRepository implements IUserRepository {
  /**
   * Login - Autenticar usuario
   * Las cookies con access_token y refresh_token se establecen automáticamente
   * @param login DNI o correo electrónico (el backend acepta ambos en el campo `login`)
   */
  async login(login: string, password: string): Promise<LoginResponse> {
    try {
      const response = await apiClient.post<LoginResponse>('/login', {
        login,
        password,
      });
      return response.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Logout - Cerrar sesión
   * Invalida los tokens en el backend y limpia las cookies
   */
  async logout(): Promise<void> {
    try {
      await apiClient.post('/logout');
    } catch (error) {
      console.error('Logout error:', error);
      // No lanzar error en logout para permitir limpieza local
    }
  }

  /**
   * Me - Obtener usuario actual
   * El access_token se envía automáticamente desde la cookie
   */
  async me(): Promise<User> {
    try {
      const response = await apiClient.get<User>('/me');
      return response.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  async findAll(): Promise<User[]> {
    try {
      const response = await apiClient.get<User[]>('/users');
      return response.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Get users with pagination
   * Supports search, filtering by status and tenant
   */
  async getUsers(params?: GetUsersParams): Promise<PaginatedResponse<User>> {
    try {
      const response = await apiClient.get<PaginatedResponse<User>>('/users', {
        params: {
          page: params?.page,
          per_page: params?.per_page,
          search: params?.search,
          status: params?.status,
          tenant_id: params?.tenant_id,
        }
      });
      return response.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  async findById(id: string): Promise<User | null> {
    try {
      const response = await apiClient.get<{ data: User }>(`/users/${id}`);
      return response.data.data;
    } catch (error) {
      console.error('Find user by id error:', error);
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
    try {
      const response = await apiClient.post<{ data: User }>('/users', data);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  async update(id: string, data: UpdateUserData): Promise<User> {
    try {
      const response = await apiClient.put<{ data: User }>(`/users/${id}`, data);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  async delete(id: string): Promise<void> {
    try {
      await apiClient.delete(`/users/${id}`);
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }
}

// Singleton instance
export const userRepository = new UserRepository();
