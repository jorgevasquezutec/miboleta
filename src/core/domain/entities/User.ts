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
   * (admin_tenant, admin, client, aprobador), el rol relevante es
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

  /**
   * Los 4 conceptos de vacaciones del cliente (VacationBalanceService::
   * computeFourFigures) para la empresa ACTIVA de la petición — el mismo
   * criterio de scoping que ya usa el resto del listado (header
   * X-Tenant-Ids, o `?tenant_id` explícito para root). Backend:
   * UserController::index().
   *
   * `null` cuando no hay una empresa activa inequívoca (root en modo "todas
   * las empresas"): el saldo de un usuario depende de su hire_date /
   * vacation_balance_initial POR empresa, así que no existe una única cifra
   * correcta en ese modo — no se inventa una suma entre empresas.
   */
  vacation_balance?: VacationBalanceSummary | null;

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
   * Roles operativos del usuario en esta empresa (admin_tenant, admin,
   * client, aprobador). Viene de user_tenant_roles (ver
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
  /** Departamento/área del usuario en esta empresa. */
  department?: string | null;
  /** Cargo/puesto del usuario en esta empresa. */
  position?: string | null;
}

/**
 * Saldo de vacaciones de un usuario en una empresa concreta, con el
 * vocabulario del cliente (mensaje del 31/07/2026 — ver
 * SPEC-VACACIONES v2 y VacationBalanceService en el backend):
 *   - pending:   Vacaciones Pendientes (initial + acumulado de años completos)
 *   - taken:     Vacaciones Gozadas (tomadas, acumulado)
 *   - truncated: Vacaciones Truncas (del año laboral en curso, 2.5 x mes)
 *   - balance:   Saldo Vacaciones (pending + truncated - taken)
 */
export interface VacationBalanceSummary {
  tenant_id: string | number;
  pending: number;
  taken: number;
  truncated: number;
  balance: number;
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
