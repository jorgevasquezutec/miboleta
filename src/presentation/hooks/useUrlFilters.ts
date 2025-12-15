import { useSearchParams } from 'react-router-dom';
import { useCallback, useMemo } from 'react';

/**
 * Hook para sincronizar filtros con los query params de la URL.
 * Permite persistencia de filtros al navegar y URLs compartibles.
 * 
 * @example
 * const { filters, setFilter, setFilters, resetFilters } = useUrlFilters({
 *   defaultValues: { search: '', status: 'all', page: 1 }
 * });
 */

interface UseUrlFiltersOptions<T extends Record<string, unknown>> {
    /** Valores por defecto para los filtros */
    defaultValues: T;
    /** Parser personalizado para valores específicos */
    parseValue?: (key: keyof T, value: string) => T[keyof T];
    /** Serializador personalizado para valores específicos */
    serializeValue?: (key: keyof T, value: T[keyof T]) => string;
}

interface UseUrlFiltersReturn<T extends Record<string, unknown>> {
    /** Valores actuales de los filtros */
    filters: T;
    /** Actualizar un solo filtro */
    setFilter: <K extends keyof T>(key: K, value: T[K]) => void;
    /** Actualizar múltiples filtros a la vez */
    setFilters: (updates: Partial<T>) => void;
    /** Resetear todos los filtros a sus valores por defecto */
    resetFilters: () => void;
}

export function useUrlFilters<T extends Record<string, unknown>>({
    defaultValues,
    parseValue,
    serializeValue,
}: UseUrlFiltersOptions<T>): UseUrlFiltersReturn<T> {
    const [searchParams, setSearchParams] = useSearchParams();

    // Parse URL params into filter values
    const filters = useMemo(() => {
        const result = { ...defaultValues };

        for (const key of Object.keys(defaultValues) as (keyof T)[]) {
            const paramValue = searchParams.get(String(key));
            if (paramValue !== null) {
                if (parseValue) {
                    try {
                        result[key] = parseValue(key, paramValue);
                    } catch {
                        // Keep default value if parsing fails
                    }
                } else {
                    // Default parsing based on type of default value
                    const defaultValue = defaultValues[key];
                    if (typeof defaultValue === 'number') {
                        const parsed = parseInt(paramValue, 10);
                        if (!isNaN(parsed)) {
                            result[key] = parsed as T[keyof T];
                        }
                    } else if (typeof defaultValue === 'boolean') {
                        result[key] = (paramValue === 'true') as T[keyof T];
                    } else {
                        result[key] = paramValue as T[keyof T];
                    }
                }
            }
        }

        return result;
    }, [searchParams, defaultValues, parseValue]);

    // Update multiple filters at once
    const setFilters = useCallback(
        (updates: Partial<T>) => {
            setSearchParams(
                (prev) => {
                    const newParams = new URLSearchParams(prev);

                    for (const [key, value] of Object.entries(updates)) {
                        const defaultValue = defaultValues[key as keyof T];

                        // Remove param if value is empty, null, undefined, or equals default
                        if (
                            value === undefined ||
                            value === null ||
                            value === '' ||
                            value === defaultValue
                        ) {
                            newParams.delete(key);
                        } else {
                            const serialized = serializeValue
                                ? serializeValue(key as keyof T, value as T[keyof T])
                                : String(value);
                            newParams.set(key, serialized);
                        }
                    }

                    return newParams;
                },
                { replace: true }
            );
        },
        [setSearchParams, defaultValues, serializeValue]
    );

    // Update a single filter
    const setFilter = useCallback(
        <K extends keyof T>(key: K, value: T[K]) => {
            setFilters({ [key]: value } as unknown as Partial<T>);
        },
        [setFilters]
    );

    // Reset all filters to defaults (clear URL params)
    const resetFilters = useCallback(() => {
        setSearchParams({}, { replace: true });
    }, [setSearchParams]);

    return { filters, setFilter, setFilters, resetFilters };
}
