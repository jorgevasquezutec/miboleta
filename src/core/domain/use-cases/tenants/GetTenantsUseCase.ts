import { ITenantRepository } from '@/core/domain/repositories';
import { Tenant } from '@/core/domain/entities';

/**
 * Use Case: Obtener todos los tenants
 */
export class GetTenantsUseCase {
  constructor(private tenantRepository: ITenantRepository) {}

  async execute(): Promise<Tenant[]> {
    return await this.tenantRepository.findAll();
  }
}
