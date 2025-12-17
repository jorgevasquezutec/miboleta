import { useEffect, DependencyList } from 'react';
import { useTenantFilterStore } from '@/presentation/stores';

/**
 * Hook personalizado que envuelve useEffect añadiendo automáticamente
 * el filtro de tenant a las dependencias.
 * 
 * Cuando el usuario cambia las empresas seleccionadas en el TenantMultiSwitcher,
 * este hook dispara automáticamente el callback.
 * 
 * @example
 * ```typescript
 * // Antes:
 * useEffect(() => {
 *   fetchDocuments(filters);
 * }, [filters.page, filters.status]);
 * 
 * // Después:
 * useTenantAwareEffect(() => {
 *   fetchDocuments(filters);
 * }, [filters.page, filters.status]);
 * // ✅ Ahora reacciona automáticamente al cambio de tenant
 * ```
 * 
 * @param callback - Función a ejecutar cuando cambien las dependencias o el tenant filter
 * @param deps - Array de dependencias (igual que useEffect)
 */
export function useTenantAwareEffect(
    callback: () => void | (() => void),
    deps: DependencyList = []
): void {
    // ✅ Subscribe ONLY to the tenantIds string (primitive value)
    // This ensures re-render when it changes
    const tenantIdsKey = useTenantFilterStore(
        state => state.filter.tenantIds.sort().join(',')  // Sort for stable comparison
    );

    useEffect(() => {
        return callback();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        ...deps,
        tenantIdsKey,  // ✅ Primitive string - guaranteed to trigger re-render
    ]);
}
