import { useQuery, UseQueryOptions, UseQueryResult } from '@tanstack/react-query';
import { useTenantFilterSelectors } from '@/presentation/stores/tenantFilterStore';
import { useMemo } from 'react';

/**
 * Opciones para el hook useTenantFilteredData
 */
interface UseTenantFilteredDataOptions<TData, TError = Error>
    extends Omit<UseQueryOptions<TData, TError>, 'queryKey' | 'queryFn'> {
    /**
     * Key base para la query (sin incluir filtro de tenant)
     * El filtro se añade automáticamente
     * 
     * @example ['dashboard', 'stats']
     */
    queryKey: unknown[];

    /**
     * Función que fetches los datos
     * Recibe tenantIds como parámetro (undefined = todas las empresas)
     * 
     * @example (tenantIds) => fetchDashboardStats({ tenantIds })
     */
    queryFn: (tenantIds?: number[]) => Promise<TData>;

    /**
     * Si debe incluir el filtro de tenant en la query
     * Por defecto: true
     * 
     * Usa false para queries que no dependen de tenant (ej: user profile)
     */
    includeTenantFilter?: boolean;

    /**
     * Si debe refetch automáticamente cuando cambia el filtro de tenant
     * Por defecto: true
     */
    refetchOnFilterChange?: boolean;
}

/**
 * Hook optimizado para fetching de data filtrada por tenant(s)
 * 
 * Features:
 * - ✅ Cache automático por tenant filter
 * - ✅ Refetch inteligente al cambiar filtro
 * - ✅ Query key memoizado
 * - ✅ Integración con React Query
 * - ✅ TypeScript type-safe
 * 
 * @example
 * ```tsx
 * // Uso básico
 * const { data, isLoading, error } = useTenantFilteredData({
 *   queryKey: ['dashboard', 'stats'],
 *   queryFn: (tenantIds) => dashboardRepository.getStats({ tenantIds }),
 * });
 * 
 * // Con opciones adicionales
 * const { data, refetch } = useTenantFilteredData({
 *   queryKey: ['documents', 'list'],
 *   queryFn: (tenantIds) => fetchDocuments({ tenantIds, status: 'pending' }),
 *   staleTime: 10 * 60 * 1000, // 10 minutos
 *   enabled: someCondition, // Conditional fetching
 * });
 * 
 * // Sin filtro de tenant
 * const { data } = useTenantFilteredData({
 *   queryKey: ['user', 'profile'],
 *   queryFn: () => fetchUserProfile(),
 *   includeTenantFilter: false,
 * });
 * ```
 * 
 * @template TData - Tipo de datos que retorna la query
 * @template TError - Tipo de error (por defecto Error)
 */
export function useTenantFilteredData<TData = unknown, TError = Error>({
    queryKey,
    queryFn,
    includeTenantFilter = true,
    refetchOnFilterChange = true,
    ...options
}: UseTenantFilteredDataOptions<TData, TError>): UseQueryResult<TData, TError> {
    const { getFilteredTenantIds, getFilterQuery } = useTenantFilterSelectors();

    // ✅ OPTIMIZACIÓN: Memoizar los tenant IDs para evitar re-renders innecesarios
    const tenantIds = useMemo(() => {
        return includeTenantFilter ? getFilteredTenantIds() : undefined;
    }, [getFilterQuery(), includeTenantFilter]);

    // ✅ OPTIMIZACIÓN: Memoizar query key incluyendo el filtro de tenant
    // Esto permite que React Query cache correctamente por filtro
    const fullQueryKey = useMemo(() => {
        if (!includeTenantFilter) {
            return queryKey;
        }

        // Añadir filtro al query key para cache separation
        const filterKey = tenantIds
            ? `tenants:${tenantIds.join(',')}`
            : 'tenants:all';

        return [...queryKey, filterKey];
    }, [queryKey, tenantIds, includeTenantFilter]);

    // ✅ OPTIMIZACIÓN: Memoizar queryFn con los tenant IDs actuales
    const memoizedQueryFn = useMemo(() => {
        return () => queryFn(tenantIds);
    }, [queryFn, tenantIds]);

    // ✅ React Query maneja:
    // - Cache automático
    // - Retry on error
    // - Refetch on window focus
    // - Stale data management
    // - Loading states
    return useQuery<TData, TError>({
        queryKey: fullQueryKey,
        queryFn: memoizedQueryFn,
        ...options,
    });
}

/**
 * Tipo de retorno para mejor autocomplete
 */
export type TenantFilteredQueryResult<TData, TError = Error> = UseQueryResult<TData, TError>;
