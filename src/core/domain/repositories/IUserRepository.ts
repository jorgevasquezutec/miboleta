import { User, CreateUserData, UpdateUserData, ImpersonatorInfo } from '../entities';
import { PaginatedResponse } from '@/infrastructure/persistence/repositories/types';

export interface LoginResponse {
  user: User;
  /**
   * Matriz de Accesos (ability -> roles permitidos) servida por el backend
   * desde config/access_matrix.php. Opcional para tolerar un backend anterior
   * al cambio. Ver authStore.accessMatrix y hooks/useCan.ts.
   */
  access_matrix?: Record<string, string[]>;
}

/**
 * Respuesta de GET /me: el usuario plano en la raíz (asimétrico respecto a
 * /login, que lo envuelve en {user}) más la Matriz de Accesos y el
 * `impersonator` (o null) como campos hermanos. Ver CONTRATO-IMPERSONATION:
 * es la ÚNICA fuente de verdad para el impersonator tras un F5 — no se
 * persiste en localStorage (ver authStore.impersonator).
 */
export type MeResponse = User & {
  access_matrix?: Record<string, string[]>;
  impersonator?: ImpersonatorInfo | null;
};

/** Respuesta de POST /users/{id}/impersonate. */
export interface ImpersonateResponse {
  user: User;
  access_matrix?: Record<string, string[]>;
  impersonator: ImpersonatorInfo;
}

/** Respuesta de POST /impersonate/leave. */
export interface LeaveImpersonationResponse {
  user: User;
  access_matrix?: Record<string, string[]>;
}

export interface GetUsersParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  tenant_id?: string;
  /**
   * Papelera: lista SOLO las cuentas eliminadas en vez de las activas (son
   * dos listados disjuntos, no un superconjunto). Exclusivo de root — el
   * backend lo gatea con la ability 'users.restore'.
   */
  deleted?: boolean;
}

export interface IUserRepository {
  /**
   * @param login DNI o correo electrónico (el backend acepta ambos en el
   * campo `login`; ver AuthController::login / RP1-B).
   */
  login(login: string, password: string): Promise<LoginResponse>;
  logout(): Promise<void>;
  me(): Promise<MeResponse>;
  /** Matriz de Accesos servida por el backend; ver authStore.accessMatrix. */
  getAccessMatrix(): Promise<Record<string, string[]>>;
  findAll(): Promise<User[]>;
  getUsers(params?: GetUsersParams): Promise<PaginatedResponse<User>>;
  findById(id: string): Promise<User | null>;
  findByEmail(email: string): Promise<User | null>;
  create(data: CreateUserData): Promise<User>;
  update(id: string, data: UpdateUserData): Promise<User>;
  delete(id: string): Promise<void>;
  /** Habilita una cuenta eliminada (soft delete). Solo root. */
  restore(id: string): Promise<void>;
  /**
   * root entra a operar como `id` (ver CONTRATO-IMPERSONATION). El backend
   * marca el token nuevo (name: "impersonation:{rootId}") y lo entrega vía
   * cookies HttpOnly; el frontend nunca ve ni maneja los tokens.
   */
  impersonate(id: string): Promise<ImpersonateResponse>;
  /** Termina la impersonation activa y restaura la sesión del root. */
  stopImpersonating(): Promise<LeaveImpersonationResponse>;
}
