import { useState, useMemo } from "react";

export interface UseTablePaginationProps<T> {
  data: T[];
  initialPageSize?: number;
  mode?: "client" | "server";
  // For server-side pagination
  serverTotalItems?: number;
  serverTotalPages?: number;
  onServerPageChange?: (page: number, pageSize: number) => void;
}

export interface UseTablePaginationReturn<T> {
  // Current page data (for client-side)
  paginatedData: T[];

  // Pagination state
  currentPage: number;
  pageSize: number;
  totalPages: number;
  totalItems: number;

  // Actions
  setCurrentPage: (page: number) => void;
  setPageSize: (size: number) => void;
  goToFirstPage: () => void;
  goToLastPage: () => void;
  goToNextPage: () => void;
  goToPreviousPage: () => void;

  // Helpers
  canGoToNextPage: boolean;
  canGoToPreviousPage: boolean;
}

export function useTablePagination<T>({
  data,
  initialPageSize = 10,
  mode = "client",
  serverTotalItems,
  serverTotalPages,
  onServerPageChange,
}: UseTablePaginationProps<T>): UseTablePaginationReturn<T> {
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(initialPageSize);

  // Calculate values based on mode
  const totalItems = mode === "server" ? (serverTotalItems ?? 0) : data.length;
  const totalPages = mode === "server"
    ? (serverTotalPages ?? Math.ceil(totalItems / pageSize))
    : Math.ceil(data.length / pageSize);

  // For client-side: slice data
  const paginatedData = useMemo(() => {
    if (mode === "server") {
      // In server mode, data is already paginated
      return data;
    }

    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    return data.slice(startIndex, endIndex);
  }, [data, currentPage, pageSize, mode]);

  // Page change handlers
  const handleSetCurrentPage = (page: number) => {
    const newPage = Math.max(1, Math.min(page, totalPages));
    setCurrentPage(newPage);

    if (mode === "server" && onServerPageChange) {
      onServerPageChange(newPage, pageSize);
    }
  };

  const handleSetPageSize = (size: number) => {
    setPageSize(size);
    setCurrentPage(1); // Reset to first page when changing page size

    if (mode === "server" && onServerPageChange) {
      onServerPageChange(1, size);
    }
  };

  const goToFirstPage = () => handleSetCurrentPage(1);
  const goToLastPage = () => handleSetCurrentPage(totalPages);
  const goToNextPage = () => handleSetCurrentPage(currentPage + 1);
  const goToPreviousPage = () => handleSetCurrentPage(currentPage - 1);

  const canGoToNextPage = currentPage < totalPages;
  const canGoToPreviousPage = currentPage > 1;

  return {
    paginatedData,
    currentPage,
    pageSize,
    totalPages,
    totalItems,
    setCurrentPage: handleSetCurrentPage,
    setPageSize: handleSetPageSize,
    goToFirstPage,
    goToLastPage,
    goToNextPage,
    goToPreviousPage,
    canGoToNextPage,
    canGoToPreviousPage,
  };
}
