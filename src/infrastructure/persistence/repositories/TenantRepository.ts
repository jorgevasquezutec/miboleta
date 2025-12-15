import { Tenant, CreateTenantData, UpdateTenantData } from '@/core/domain/entities';
import apiClient, { getErrorMessage } from '@/infrastructure/http/apiClient';
import { PaginatedResponse } from './types';

export interface GetTenantsParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

/**
 * Implementación del repositorio de tenants
 * Conecta con la API de Laravel para gestión de organizaciones
 */
export class TenantRepository {
  /**
   * Obtener lista de tenants con paginación
   */
  async getAll(params?: GetTenantsParams): Promise<PaginatedResponse<Tenant>> {
    try {
      const response = await apiClient.get<PaginatedResponse<Tenant>>('/tenants', { params });
      return response.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Obtener tenant por ID
   */
  async getById(id: string): Promise<Tenant> {
    try {
      const response = await apiClient.get<{ data: Tenant }>(`/tenants/${id}`);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Crear nuevo tenant (solo root)
   */
  async create(data: CreateTenantData): Promise<Tenant> {
    try {
      const response = await apiClient.post<{ data: Tenant; message: string }>('/tenants', data);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Actualizar tenant existente
   */
  async update(id: string, data: UpdateTenantData): Promise<Tenant> {
    try {
      const response = await apiClient.put<{ data: Tenant; message: string }>(`/tenants/${id}`, data);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Eliminar tenant (soft delete, solo root)
   */
  async delete(id: string): Promise<void> {
    try {
      await apiClient.delete(`/tenants/${id}`);
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Obtener usuarios de un tenant
   */
  async getUsers(tenantId: string): Promise<any[]> {
    try {
      const response = await apiClient.get<{ data: any[] }>(`/tenants/${tenantId}/users`);
      return response.data.data;
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Agregar usuario a un tenant
   */
  async addUser(tenantId: string, userId: string, isPrimary: boolean = false): Promise<void> {
    try {
      await apiClient.post(`/tenants/${tenantId}/users`, {
        user_id: userId,
        is_primary: isPrimary,
      });
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }

  /**
   * Remover usuario de un tenant
   */
  async removeUser(tenantId: string, userId: string): Promise<void> {
    try {
      await apiClient.delete(`/tenants/${tenantId}/users/${userId}`);
    } catch (error) {
      throw new Error(getErrorMessage(error));
    }
  }
}

// Exportar instancia singleton
export const tenantRepository = new TenantRepository();

