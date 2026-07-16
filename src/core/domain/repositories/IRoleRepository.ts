import { Role } from '../entities';

export interface IRoleRepository {
  getAll(): Promise<Role[]>;
}
