import { create } from 'zustand';
import { toast } from 'sonner';
import { Tenant, CreateTenantData, UpdateTenantData } from '@/core/domain/entities/Tenant';
import {
    tenantRepository,
    GetTenantsParams,
} from '@/infrastructure/persistence/repositories/TenantRepository';
import { PaginationMeta } from '@/infrastructure/persistence/repositories/types';

interface TenantsState {
    // State
    tenants: Tenant[];
    currentTenant: Tenant | null;
    isLoading: boolean;
    error: string | null;
    pagination: PaginationMeta | null;

    // Search state (para el selector)
    searchResults: Tenant[];
    isSearching: boolean;
    searchPagination: PaginationMeta | null;

    // Filters
    search: string;
    statusFilter: string;

    // Actions
    fetchTenants: () => Promise<void>;
    fetchTenantById: (id: string) => Promise<void>;
    createTenant: (data: CreateTenantData) => Promise<Tenant | null>;
    updateTenant: (id: string, data: UpdateTenantData) => Promise<Tenant | null>;
    deleteTenant: (id: string) => Promise<boolean>;

    // Search actions (para el selector multi-tenant)
    searchTenants: (query: string, page?: number) => Promise<void>;
    loadMoreSearchResults: () => Promise<void>;
    clearSearchResults: () => void;

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
    searchResults: [],
    isSearching: false,
    searchPagination: null,
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
            // La página (TenantFormPage) pinta los errores por campo con
            // useFormErrors + showApiError; el store ya no tuesta aquí, solo
            // relanza para que el catch de la página lo reciba (tarea 6.4).
            const errorMessage = error instanceof Error ? error.message : 'Error al crear organización';
            set({
                error: errorMessage,
                isLoading: false,
            });
            throw error;
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
            // Igual que createTenant: el store relanza en vez de tostear,
            // para que TenantFormPage pinte los errores por campo.
            const errorMessage = error instanceof Error ? error.message : 'Error al actualizar organización';
            set({
                error: errorMessage,
                isLoading: false,
            });
            throw error;
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
            const errorMessage = error instanceof Error ? error.message : 'Error al eliminar organización';
            set({
                error: errorMessage,
                isLoading: false,
            });
            toast.error(errorMessage);
            return false;
        }
    },

    // Search tenants (para el selector multi-tenant)
    searchTenants: async (query: string, page: number = 1) => {
        set({ isSearching: true, error: null });
        try {
            const params: GetTenantsParams = {
                page,
                per_page: 20,
                search: query,
                status: 'active', // Solo mostrar activos
            };

            const response = await tenantRepository.getAll(params);

            set({
                search: query,
                searchResults: page === 1 ? response.data : [...get().searchResults, ...response.data],
                searchPagination: response.meta,
                isSearching: false,
            });
        } catch (error) {
            set({
                error: error instanceof Error ? error.message : 'Error al buscar organizaciones',
                isSearching: false,
            });
        }
    },

    // Load more search results (infinite scroll)
    loadMoreSearchResults: async () => {
        const { searchPagination } = get();
        if (!searchPagination || searchPagination.current_page >= searchPagination.last_page) {
            return;
        }

        const nextPage = searchPagination.current_page + 1;
        await get().searchTenants(get().search, nextPage);
    },

    // Clear search results
    clearSearchResults: () => {
        set({
            searchResults: [],
            searchPagination: null,
        });
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
