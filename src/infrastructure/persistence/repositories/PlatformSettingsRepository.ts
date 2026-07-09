import {
  IPlatformSettingsRepository,
  UpdatePlatformSettingsRequest,
} from '@/core/domain/repositories/IPlatformSettingsRepository';
import { PlatformSettings } from '@/core/domain/entities/PlatformSettings';
import apiClient from '@/infrastructure/http/apiClient';

interface PlatformSettingsResponseData {
  public_ip: string | null;
  updated_at: string | null;
}

/**
 * Repository - Configuración general de la plataforma (IP pública, ítem 23).
 * Endpoints SOLO root: GET/PUT /platform/settings.
 */
export class PlatformSettingsRepository implements IPlatformSettingsRepository {
  async getSettings(): Promise<PlatformSettings> {
    const response = await apiClient.get<{ data: PlatformSettingsResponseData }>('/platform/settings');
    return this.mapSettings(response.data.data);
  }

  async updateSettings(data: UpdatePlatformSettingsRequest): Promise<PlatformSettings> {
    const response = await apiClient.put<{ data: PlatformSettingsResponseData }>('/platform/settings', {
      public_ip: data.publicIp,
    });
    return this.mapSettings(response.data.data);
  }

  private mapSettings(data: PlatformSettingsResponseData): PlatformSettings {
    return {
      publicIp: data.public_ip,
      updatedAt: data.updated_at,
    };
  }
}

// Singleton instance
export const platformSettingsRepository = new PlatformSettingsRepository();
