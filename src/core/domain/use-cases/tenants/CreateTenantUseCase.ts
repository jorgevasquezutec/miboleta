import { ITenantRepository } from '@/core/domain/repositories';
import { Tenant, CreateTenantData } from '@/core/domain/entities';
import { isValidRUC } from '@/shared/utils';

/**
 * Use Case: Crear un nuevo tenant (empresa)
 */
export class CreateTenantUseCase {
  constructor(private tenantRepository: ITenantRepository) {}

  async execute(data: CreateTenantData): Promise<Tenant> {
    // Validaciones
    if (!data.name || data.name.trim().length < 3) {
      throw new Error('El nombre debe tener al menos 3 caracteres');
    }

    if (!isValidRUC(data.ruc)) {
      throw new Error('RUC inválido (debe tener 11 dígitos)');
    }

    if (!data.address || data.address.trim().length < 5) {
      throw new Error('La dirección debe tener al menos 5 caracteres');
    }

    // Crear tenant
    return await this.tenantRepository.create(data);
  }
}
