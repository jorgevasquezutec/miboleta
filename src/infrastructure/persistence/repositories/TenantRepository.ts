import { ITenantRepository } from '@/core/domain/repositories';
import { Tenant, CreateTenantData, UpdateTenantData } from '@/core/domain/entities';
import { mockApi } from '@/infrastructure/http/api';

/**
 * Implementación del repositorio de tenants (empresas)
 */
export class TenantRepository implements ITenantRepository {
  async findAll(): Promise<Tenant[]> {
    const response = await mockApi.get<Tenant[]>('/tenants');
    return response.data;
  }

  async findById(id: string): Promise<Tenant | null> {
    try {
      const response = await mockApi.get<Tenant>(`/tenants/${id}`);
      return response.data;
    } catch (error) {
      return null;
    }
  }

  async create(data: CreateTenantData): Promise<Tenant> {
    const response = await mockApi.post<Tenant>('/tenants', data);
    return response.data;
  }

  async update(id: string, data: UpdateTenantData): Promise<Tenant> {
    const response = await mockApi.put<Tenant>(`/tenants/${id}`, data);
    return response.data;
  }

  async delete(id: string): Promise<void> {
    await mockApi.delete(`/tenants/${id}`);
  }
}

// Singleton instance
export const tenantRepository = new TenantRepository();
