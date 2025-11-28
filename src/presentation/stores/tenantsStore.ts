import { create } from "zustand";
import { Tenant, CreateTenantData, UpdateTenantData } from "@/core/domain/entities";
import { GetTenantsUseCase, CreateTenantUseCase, UpdateTenantUseCase } from "@/core/domain/use-cases/tenants";
import { tenantRepository } from "@/infrastructure/persistence/repositories";

// Instanciar use cases
const getTenantsUseCase = new GetTenantsUseCase(tenantRepository);
const createTenantUseCase = new CreateTenantUseCase(tenantRepository);
const updateTenantUseCase = new UpdateTenantUseCase(tenantRepository);

interface TenantsState {
  tenants: Tenant[];
  currentTenant: Tenant | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchTenants: () => Promise<void>;
  createTenant: (tenantData: CreateTenantData) => Promise<Tenant>;
  updateTenant: (id: string, updates: UpdateTenantData) => Promise<Tenant>;
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
      const tenants = await getTenantsUseCase.execute();

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

  createTenant: async (tenantData: CreateTenantData) => {
    set({ isLoading: true, error: null });

    try {
      const newTenant = await createTenantUseCase.execute(tenantData);

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
      const updatedTenant = await updateTenantUseCase.execute(id, updates);

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
      await tenantRepository.delete(id);

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
