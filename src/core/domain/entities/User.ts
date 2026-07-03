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
  /** Fecha de nacimiento (YYYY-MM-DD), a nivel usuario (no por empresa). */
  birth_date?: string | null;
  /**
   * Rol "global" de respaldo (ver AuthService::transformAuthUser /
   * User::getCurrentRole en backend). Para root sigue siendo la fuente de
   * verdad ('root' es global). Para el resto de roles operativos
   * (admin, client, aprobador, administrador_clientes), el rol relevante es
   * el de la empresa activa: usa `tenants[].role` / `tenants[].roles`, o
   * mejor aún, `authStore.currentRole` (ya resuelto para la sesión activa).
   * Se deja el tipo sin ampliar para no romper el CRUD de usuarios
   * (UserFormPage), que asume estos 3 valores para el toggle root/empresa.
   */
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
  supervisor?: SupervisorBasic | null;
  /**
   * Roles operativos del usuario en esta empresa (admin, client, aprobador,
   * administrador_clientes). Viene de user_tenant_roles (ver
   * AuthService::transformAuthUser en backend).
   */
  roles?: string[];
  /**
   * Rol de mayor prioridad entre `roles` para esta empresa (ver
   * User::ROLE_PRIORITY / User::highestPriorityRole en backend). Es la base
   * para el rol activo por defecto al entrar a esta empresa.
   */
  role?: string | null;
  /**
   * IDs de los roles operativos (`roles`) del usuario en esta empresa. Base
   * para pre-seleccionar el multi-select de roles al editar en UserFormPage
   * (evita tener que resolver name -> id de nuevo contra el catálogo).
   */
  role_ids?: number[];
  /** Fecha de inicio laboral en esta empresa (YYYY-MM-DD). */
  hire_date?: string | null;
  /**
   * Saldo inicial de vacaciones (días) al momento de asignar la empresa.
   * El backend expone la columna `decimal` del pivote tal cual (puede llegar
   * como string, p. ej. "5.00", según el driver de BD) — no asumir `number`.
   */
  vacation_balance_initial?: number | string | null;
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
