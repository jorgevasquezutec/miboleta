import { useEffect, useState } from 'react';
import { useDebounce } from './useDebounce';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';

/**
 * Hook personalizado para búsqueda de tenants con debounce
 * Maneja el estado de búsqueda y carga automática de resultados
 *
 * @param initialQuery - Query inicial de búsqueda (opcional)
 * @param debounceMs - Tiempo de debounce en ms (default: 300ms)
 *
 * @returns Objeto con estado y funciones de búsqueda
 *
 * @example
 * ```tsx
 * const { searchQuery, setSearchQuery, results, isSearching, hasMore, loadMore } = useTenantSearch();
 *
 * <Input
 *   value={searchQuery}
 *   onChange={(e) => setSearchQuery(e.target.value)}
 * />
 * ```
 */
export function useTenantSearch(initialQuery: string = '', debounceMs: number = 300) {
  const [searchQuery, setSearchQuery] = useState(initialQuery);
  const debouncedQuery = useDebounce(searchQuery, debounceMs);

  const {
    searchResults,
    isSearching,
    searchPagination,
    searchTenants,
    loadMoreSearchResults,
    clearSearchResults,
  } = useTenantsStore();

  // Buscar cuando cambia el query con debounce
  useEffect(() => {
    if (debouncedQuery.trim()) {
      searchTenants(debouncedQuery);
    } else {
      clearSearchResults();
    }
  }, [debouncedQuery, searchTenants, clearSearchResults]);

  // Limpiar resultados al desmontar
  useEffect(() => {
    return () => {
      clearSearchResults();
    };
  }, [clearSearchResults]);

  const hasMore =
    searchPagination !== null &&
    searchPagination.current_page < searchPagination.last_page;

  return {
    // Estado
    searchQuery,
    results: searchResults,
    isSearching,
    hasMore,
    pagination: searchPagination,

    // Acciones
    setSearchQuery,
    loadMore: loadMoreSearchResults,
    clear: clearSearchResults,
  };
}
