import { create } from 'zustand';
import { Tenant, CreateTenantData, UpdateTenantData } from '@/core/domain/entities/Tenant';
import {
    tenantRepository,
    PaginationMeta,
    GetTenantsParams,
} from '@/infrastructure/persistence/repositories/TenantRepository';

interface TenantsState {
    // State
    tenants: Tenant[];
    currentTenant: Tenant | null;
    isLoading: boolean;
    error: string | null;
    pagination: PaginationMeta | null;

    // Filters
    search: string;
    statusFilter: string;

    // Actions
    fetchTenants: () => Promise<void>;
    fetchTenantById: (id: string) => Promise<void>;
    createTenant: (data: CreateTenantData) => Promise<Tenant | null>;
    updateTenant: (id: string, data: UpdateTenantData) => Promise<Tenant | null>;
    deleteTenant: (id: string) => Promise<boolean>;

    // Pagination actions
    goToPage: (page: number) => Promise<void>;
    changePerPage: (perPage: number) => Promise<void>;

    // Filter actions
    setSearch: (search: string) => void;
    setStatusFilter: (status: string) => void;

    // Utility actions
    clearError: () => void;
    clearCurrentTenant: () => void;
}

export const useTenantsStore = create<TenantsState>((set, get) => ({
    // Initial state
    tenants: [],
    currentTenant: null,
    isLoading: false,
    error: null,
    pagination: null,
    search: '',
    statusFilter: '',

    // Fetch all tenants with pagination and filters
    fetchTenants: async () => {
        set({ isLoading: true, error: null });
        try {
            const { pagination, search, statusFilter } = get();

            const params: GetTenantsParams = {
                page: pagination?.current_page || 1,
                per_page: pagination?.per_page || 10,
            };

            if (search) {
                params.search = search;
            }

            if (statusFilter) {
                params.status = statusFilter;
            }

            const response = await tenantRepository.getAll(params);

            set({
                tenants: response.data,
                pagination: response.meta,
                isLoading: false,
            });
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al cargar organizaciones',
                isLoading: false,
            });
        }
    },

    // Fetch single tenant by ID
    fetchTenantById: async (id: string) => {
        set({ isLoading: true, error: null });
        try {
            const tenant = await tenantRepository.getById(id);
            set({
                currentTenant: tenant,
                isLoading: false,
            });
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al cargar organización',
                isLoading: false,
            });
        }
    },

    // Create new tenant
    createTenant: async (data: CreateTenantData) => {
        set({ isLoading: true, error: null });
        try {
            const tenant = await tenantRepository.create(data);
            set({ isLoading: false });

            // Refresh the list
            await get().fetchTenants();

            return tenant;
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al crear organización',
                isLoading: false,
            });
            return null;
        }
    },

    // Update existing tenant
    updateTenant: async (id: string, data: UpdateTenantData) => {
        set({ isLoading: true, error: null });
        try {
            const tenant = await tenantRepository.update(id, data);

            // Update in the list if present
            set((state) => ({
                tenants: state.tenants.map((t) => (t.id === id ? tenant : t)),
                currentTenant: state.currentTenant?.id === id ? tenant : state.currentTenant,
                isLoading: false,
            }));

            return tenant;
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al actualizar organización',
                isLoading: false,
            });
            return null;
        }
    },

    // Delete tenant
    deleteTenant: async (id: string) => {
        set({ isLoading: true, error: null });
        try {
            await tenantRepository.delete(id);

            // Remove from the list
            set((state) => ({
                tenants: state.tenants.filter((t) => t.id !== id),
                isLoading: false,
            }));

            return true;
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al eliminar organización',
                isLoading: false,
            });
            return false;
        }
    },

    // Pagination: Go to specific page
    goToPage: async (page: number) => {
        set((state) => ({
            pagination: state.pagination
                ? { ...state.pagination, current_page: page }
                : null,
        }));
        await get().fetchTenants();
    },

    // Pagination: Change items per page
    changePerPage: async (perPage: number) => {
        set((state) => ({
            pagination: state.pagination
                ? { ...state.pagination, per_page: perPage, current_page: 1 }
                : { current_page: 1, per_page: perPage, last_page: 1, total: 0, from: null, to: null },
        }));
        await get().fetchTenants();
    },

    // Set search filter
    setSearch: (search: string) => {
        set({ search });
    },

    // Set status filter
    setStatusFilter: (status: string) => {
        set({ statusFilter: status });
    },

    // Clear error
    clearError: () => {
        set({ error: null });
    },

    // Clear current tenant
    clearCurrentTenant: () => {
        set({ currentTenant: null });
    },
}));
