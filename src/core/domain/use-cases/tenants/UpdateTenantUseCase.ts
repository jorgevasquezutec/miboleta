import { ITenantRepository } from '@/core/domain/repositories';
import { Tenant, UpdateTenantData } from '@/core/domain/entities';
import { isValidRUC } from '@/shared/utils';

/**
 * Use Case: Actualizar un tenant existente
 */
export class UpdateTenantUseCase {
  constructor(private tenantRepository: ITenantRepository) {}

  async execute(id: string, data: UpdateTenantData): Promise<Tenant> {
    // Verificar que el tenant existe
    const existingTenant = await this.tenantRepository.findById(id);
    if (!existingTenant) {
      throw new Error('Empresa no encontrada');
    }

    // Validaciones
    if (data.name && data.name.trim().length < 3) {
      throw new Error('El nombre debe tener al menos 3 caracteres');
    }

    if (data.ruc && !isValidRUC(data.ruc)) {
      throw new Error('RUC inválido (debe tener 11 dígitos)');
    }

    if (data.address && data.address.trim().length < 5) {
      throw new Error('La dirección debe tener al menos 5 caracteres');
    }

    // Actualizar tenant
    return await this.tenantRepository.update(id, data);
  }
}
