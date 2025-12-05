// Domain Entity - User
export interface User {
  id: string;
  name: string;
  last_name?: string;
  full_name?: string;
  email: string;
  document_type?: string;
  document_text?: string;
  phone?: string;
  role: 'root' | 'admin' | 'client';
  roles?: string[]; // Array de roles del usuario
  status: 'active' | 'inactive' | 'suspended' | 'pending';
  
  // Multi-tenancy
  tenants?: TenantAssociation[];
  primary_tenant?: TenantBasic | null;

  // Supervisor
  immediate_supervisor?: SupervisorBasic | null;
  immediate_supervisor_id?: string | null;

  // Metadata
  avatar?: string;
  createdAt?: Date;
  updatedAt?: Date;
  created_at?: string;
  updated_at?: string;
}

// Tenant asociado al usuario
export interface TenantAssociation {
  id: string;
  name: string;
  ruc: string;
  is_primary: boolean;
}

// Información básica de tenant
export interface TenantBasic {
  id: string;
  name: string;
  ruc: string;
}

// Información básica de supervisor
export interface SupervisorBasic {
  id: string;
  name: string;
  full_name?: string;
  email?: string;
}

export type CreateUserData = Omit<User, 'id' | 'createdAt' | 'updatedAt' | 'created_at' | 'updated_at'>;
export type UpdateUserData = Partial<Omit<User, 'id' | 'createdAt' | 'updatedAt' | 'created_at' | 'updated_at'>>;
