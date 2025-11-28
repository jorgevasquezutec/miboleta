// Domain Entity - Tenant
export interface Tenant {
  id: string;
  name: string;
  ruc: string;
  address?: string;
  phone?: string;
  email?: string;
  logo?: string;
  status: 'active' | 'inactive' | 'suspended';
  primaryColor?: string;
  secondaryColor?: string;
  subscriptionPlan?: 'basic' | 'premium' | 'enterprise';
  maxUsers?: number;
  maxStorage?: number;
  createdAt: Date;
  updatedAt: Date;
}

export type CreateTenantData = Omit<Tenant, 'id' | 'createdAt' | 'updatedAt'>;
export type UpdateTenantData = Partial<Omit<Tenant, 'id' | 'createdAt' | 'updatedAt'>>;
