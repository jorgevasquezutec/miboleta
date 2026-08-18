import {
  IPlatformSettingsRepository,
  UpdatePlatformSettingsRequest,
} from '@/core/domain/repositories/IPlatformSettingsRepository';
import { PlatformSettings } from '@/core/domain/entities/PlatformSettings';
import type { MailEncryption } from '@/core/domain/entities/Tenant';
import apiClient, { toApiError } from '@/infrastructure/http/apiClient';

interface PlatformSettingsResponseData {
  public_ip: string | null;
  mail_host: string | null;
  mail_port: number | null;
  mail_username: string | null;
  mail_encryption: MailEncryption | null;
  mail_from_address: string | null;
  mail_from_name: string | null;
  has_mail_password: boolean;
  updated_at: string | null;
}

/**
 * Repository - Configuración general de la plataforma: IP pública (ítem 23) y
 * servidor SMTP global. Endpoints SOLO root: GET/PUT /platform/settings.
 */
export class PlatformSettingsRepository implements IPlatformSettingsRepository {
  async getSettings(): Promise<PlatformSettings> {
    try {
      const response = await apiClient.get<{ data: PlatformSettingsResponseData }>('/platform/settings');
      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  async updateSettings(data: UpdatePlatformSettingsRequest): Promise<PlatformSettings> {
    // La contraseña SMTP solo se envía cuando el usuario escribió una nueva
    // (write-only): un mail_password ausente conserva la existente; enviarlo
    // vacío la borraría, por eso solo se incluye si viene con contenido.
    const payload: Record<string, unknown> = {
      public_ip: data.publicIp,
      mail_host: data.mailHost ?? null,
      mail_port: data.mailPort ?? null,
      mail_username: data.mailUsername ?? null,
      mail_encryption: data.mailEncryption ?? null,
      mail_from_address: data.mailFromAddress ?? null,
      mail_from_name: data.mailFromName ?? null,
    };

    if (data.mailPassword && data.mailPassword.trim() !== '') {
      payload.mail_password = data.mailPassword;
    }

    try {
      const response = await apiClient.put<{ data: PlatformSettingsResponseData }>('/platform/settings', payload);
      return this.mapSettings(response.data.data);
    } catch (error) {
      throw toApiError(error);
    }
  }

  private mapSettings(data: PlatformSettingsResponseData): PlatformSettings {
    return {
      publicIp: data.public_ip,
      mailHost: data.mail_host,
      mailPort: data.mail_port,
      mailUsername: data.mail_username,
      mailEncryption: data.mail_encryption,
      mailFromAddress: data.mail_from_address,
      mailFromName: data.mail_from_name,
      hasMailPassword: data.has_mail_password,
      updatedAt: data.updated_at,
    };
  }
}

// Singleton instance
export const platformSettingsRepository = new PlatformSettingsRepository();
