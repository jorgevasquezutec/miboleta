import { useState, useCallback } from 'react';

export interface PaginationState {
    currentPage: number;
    perPage: number;
    total: number;
    totalPages: number;
}

export interface UsePaginationOptions {
    initialPage?: number;
    initialPerPage?: number;
    onPageChange?: (page: number) => void;
}

export interface UsePaginationReturn extends PaginationState {
    setPage: (page: number) => void;
    nextPage: () => void;
    previousPage: () => void;
    setPerPage: (perPage: number) => void;
    setTotal: (total: number) => void;
    setTotalPages: (totalPages: number) => void;
    resetPagination: () => void;
    canGoNext: boolean;
    canGoPrevious: boolean;
}

export function usePagination(options: UsePaginationOptions = {}): UsePaginationReturn {
    const { initialPage = 1, initialPerPage = 20, onPageChange } = options;

    const [currentPage, setCurrentPage] = useState(initialPage);
    const [perPage, setPerPageState] = useState(initialPerPage);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);

    const setPage = useCallback(
        (page: number) => {
            if (page < 1 || page > totalPages) return;
            setCurrentPage(page);
            onPageChange?.(page);
        },
        [totalPages, onPageChange]
    );

    const nextPage = useCallback(() => {
        if (currentPage < totalPages) {
            setPage(currentPage + 1);
        }
    }, [currentPage, totalPages, setPage]);

    const previousPage = useCallback(() => {
        if (currentPage > 1) {
            setPage(currentPage - 1);
        }
    }, [currentPage, setPage]);

    const setPerPage = useCallback((newPerPage: number) => {
        setPerPageState(newPerPage);
        setCurrentPage(1); // Reset to first page when changing page size
    }, []);

    const resetPagination = useCallback(() => {
        setCurrentPage(initialPage);
        setPerPageState(initialPerPage);
        setTotal(0);
        setTotalPages(1);
    }, [initialPage, initialPerPage]);

    const canGoNext = currentPage < totalPages;
    const canGoPrevious = currentPage > 1;

    return {
        currentPage,
        perPage,
        total,
        totalPages,
        setPage,
        nextPage,
        previousPage,
        setPerPage,
        setTotal,
        setTotalPages,
        resetPagination,
        canGoNext,
        canGoPrevious,
    };
}
