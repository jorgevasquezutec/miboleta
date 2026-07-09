import { create } from "zustand";
import { PlatformSettings } from "@/core/domain/entities";
import { UpdatePlatformSettingsRequest } from "@/core/domain/repositories/IPlatformSettingsRepository";
import { platformSettingsRepository } from "@/infrastructure/persistence/repositories";
import { getErrorMessage } from "@/infrastructure/http/apiClient";

interface PlatformSettingsState {
  settings: PlatformSettings | null;
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;

  fetchSettings: () => Promise<void>;
  updateSettings: (data: UpdatePlatformSettingsRequest) => Promise<void>;
  clearError: () => void;
}

export const usePlatformSettingsStore = create<PlatformSettingsState>((set) => ({
  settings: null,
  isLoading: false,
  isSaving: false,
  error: null,

  fetchSettings: async () => {
    set({ isLoading: true, error: null });
    try {
      const settings = await platformSettingsRepository.getSettings();
      set({ settings, isLoading: false });
    } catch (error) {
      set({ error: getErrorMessage(error), isLoading: false });
    }
  },

  updateSettings: async (data: UpdatePlatformSettingsRequest) => {
    set({ isSaving: true, error: null });
    try {
      const settings = await platformSettingsRepository.updateSettings(data);
      set({ settings, isSaving: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({ error: errorMessage, isSaving: false });
      throw error;
    }
  },

  clearError: () => set({ error: null }),
}));
