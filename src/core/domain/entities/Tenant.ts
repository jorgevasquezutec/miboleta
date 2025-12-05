// Domain Entity - Tenant
export interface Tenant {
  id: string;
  name: string;
  ruc: string;
  business_name?: string;
  address?: string;
  phone?: string;
  logo_path?: string;
  primaryColor?: string;
  status: 'active' | 'inactive' | 'suspended';
  createdAt?: Date;
  updatedAt?: Date;
  created_at?: string;
  updated_at?: string;
}

export type CreateTenantData = Omit<Tenant, 'id' | 'createdAt' | 'updatedAt' | 'created_at' | 'updated_at'>;
export type UpdateTenantData = Partial<Omit<Tenant, 'id' | 'createdAt' | 'updatedAt' | 'created_at' | 'updated_at'>>;
