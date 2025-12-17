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

  // Password management
  must_change_password?: boolean;

  // Multi-tenancy
  tenants?: TenantAssociation[];
  primary_tenant?: TenantBasic | null;

  //Supervisor (DEPRECATED - usar tenants[].supervisor_id)
  /** @deprecated Use tenants[].supervisor_id instead */
  immediate_supervisor?: SupervisorBasic | null;
  /** @deprecated Use tenants[].supervisor_id instead */
  immediate_supervisor_id?: string | null;

  // Metadata
  avatar?: string;
  avatar_url?: string;
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
  logo_url?: string;
  is_primary: boolean;
  supervisor_id?: string | null;
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
