import { create } from "zustand";
import { AuditActionSetting } from "@/core/domain/entities/AuditSettings";
import { auditSettingsRepository } from "@/infrastructure/persistence/repositories";
import { getErrorMessage } from "@/infrastructure/http/apiClient";

interface AuditSettingsState {
  catalog: AuditActionSetting[];
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;

  fetchCatalog: () => Promise<void>;
  updateSettings: (disabledActions: string[]) => Promise<void>;
  clearError: () => void;
}

export const useAuditSettingsStore = create<AuditSettingsState>((set) => ({
  catalog: [],
  isLoading: false,
  isSaving: false,
  error: null,

  fetchCatalog: async () => {
    set({ isLoading: true, error: null });
    try {
      const catalog = await auditSettingsRepository.getCatalog();
      set({ catalog, isLoading: false });
    } catch (error) {
      set({ error: getErrorMessage(error), isLoading: false });
    }
  },

  updateSettings: async (disabledActions: string[]) => {
    set({ isSaving: true, error: null });
    try {
      const catalog = await auditSettingsRepository.updateSettings(disabledActions);
      set({ catalog, isSaving: false });
    } catch (error) {
      // A diferencia de fetchCatalog, este error ya no se guarda en
      // `error`: la página (AuditSettingsPage.handleSave) lo trata con
      // showApiError, así que dejarlo aquí también duplicaría el toast vía
      // el useEffect genérico que sigue cubriendo los fallos de fetchCatalog.
      set({ isSaving: false });
      throw error;
    }
  },

  clearError: () => set({ error: null }),
}));
