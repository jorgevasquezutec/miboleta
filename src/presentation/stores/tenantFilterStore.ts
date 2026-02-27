import { create } from "zustand";
import { persist } from "zustand/middleware";
import { useShallow } from "zustand/react/shallow";
import { TenantAssociation } from "@/core/domain/entities";

/**
 * Modo de filtrado de tenants
 * - 'all': Sin filtro, mostrar todas las empresas
 * - 'single': Filtrar por una sola empresa
 * - 'selected': Filtrar por múltiples empresas seleccionadas
 */
export type TenantFilterMode = 'all' | 'single' | 'selected';

/**
 * Configuración del filtro de tenants
 */
export interface TenantFilter {
    mode: TenantFilterMode;
    tenantIds: string[];
    tenants: TenantAssociation[];
}

/**
 * Estado del store de filtros de tenant
 */
interface TenantFilterState {
    filter: TenantFilter;

    // Actions
    setFilter: (tenantIds: string[], availableTenants: TenantAssociation[], mode?: TenantFilterMode) => void;
    clearFilter: () => void;
    toggleTenant: (tenantId: string, availableTenants: TenantAssociation[]) => void;
    selectAll: (availableTenants: TenantAssociation[]) => void;

    // Selectors (memoizados internamente)
    getFilteredTenantIds: () => number[] | undefined;
    getFilterQuery: () => string;
    isFiltering: () => boolean;
    getFilterDisplayText: () => string;
}

/**
 * Store especializado para manejo de filtros de tenant
 * 
 * Separado del authStore para:
 * - Mejor separación de responsabilidades
 * - Facilitar testing
 * - Performance optimizada con selectores
 * 
 * @example
 * ```tsx
 * const { filter, setFilter, isFiltering } = useTenantFilterStore();
 * 
 * // Filtrar por 2 empresas
 * setFilter(['1', '2']);
 * 
 * // Limpiar filtro (mostrar todas)
 * clearFilter();
 * 
 * // Verificar si está filtrando
 * if (isFiltering()) {
 *   console.log('Filtrando por:', filter.tenants.length, 'empresas');
 * }
 * ```
 */
