import { useEffect, useState } from 'react';

/**
 * Hook para aplicar debounce a un valor
 * Útil para búsquedas y prevenir llamadas excesivas a la API
 *
 * @param value - Valor a aplicar debounce
 * @param delay - Tiempo de espera en milisegundos (default: 300ms)
 * @returns Valor con debounce aplicado
 *
 * @example
 * ```tsx
 * const [search, setSearch] = useState('');
 * const debouncedSearch = useDebounce(search, 500);
 *
 * useEffect(() => {
 *   if (debouncedSearch) {
 *     searchAPI(debouncedSearch);
 *   }
 * }, [debouncedSearch]);
 * ```
 */
export function useDebounce<T>(value: T, delay: number = 300): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

  useEffect(() => {
    // Set up the timeout
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    // Clean up the timeout if value changes before delay
    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}
