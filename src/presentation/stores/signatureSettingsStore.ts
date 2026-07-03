import { create } from "zustand";
import { SignatureSettings } from "@/core/domain/entities";
import { UploadCertificateRequest } from "@/core/domain/repositories/ISignatureSettingsRepository";
import { signatureSettingsRepository } from "@/infrastructure/persistence/repositories";
import { getErrorMessage } from "@/infrastructure/http/apiClient";

interface SignatureSettingsState {
  settings: SignatureSettings | null;
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;

  fetchSettings: () => Promise<void>;
  uploadCertificate: (data: UploadCertificateRequest) => Promise<void>;
  setEnabled: (enabled: boolean) => Promise<void>;
  deleteCertificate: () => Promise<void>;
  clearError: () => void;
}

export const useSignatureSettingsStore = create<SignatureSettingsState>((set) => ({
  settings: null,
  isLoading: false,
  isSaving: false,
  error: null,

  fetchSettings: async () => {
    set({ isLoading: true, error: null });
    try {
      const settings = await signatureSettingsRepository.getSettings();
      set({ settings, isLoading: false });
    } catch (error) {
      set({ error: getErrorMessage(error), isLoading: false });
    }
  },

  uploadCertificate: async (data: UploadCertificateRequest) => {
    set({ isSaving: true, error: null });
    try {
      const settings = await signatureSettingsRepository.uploadCertificate(data);
      set({ settings, isSaving: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({ error: errorMessage, isSaving: false });
      throw error;
    }
  },

  setEnabled: async (enabled: boolean) => {
    set({ isSaving: true, error: null });
    try {
      const settings = await signatureSettingsRepository.updateEnabled(enabled);
      set({ settings, isSaving: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({ error: errorMessage, isSaving: false });
      throw error;
    }
  },

  deleteCertificate: async () => {
    set({ isSaving: true, error: null });
    try {
      const settings = await signatureSettingsRepository.deleteCertificate();
      set({ settings, isSaving: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({ error: errorMessage, isSaving: false });
      throw error;
    }
  },

  clearError: () => set({ error: null }),
}));
