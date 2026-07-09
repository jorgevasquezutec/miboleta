import { Tenant, CreateTenantData, UpdateTenantData, TenantSmtpTestResult } from '../entities';
import { PaginatedResponse } from '@/infrastructure/persistence/repositories/types';

export interface GetTenantsParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}

export interface ITenantRepository {
  getAll(params?: GetTenantsParams): Promise<PaginatedResponse<Tenant>>;
  getById(id: string): Promise<Tenant>;
  create(data: CreateTenantData): Promise<Tenant>;
  update(id: string, data: UpdateTenantData): Promise<Tenant>;
  delete(id: string): Promise<void>;
  getUsers(tenantId: string): Promise<any[]>;
  addUser(tenantId: string, userId: string, isPrimary?: boolean): Promise<void>;
  removeUser(tenantId: string, userId: string): Promise<void>;
  /** Prueba la conexión SMTP guardada de la empresa (ítem 24-menor, solo root). */
  testSmtp(tenantId: string): Promise<TenantSmtpTestResult>;
}
