import { User, CreateUserData, UpdateUserData } from '../entities';
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
 * /login, que lo envuelve en {user}) más la Matriz de Accesos como campo
 * hermano.
 */
export type MeResponse = User & {
  access_matrix?: Record<string, string[]>;
};

export interface GetUsersParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  tenant_id?: string;
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
}
