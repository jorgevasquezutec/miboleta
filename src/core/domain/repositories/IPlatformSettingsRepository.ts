import { PlatformSettings } from '../entities/PlatformSettings';
import type { MailEncryption } from '../entities/Tenant';

export interface UpdatePlatformSettingsRequest {
  publicIp: string | null;
  // SMTP global. mailPassword es WRITE-ONLY: solo se envía cuando el usuario
  // escribe una nueva; omitirla conserva la existente en el backend.
  mailHost?: string | null;
  mailPort?: number | null;
  mailUsername?: string | null;
  mailPassword?: string;
  mailEncryption?: MailEncryption | null;
  mailFromAddress?: string | null;
  mailFromName?: string | null;
}

export interface IPlatformSettingsRepository {
  getSettings(): Promise<PlatformSettings>;
  updateSettings(data: UpdatePlatformSettingsRequest): Promise<PlatformSettings>;
}
