import * as React from "react";
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";
import { Button } from "./button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "./select";

export interface TablePaginationProps {
  // Current page (1-based)
  currentPage: number;

  // Total number of pages
  totalPages: number;

  // Total number of items (optional, for display)
  totalItems?: number;

  // Page size (items per page)
  pageSize: number;

  // Available page sizes
  pageSizeOptions?: number[];

  // Callbacks
  onPageChange: (page: number) => void;
  onPageSizeChange?: (pageSize: number) => void;

  // Server-side or client-side mode
  mode?: "client" | "server";

  // Optional: show page size selector
  showPageSizeSelector?: boolean;

  // Optional: show page info text
  showPageInfo?: boolean;

  // Optional: custom labels
  labels?: {
    pageInfo?: string; // e.g., "Página {page} de {total}"
    itemsInfo?: string; // e.g., "Mostrando {from}-{to} de {total} elementos"
    rowsPerPage?: string; // e.g., "Filas por página:"
  };
}

export function TablePagination({
  currentPage,
  totalPages,
  totalItems,
  pageSize,
  pageSizeOptions = [10, 20, 50, 100],
  onPageChange,
  onPageSizeChange,
  mode = "client",
  showPageSizeSelector = true,
  showPageInfo = true,
  labels = {},
}: TablePaginationProps) {
  const {
    pageInfo = `Página ${currentPage} de ${totalPages}`,
    itemsInfo,
    rowsPerPage = "Filas por página:",
  } = labels;

  // Calculate item range for display
  const getItemRange = () => {
    if (!totalItems) return null;
    const from = (currentPage - 1) * pageSize + 1;
    const to = Math.min(currentPage * pageSize, totalItems);
    return { from, to };
  };

  const itemRange = getItemRange();

  // Generate page numbers to show
  const getPageNumbers = () => {
    const pages: (number | "ellipsis")[] = [];
    const maxPagesToShow = 7;

    if (totalPages <= maxPagesToShow) {
      // Show all pages if total is small
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      // Always show first page
      pages.push(1);

      if (currentPage > 3) {
        pages.push("ellipsis");
      }

      // Show pages around current
      const start = Math.max(2, currentPage - 1);
      const end = Math.min(totalPages - 1, currentPage + 1);

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      if (currentPage < totalPages - 2) {
        pages.push("ellipsis");
      }

      // Always show last page
      pages.push(totalPages);
    }

    return pages;
  };

  const pageNumbers = getPageNumbers();

  const handlePageChange = (page: number) => {
    if (page >= 1 && page <= totalPages && page !== currentPage) {
      onPageChange(page);
    }
  };

  const handlePageSizeChange = (value: string) => {
    const newSize = parseInt(value);
    if (onPageSizeChange) {
      onPageSizeChange(newSize);
    }
  };

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 px-2 py-4">
      {/* Left side: Page size selector */}
      <div className="flex items-center gap-2">
        {showPageSizeSelector && onPageSizeChange && (
          <>
            <span className="text-sm text-[#64748B]">{rowsPerPage}</span>
            <Select value={pageSize.toString()} onValueChange={handlePageSizeChange}>
              <SelectTrigger className="h-8 w-[70px]">
                <SelectValue placeholder={pageSize} />
              </SelectTrigger>
              <SelectContent side="top">
                {pageSizeOptions.map((size) => (
                  <SelectItem key={size} value={size.toString()}>
                    {size}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </>
        )}
      </div>

      {/* Center: Page info */}
      {showPageInfo && (
        <div className="text-sm text-[#64748B]">
          {itemRange && itemsInfo ? (
            <span>
              Mostrando {itemRange.from}-{itemRange.to} de {totalItems} elementos
            </span>
          ) : totalItems ? (
            <span>Total: {totalItems} elementos</span>
          ) : (
            <span>{pageInfo}</span>
          )}
        </div>
      )}

      {/* Right side: Pagination controls */}
      <div className="flex items-center gap-1">
        {/* First page */}
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => handlePageChange(1)}
          disabled={currentPage === 1}
        >
          <ChevronsLeft className="h-4 w-4" />
        </Button>

        {/* Previous page */}
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => handlePageChange(currentPage - 1)}
          disabled={currentPage === 1}
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>

        {/* Page numbers */}
        <div className="hidden sm:flex items-center gap-1">
          {pageNumbers.map((page, index) =>
            page === "ellipsis" ? (
              <span key={`ellipsis-${index}`} className="px-2 text-[#64748B]">
                ...
              </span>
            ) : (
              <Button
                key={page}
                type="button"
                variant={currentPage === page ? "default" : "outline"}
                size="icon"
                className="h-8 w-8"
                onClick={() => handlePageChange(page)}
              >
                {page}
              </Button>
            )
          )}
        </div>

        {/* Mobile: show current page only */}
        <div className="sm:hidden px-2">
          <span className="text-sm">
            {currentPage}/{totalPages}
          </span>
        </div>

        {/* Next page */}
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => handlePageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
        >
          <ChevronRight className="h-4 w-4" />
        </Button>

        {/* Last page */}
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8"
          onClick={() => handlePageChange(totalPages)}
          disabled={currentPage === totalPages}
        >
          <ChevronsRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
