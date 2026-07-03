import { User, CreateUserData, UpdateUserData } from '../entities';
import { PaginatedResponse } from '@/infrastructure/persistence/repositories/types';

export interface LoginResponse {
  user: User;
}

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
  me(): Promise<User>;
  findAll(): Promise<User[]>;
  getUsers(params?: GetUsersParams): Promise<PaginatedResponse<User>>;
  findById(id: string): Promise<User | null>;
  findByEmail(email: string): Promise<User | null>;
  create(data: CreateUserData): Promise<User>;
  update(id: string, data: UpdateUserData): Promise<User>;
  delete(id: string): Promise<void>;
}