export const useTenantFilterStore = create<TenantFilterState>()(
    persist(
        (set, get) => ({
            // Estado inicial: sin filtro (todas las empresas)
            filter: {
                mode: 'all',
                tenantIds: [],
                tenants: [],
            },

            /**
             * Establece el filtro de tenants
             * @param tenantIds - Array de IDs de tenants a filtrar
             * @param availableTenants - Array de tenants disponibles del usuario
             * @param mode - Modo de filtrado (se calcula automáticamente si no se provee)
             */
            setFilter: (tenantIds: string[], availableTenants: TenantAssociation[], mode?: TenantFilterMode) => {
                // Obtener objetos completos de los tenants
                const tenants = availableTenants.filter(t =>
                    tenantIds.includes(String(t.id))
                );

                // Calcular modo automáticamente si no se provee
                const finalMode = mode || (
                    tenantIds.length === 0 ? 'all' :
                        tenantIds.length === 1 ? 'single' :
                            'selected'
                );

                console.log(`🏢 [TenantFilter] Setting filter:`, {
                    mode: finalMode,
                    tenantCount: tenants.length,
                    tenants: tenants.map(t => t.name).join(', ') || 'All',
                });

                set({
                    filter: {
                        mode: finalMode,
                        tenantIds,
                        tenants
                    }
                });
            },

            /**
             * Limpia el filtro (mostrar todas las empresas)
             * For non-root users, 'all' means all their assigned tenants
             */
            clearFilter: () => {
                console.log('🏢 [TenantFilter] Clearing filter');

                // Check if user is root
                try {
                    const authStorage = localStorage.getItem('auth-storage');
                    if (authStorage) {
                        const { state } = JSON.parse(authStorage);
                        const user = state?.user;
                        if (user?.role !== 'root' && user?.tenants && user.tenants.length > 0) {
                            // Non-root: set mode 'all' but include their tenant IDs
                            const tenantIds = user.tenants.map((t: any) => String(t.id));
                            const tenants = user.tenants.map((t: any) => ({
                                id: t.id,
                                name: t.name,
                                slug: t.slug,
                            }));
                            set({
                                filter: {
                                    mode: 'all',
                                    tenantIds,
                                    tenants,
                                }
                            });
                            return;
                        }
                    }
                } catch (e) {
                    // Fallback to default behavior
                }

                set({
                    filter: {
                        mode: 'all',
                        tenantIds: [],
                        tenants: []
                    }
                });
            },

            /**
             * Toggle de un tenant específico en la selección
             * @param tenantId - ID del tenant a toggle
             * @param availableTenants - Array de tenants disponibles del usuario
             */
            toggleTenant: (tenantId: string, availableTenants: TenantAssociation[]) => {
                const { filter, setFilter } = get();

                let newIds: string[];
                if (filter.tenantIds.includes(tenantId)) {
                    // Deseleccionar
                    newIds = filter.tenantIds.filter(id => id !== tenantId);
                } else {
                    // Seleccionar
                    newIds = [...filter.tenantIds, tenantId];
                }

                setFilter(newIds, availableTenants);
            },

            /**
             * Selecciona todos los tenants disponibles
             * @param availableTenants - Array de tenants disponibles del usuario
             */
            selectAll: (availableTenants: TenantAssociation[]) => {
                // Check if user is root
                let isRoot = false;
                try {
                    const authStorage = localStorage.getItem('auth-storage');
                    if (authStorage) {
                        const { state } = JSON.parse(authStorage);
                        isRoot = state?.user?.role === 'root';
                    }
                } catch (e) {
                    // Default to non-root behavior
                }

                if (isRoot) {
                    // Root users: set mode 'all' without IDs (sees everything)
                    set({
                        filter: {
                            mode: 'all',
                            tenantIds: [],
                            tenants: [],
                        }
                    });
                } else {
                    // Non-root users: select all available tenants with IDs
                    const allIds = availableTenants.map(t => String(t.id));
                    get().setFilter(allIds, availableTenants, 'all');
                }
            },

            /**
             * Obtiene los IDs de tenants filtrados como números
             * @returns Array de IDs o undefined si no hay filtro (todas las empresas)
             * 
             * @example
             * ```tsx
             * const tenantIds = getFilteredTenantIds();
             * // tenantIds = [1, 2, 3] o undefined
             * ```
             */
            getFilteredTenantIds: () => {
                const { filter } = get();
                if (filter.mode === 'all') return undefined;
                return filter.tenantIds.map(Number);
            },

            /**
             * Obtiene la query string para enviar al API
             * @returns String con IDs separados por coma, o string vacío si no hay filtro
             * 
             * @example
             * ```tsx
             * const query = getFilterQuery();
             * // query = "1,2,3" o ""
             * ```
             */
            getFilterQuery: () => {
                const { filter } = get();
                if (filter.mode === 'all') return '';
                return filter.tenantIds.join(',');
            },

            /**
             * Verifica si hay un filtro activo
             * @returns true si está filtrando, false si muestra todas las empresas
             */
            isFiltering: () => {
                const { filter } = get();
                return filter.mode !== 'all';
            },

            /**
             * Obtiene el texto a mostrar en la UI del filtro
             * @returns String descriptivo del filtro actual
             */
            getFilterDisplayText: () => {
                const { filter } = get();

                if (filter.mode === 'all') {
                    return 'Todas las empresas';
                }

                if (filter.mode === 'single') {
                    return filter.tenants[0]?.name || 'Empresa';
                }

                return `${filter.tenants.length} empresas`;
            },
        }),
        {
            name: "tenant-filter-storage",
            partialize: (state) => ({
                // Solo persistir el filtro, no las funciones
                filter: state.filter,
            }),
        }
    )
);

/**
 * Hook para obtener solo los valores del filtro (sin acciones)
 * Útil para componentes que solo necesitan leer el filtro
 * 
 * @example
 * ```tsx
 * function MyComponent() {
 *   const filter = useTenantFilter();
 *   return <div>Filtrando por: {filter.tenants.length} empresas</div>;
 * }
 * ```
 */
export const useTenantFilter = () => {
    return useTenantFilterStore(state => state.filter);
};

/**
 * Hook para obtener solo las acciones del filtro
 * Útil para componentes que solo necesitan modificar el filtro
 * 
 * @example
 * ```tsx
 * function FilterControls() {
 *   const { setFilter, clearFilter } = useTenantFilterActions();
 *   return (
 *     <button onClick={() => clearFilter()}>Limpiar filtro</button>
 *   );
 * }
 * ```
 */
export const useTenantFilterActions = () => {
    return useTenantFilterStore(
        useShallow(state => ({
            setFilter: state.setFilter,
            clearFilter: state.clearFilter,
            toggleTenant: state.toggleTenant,
            selectAll: state.selectAll,
        }))
    );
};

/**
 * Hook para obtener solo los selectores del filtro
 * Útil para lógica de negocio que necesita datos derivados
 * 
 * @example
 * ```tsx
 * function DataFetcher() {
 *   const { getFilteredTenantIds, isFiltering } = useTenantFilterSelectors();
 *   
 *   useEffect(() => {
 *     if (isFiltering()) {
 *       fetchData(getFilteredTenantIds());
 *     }
 *   }, [isFiltering()]);
 * }
 * ```
 */
export const useTenantFilterSelectors = () => {
    return useTenantFilterStore(
        useShallow(state => ({
            getFilteredTenantIds: state.getFilteredTenantIds,
            getFilterQuery: state.getFilterQuery,
            isFiltering: state.isFiltering,
            getFilterDisplayText: state.getFilterDisplayText,
        }))
    );
};
