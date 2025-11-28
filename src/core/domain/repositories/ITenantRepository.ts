// Repository Interface - Tenant Repository
import { Tenant, CreateTenantData, UpdateTenantData } from '../entities/Tenant';

export interface ITenantRepository {
  findAll(): Promise<Tenant[]>;
  findById(id: string): Promise<Tenant | null>;
  create(data: CreateTenantData): Promise<Tenant>;
  update(id: string, data: UpdateTenantData): Promise<Tenant>;
  delete(id: string): Promise<void>;
}
