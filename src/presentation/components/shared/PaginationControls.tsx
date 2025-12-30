import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
    PaginationEllipsis,
} from '@/presentation/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/presentation/components/ui/select';

export interface PaginationControlsProps {
    currentPage: number;
    totalPages: number;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    disabled?: boolean;
    showInfo?: boolean;
    showPerPageSelector?: boolean;
    className?: string;
    maxPages?: number;
    perPageOptions?: number[];
}

export function PaginationControls({
    currentPage,
    totalPages,
    total,
    perPage,
    onPageChange,
    onPerPageChange,
    disabled = false,
    showInfo = true,
    showPerPageSelector = true,
    className = '',
    maxPages = 7,
    perPageOptions = [5, 10, 20, 50],
}: PaginationControlsProps) {
    const canGoPrevious = currentPage > 1;
    const canGoNext = currentPage < totalPages;

    // Generate page numbers to display
    const getPageNumbers = () => {
        const pages: (number | 'ellipsis')[] = [];

        if (totalPages <= maxPages) {
            // Show all pages if total is less than max
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else {
            // Always show first page
            pages.push(1);

            const leftSide = currentPage - 2;
            const rightSide = currentPage + 2;

            if (leftSide > 2) {
                pages.push('ellipsis');
            }

            for (let i = Math.max(2, leftSide); i <= Math.min(totalPages - 1, rightSide); i++) {
                pages.push(i);
            }

            if (rightSide < totalPages - 1) {
                pages.push('ellipsis');
            }

            // Always show last page
            if (totalPages > 1) {
                pages.push(totalPages);
            }
        }

        return pages;
    };

    const pageNumbers = getPageNumbers();

    const handlePerPageChange = (value: string) => {
        const newPerPage = parseInt(value, 10);
        if (onPerPageChange) {
            onPerPageChange(newPerPage);
        }
    };

    return (
        <div className={`flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 ${className}`}>
            {/* Left side: Info text */}
            {showInfo && (
                <div className="text-xs sm:text-sm text-[#64748B] text-center sm:text-left">
                    Página {currentPage} de {totalPages || 1} ({total || 0} registros)
                </div>
            )}

            {/* Right side: Per page selector + Pagination */}
            <div className="flex flex-wrap items-center justify-center sm:justify-end gap-2 sm:gap-4">
                {showPerPageSelector && onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <span className="text-xs sm:text-sm text-[#64748B] hidden xs:inline">Mostrar</span>
                        <Select
                            value={perPage.toString()}
                            onValueChange={handlePerPageChange}
                            disabled={disabled}
                        >
                            <SelectTrigger className="w-[60px] sm:w-[70px] h-8">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {perPageOptions.map((option) => (
                                    <SelectItem key={option} value={option.toString()}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <span className="text-xs sm:text-sm text-[#64748B] hidden xs:inline">por pág.</span>
                    </div>
                )}

                <Pagination className="mx-0 w-auto">
                    <PaginationContent className="gap-1">
                        <PaginationItem>
                            <PaginationPrevious
                                onClick={(e) => {
                                    e.preventDefault();
                                    if (canGoPrevious && !disabled) {
                                        onPageChange(currentPage - 1);
                                    }
                                }}
                                className={`px-2 sm:px-3 ${!canGoPrevious || disabled
                                        ? 'pointer-events-none opacity-50'
                                        : 'cursor-pointer'
                                    }`}
                            />
                        </PaginationItem>

                        {/* Hide page numbers on very small screens, show only on xs and up */}
                        <div className="hidden xs:flex items-center gap-1">
                            {pageNumbers.map((page, index) =>
                                page === 'ellipsis' ? (
                                    <PaginationItem key={`ellipsis-${index}`}>
                                        <PaginationEllipsis />
                                    </PaginationItem>
                                ) : (
                                    <PaginationItem key={page}>
                                        <PaginationLink
                                            onClick={(e) => {
                                                e.preventDefault();
                                                if (!disabled && page !== currentPage) {
                                                    onPageChange(page);
                                                }
                                            }}
                                            isActive={currentPage === page}
                                            className={`h-8 w-8 ${disabled
                                                    ? 'pointer-events-none opacity-50'
                                                    : 'cursor-pointer'
                                                }`}
                                        >
                                            {page}
                                        </PaginationLink>
                                    </PaginationItem>
                                )
                            )}
                        </div>

                        {/* On very small screens, just show current/total */}
                        <div className="flex xs:hidden items-center px-2 text-sm text-[#64748B]">
                            {currentPage}/{totalPages || 1}
                        </div>

                        <PaginationItem>
                            <PaginationNext
                                onClick={(e) => {
                                    e.preventDefault();
                                    if (canGoNext && !disabled) {
                                        onPageChange(currentPage + 1);
                                    }
                                }}
                                className={`px-2 sm:px-3 ${!canGoNext || disabled
                                        ? 'pointer-events-none opacity-50'
                                        : 'cursor-pointer'
                                    }`}
                            />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
            </div>
        </div>
    );
}
