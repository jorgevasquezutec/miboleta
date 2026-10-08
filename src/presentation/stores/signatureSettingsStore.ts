import { create } from "zustand";
import {
  SignatureSettings,
  TenantCertificateStatus,
  TenantCertificatesSummary,
} from "@/core/domain/entities";
import {
  TenantCertificateMutationResult,
  TenantCertificatesFilters,
  UploadCertificateRequest,
  UploadTenantCertificateRequest,
} from "@/core/domain/repositories/ISignatureSettingsRepository";
import { signatureSettingsRepository } from "@/infrastructure/persistence/repositories";
import { getErrorMessage } from "@/infrastructure/http/apiClient";

interface SignatureSettingsState {
  settings: SignatureSettings | null;
  isLoading: boolean;
  isSaving: boolean;
  error: string | null;

  // Certificados por empresa (solo root). Sus errores NUNCA escriben `error`
  // (la página lo convierte en toast): el fetch usa `tenantError` (Alert en la
  // tarjeta) y upload/delete re-lanzan para que el componente muestre un único toast.
  tenantCertificates: TenantCertificateStatus[];
  tenantSummary: TenantCertificatesSummary | null;
  isLoadingTenants: boolean;
  savingTenantId: number | null;
  tenantError: string | null;
  lastTenantFilters: TenantCertificatesFilters;

  fetchSettings: () => Promise<void>;
  uploadCertificate: (data: UploadCertificateRequest) => Promise<void>;
  setEnabled: (enabled: boolean) => Promise<void>;
  deleteCertificate: () => Promise<void>;
  clearError: () => void;

  fetchTenantCertificates: (filters?: TenantCertificatesFilters) => Promise<void>;
  uploadTenantCertificate: (
    tenantId: number,
    data: UploadTenantCertificateRequest
  ) => Promise<TenantCertificateMutationResult>;
  deleteTenantCertificate: (tenantId: number) => Promise<TenantCertificateMutationResult>;
}

// Contador de peticiones de GET /signature/tenants: solo la última puede escribir el estado
let tenantRequestSeq = 0;

export const useSignatureSettingsStore = create<SignatureSettingsState>((set, get) => ({
  settings: null,
  isLoading: false,
  isSaving: false,
  error: null,

  tenantCertificates: [],
  tenantSummary: null,
  isLoadingTenants: false,
  savingTenantId: null,
  tenantError: null,
  lastTenantFilters: {},

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
      // El origen efectivo de las empresas sin certificado propio cambia
      await get().fetchTenantCertificates();
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
      // El origen efectivo de las empresas sin certificado propio cambia
      await get().fetchTenantCertificates();
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({ error: errorMessage, isSaving: false });
      throw error;
    }
  },

  clearError: () => set({ error: null }),

  fetchTenantCertificates: async (filters?: TenantCertificatesFilters) => {
    // Con filtros nuevos se recuerdan; sin argumentos se reusan los últimos,
    // así un refetch tras subir/eliminar conserva la búsqueda activa.
    const effective = filters ?? get().lastTenantFilters;
    const seq = ++tenantRequestSeq;
    set({ isLoadingTenants: true, tenantError: null, lastTenantFilters: effective });
    try {
      const { items, summary } = await signatureSettingsRepository.listTenantCertificates(effective);
      // Respuesta obsoleta: otra petición más reciente ya está en vuelo o terminó
      if (seq !== tenantRequestSeq) return;
      // Página vacía fuera de rango (p. ej. se eliminó la última fila de la última página): retroceder
      if (items.length === 0 && (effective.page ?? 1) > 1 && summary.lastPage < (effective.page ?? 1)) {
        await get().fetchTenantCertificates({ ...effective, page: summary.lastPage });
        return;
      }
      set({ tenantCertificates: items, tenantSummary: summary, isLoadingTenants: false });
    } catch (error) {
      if (seq !== tenantRequestSeq) return;
      set({ tenantError: getErrorMessage(error), isLoadingTenants: false });
    }
  },

  uploadTenantCertificate: async (tenantId: number, data: UploadTenantCertificateRequest) => {
    set({ savingTenantId: tenantId });
    try {
      const result = await signatureSettingsRepository.uploadTenantCertificate(tenantId, data);
      await get().fetchTenantCertificates();
      return result;
    } finally {
      set({ savingTenantId: null });
    }
  },

  deleteTenantCertificate: async (tenantId: number) => {
    set({ savingTenantId: tenantId });
    try {
      const result = await signatureSettingsRepository.deleteTenantCertificate(tenantId);
      await get().fetchTenantCertificates();
      return result;
    } finally {
      set({ savingTenantId: null });
    }
  },
}));
