// Domain Entity - User
export interface User {
  id: string;
  name: string;
  email: string;
  role: 'platform_admin' | 'tenant_admin' | 'employee';
  tenantId: string | null;
  status: 'active' | 'inactive' | 'suspended';
  department?: string;
  avatar?: string;
  createdAt: Date;
  updatedAt: Date;
}

export type CreateUserData = Omit<User, 'id' | 'createdAt' | 'updatedAt'>;
export type UpdateUserData = Partial<Omit<User, 'id' | 'createdAt' | 'updatedAt'>>;
