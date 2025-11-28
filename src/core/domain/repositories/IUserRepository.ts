// Repository Interface - User Repository
import { User, CreateUserData, UpdateUserData } from '../entities/User';

export interface IUserRepository {
  findAll(tenantId?: string): Promise<User[]>;
  findById(id: string): Promise<User | null>;
  findByEmail(email: string): Promise<User | null>;
  create(data: CreateUserData): Promise<User>;
  update(id: string, data: UpdateUserData): Promise<User>;
  delete(id: string): Promise<void>;
}
