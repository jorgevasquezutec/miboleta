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

    /**
     * Id del usuario dueño del filtro persistido (null si no hay sesión).
     *
     * El filtro vive en localStorage y sobrevive a la sesión. Sin este sello,
     * el siguiente usuario que entra en el mismo navegador hereda las empresas
     * del anterior y el backend le responde 403 ("Las empresas seleccionadas no
     * están asociadas a tu cuenta") en TODAS sus peticiones.
     */
    ownerUserId: string | null;

    // Actions
    setFilter: (tenantIds: string[], availableTenants: TenantAssociation[], mode?: TenantFilterMode) => void;
    clearFilter: () => void;
    toggleTenant: (tenantId: string, availableTenants: TenantAssociation[]) => void;
    selectAll: (availableTenants: TenantAssociation[]) => void;

    /** Descarta el filtro y lo sella a nombre de `userId` (login / cambio de dueño). */
    resetForUser: (userId: string | number | null) => void;

    // Selectors (memoizados internamente)
    getFilteredTenantIds: () => number[] | undefined;
    getFilterQuery: () => string;
    isFiltering: () => boolean;
    getFilterDisplayText: () => string;
}

/** Filtro por defecto: sin selección explícita (todas las empresas del usuario). */
const defaultFilter = (): TenantFilter => ({
    mode: 'all',
    tenantIds: [],
    tenants: [],
});

/**
 * Lee el usuario autenticado desde el storage del authStore.
 * Devuelve null si no hay sesión o el JSON está corrupto.
 */
const readAuthUser = (): { id?: string | number; role?: string; tenants?: any[] } | null => {
    try {
        const authStorage = localStorage.getItem('auth-storage');
        if (!authStorage) return null;
        const { state } = JSON.parse(authStorage);
        return state?.user ?? null;
    } catch {
        return null;
    }
};

/** Id del usuario autenticado como string, o null si no hay sesión. */
const currentOwnerId = (): string | null => {
    const id = readAuthUser()?.id;
    return id === undefined || id === null ? null : String(id);
};

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
            filter: defaultFilter(),
            ownerUserId: null,

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
                    },
                    ownerUserId: currentOwnerId(),
                });
            },

            /**
             * Limpia el filtro (mostrar todas las empresas)
             * For non-root users, 'all' means all their assigned tenants
             */
            clearFilter: () => {
                console.log('🏢 [TenantFilter] Clearing filter');

                const user = readAuthUser();
                const owner = currentOwnerId();

                // Check if user is root
                if (user && user.role !== 'root' && user.tenants && user.tenants.length > 0) {
                    // Non-root: set mode 'all' but include their tenant IDs
                    const tenantIds = user.tenants.map((t: any) => String(t.id));
                    const tenants = user.tenants as TenantAssociation[];
                    set({
                        filter: {
                            mode: 'all',
                            tenantIds,
                            tenants,
                        },
                        ownerUserId: owner,
                    });
                    return;
                }

                set({
                    filter: defaultFilter(),
                    ownerUserId: owner,
                });
            },

            /**
             * Descarta el filtro y lo sella a nombre de `userId`.
             *
             * Se llama al iniciar sesión: el filtro arranca sin selección, así que
             * nunca puede arrastrar empresas del usuario anterior. Para usuarios
             * no-root el interceptor rellena el header con sus propias empresas
             * mientras no elijan una (`apiClient.ts`), así que no se pierde nada.
             */
            resetForUser: (userId: string | number | null) => {
                const owner = userId === undefined || userId === null ? null : String(userId);
                console.log(`🏢 [TenantFilter] Filtro reiniciado para el usuario ${owner ?? '(sin sesión)'}`);
                set({ filter: defaultFilter(), ownerUserId: owner });
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
                const isRoot = readAuthUser()?.role === 'root';

                if (isRoot) {
                    // Root users: set mode 'all' without IDs (sees everything)
                    set({
                        filter: defaultFilter(),
                        ownerUserId: currentOwnerId(),
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
                // Solo persistir el filtro y su dueño, no las funciones
                filter: state.filter,
                ownerUserId: state.ownerUserId,
            }),
            /**
             * Al rehidratar, el filtro solo se conserva si está sellado con el
             * usuario que hay ahora en sesión. Un filtro sin sello (persistido
             * por una versión anterior) o de otro usuario se descarta: es el caso
             * de la sesión que murió sin logout y dejó sus empresas en el
             * navegador para el siguiente que entrara.
             */
            onRehydrateStorage: () => (state, error) => {
                if (error || !state) return;

                const owner = currentOwnerId();
                if (state.ownerUserId !== null && state.ownerUserId === owner) return;

                console.warn(
                    `🏢 [TenantFilter] Filtro persistido de otra sesión (dueño ${state.ownerUserId ?? 'sin sello'}, sesión actual ${owner ?? 'ninguna'}): se descarta`
                );
                state.resetForUser(owner);
            },
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
