import { IRoleRepository } from '@/core/domain/repositories/IRoleRepository';
import { Role } from '@/core/domain/entities';
import apiClient, { toApiError } from '@/infrastructure/http/apiClient';

/**
 * Catálogo de roles del sistema (incluye 'root').
 * Usado por UserFormPage para construir el selector de roles por empresa
 * (tenants_config.*.role_ids) — filtrando 'root' del listado, ya que ese rol
 * es global y no se asigna dentro de una empresa.
 */
export class RoleRepository implements IRoleRepository {
  async getAll(): Promise<Role[]> {
    try {
      const response = await apiClient.get<{ data: Role[] }>('/roles');
      return response.data.data;
    } catch (error) {
      throw toApiError(error);
    }
  }
}

// Singleton instance
export const roleRepository = new RoleRepository();
