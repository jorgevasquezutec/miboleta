import { PlatformSettings } from '../entities/PlatformSettings';

export interface UpdatePlatformSettingsRequest {
  publicIp: string | null;
}

export interface IPlatformSettingsRepository {
  getSettings(): Promise<PlatformSettings>;
  updateSettings(data: UpdatePlatformSettingsRequest): Promise<PlatformSettings>;
}
