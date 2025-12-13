import { useState, useCallback } from 'react';

export interface TableFilters {
    [key: string]: any;
}

export interface UseTableFiltersOptions<T extends TableFilters> {
    initialFilters?: Partial<T>;
    onFiltersChange?: (filters: T) => void;
}

export interface UseTableFiltersReturn<T extends TableFilters> {
    filters: T;
    setFilter: <K extends keyof T>(key: K, value: T[K]) => void;
    setFilters: (newFilters: Partial<T>) => void;
    resetFilters: () => void;
    clearFilter: <K extends keyof T>(key: K) => void;
}

export function useTableFilters<T extends TableFilters>(
    options: UseTableFiltersOptions<T> = {}
): UseTableFiltersReturn<T> {
    const { initialFilters = {} as T, onFiltersChange } = options;

    const [filters, setFiltersState] = useState<T>(initialFilters as T);

    const setFilter = useCallback(
        <K extends keyof T>(key: K, value: T[K]) => {
            const newFilters = { ...filters, [key]: value };
            setFiltersState(newFilters);
            onFiltersChange?.(newFilters);
        },
        [filters, onFiltersChange]
    );

    const setFilters = useCallback(
        (newFilters: Partial<T>) => {
            const updatedFilters = { ...filters, ...newFilters };
            setFiltersState(updatedFilters);
            onFiltersChange?.(updatedFilters);
        },
        [filters, onFiltersChange]
    );

    const resetFilters = useCallback(() => {
        setFiltersState(initialFilters as T);
        onFiltersChange?.(initialFilters as T);
    }, [initialFilters, onFiltersChange]);

    const clearFilter = useCallback(
        <K extends keyof T>(key: K) => {
            const newFilters = { ...filters };
            delete newFilters[key];
            setFiltersState(newFilters);
            onFiltersChange?.(newFilters);
        },
        [filters, onFiltersChange]
    );

    return {
        filters,
        setFilter,
        setFilters,
        resetFilters,
        clearFilter,
    };
}
