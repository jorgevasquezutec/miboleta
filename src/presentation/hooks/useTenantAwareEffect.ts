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
    // Se suscribe SOLO al string de tenantIds (primitivo), que es estable bajo
    // la comparación Object.is de zustand y dispara el efecto al cambiar.
    //
    // Se copia antes de ordenar: .sort() ordena IN-PLACE, así que sin el spread
    // este selector mutaba state.filter.tenantIds del store en cada render —
    // fuera de un set(), y durante el render (los selectores deben ser puros).
    const tenantIdsKey = useTenantFilterStore(
        state => [...state.filter.tenantIds].sort().join(',')
    );

    useEffect(() => {
        return callback();
        // El spread es inherente al diseño: este hook envuelve useEffect con
        // dependencias dinámicas, así que ESLint no puede verificarlas
        // estáticamente (ni ver `callback`). Quien llama declara sus deps
        // exactamente igual que en un useEffect, y ahí sí las valida la regla.
        // tenantIdsKey es un string (primitivo): dispara el efecto al cambiar de
        // empresa sin arrastrar la identidad del array del store.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [...deps, tenantIdsKey]);
}
