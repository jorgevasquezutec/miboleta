import { IAuditSettingsRepository } from '@/core/domain/repositories/IAuditSettingsRepository';
import { AuditActionSetting } from '@/core/domain/entities/AuditSettings';
import apiClient from '@/infrastructure/http/apiClient';

interface AuditActionSettingResponse {
  action: string;
  label: string;
  category: string;
  enabled: boolean;
  locked: boolean;
  count: number;
}

/**
 * Repository - Mantenedor de auditoría (solo root): catálogo de acciones y
 * activar/desactivar su captura. Endpoints: GET/PUT /audit/settings.
 */
export class AuditSettingsRepository implements IAuditSettingsRepository {
  async getCatalog(): Promise<AuditActionSetting[]> {
    const response = await apiClient.get<{ data: AuditActionSettingResponse[] }>('/audit/settings');
    return response.data.data;
  }

  async updateSettings(disabledActions: string[]): Promise<AuditActionSetting[]> {
    const response = await apiClient.put<{ data: AuditActionSettingResponse[] }>('/audit/settings', {
      disabled_actions: disabledActions,
    });
    return response.data.data;
  }
}

// Singleton instance
export const auditSettingsRepository = new AuditSettingsRepository();
