import { create } from "zustand";
import { mockApi, type Tenant } from "../services/mockApi";

interface TenantsState {
  tenants: Tenant[];
  currentTenant: Tenant | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchTenants: () => Promise<void>;
  createTenant: (tenantData: Omit<Tenant, "id" | "createdAt">) => Promise<Tenant>;
  updateTenant: (id: string, updates: Partial<Tenant>) => Promise<Tenant>;
  deleteTenant: (id: string) => Promise<void>;
  setCurrentTenant: (tenant: Tenant | null) => void;
  clearError: () => void;
}

export const useTenantsStore = create<TenantsState>((set, get) => ({
  tenants: [],
  currentTenant: null,
  isLoading: false,
  error: null,

  fetchTenants: async () => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP a /api/tenants
      const tenants = await mockApi.getTenants();

      set({
        tenants,
        isLoading: false,
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al cargar empresas",
        isLoading: false,
      });
    }
  },

  createTenant: async (tenantData: Omit<Tenant, "id" | "createdAt">) => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP POST /api/tenants
      const newTenant = await mockApi.createTenant(tenantData);

      set((state) => ({
        tenants: [...state.tenants, newTenant],
        isLoading: false,
      }));

      return newTenant;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al crear empresa",
        isLoading: false,
      });
      throw error;
    }
  },

  updateTenant: async (id: string, updates: Partial<Tenant>) => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP PUT /api/tenants/:id
      const updatedTenant = await mockApi.updateTenant(id, updates);

      set((state) => ({
        tenants: state.tenants.map((t) => (t.id === id ? updatedTenant : t)),
        currentTenant: state.currentTenant?.id === id ? updatedTenant : state.currentTenant,
        isLoading: false,
      }));

      return updatedTenant;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al actualizar empresa",
        isLoading: false,
      });
      throw error;
    }
  },

  deleteTenant: async (id: string) => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP DELETE /api/tenants/:id
      await mockApi.deleteTenant(id);

      set((state) => ({
        tenants: state.tenants.filter((t) => t.id !== id),
        currentTenant: state.currentTenant?.id === id ? null : state.currentTenant,
        isLoading: false,
      }));
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al eliminar empresa",
        isLoading: false,
      });
      throw error;
    }
  },

  setCurrentTenant: (tenant: Tenant | null) => {
    set({ currentTenant: tenant });
  },

  clearError: () => set({ error: null }),
}));
