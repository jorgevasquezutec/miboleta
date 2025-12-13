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
        <div className={`flex items-center justify-between gap-4 ${className}`}>
            {/* Left side: Info text */}
            <div className="flex items-center gap-4">
                {showInfo && (
                    <div className="text-sm text-[#64748B]">
                        Mostrando página {currentPage} de {totalPages || 1} ({total || 0} registros total)
                    </div>
                )}
            </div>

            {/* Right side: Per page selector + Pagination */}
            <div className="flex items-center gap-4">
                {showPerPageSelector && onPerPageChange && (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-[#64748B]">Mostrar</span>
                        <Select
                            value={perPage.toString()}
                            onValueChange={handlePerPageChange}
                            disabled={disabled}
                        >
                            <SelectTrigger className="w-[70px] h-8">
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
                        <span className="text-sm text-[#64748B]">por página</span>
                    </div>
                )}

                <Pagination className="mx-0 w-auto justify-end">
                    <PaginationContent>
                        <PaginationItem>
                            <PaginationPrevious
                                onClick={(e) => {
                                    e.preventDefault();
                                    if (canGoPrevious && !disabled) {
                                        onPageChange(currentPage - 1);
                                    }
                                }}
                                className={
                                    !canGoPrevious || disabled
                                        ? 'pointer-events-none opacity-50'
                                        : 'cursor-pointer'
                                }
                            />
                        </PaginationItem>

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
                                        className={
                                            disabled
                                                ? 'pointer-events-none opacity-50'
                                                : 'cursor-pointer'
                                        }
                                    >
                                        {page}
                                    </PaginationLink>
                                </PaginationItem>
                            )
                        )}

                        <PaginationItem>
                            <PaginationNext
                                onClick={(e) => {
                                    e.preventDefault();
                                    if (canGoNext && !disabled) {
                                        onPageChange(currentPage + 1);
                                    }
                                }}
                                className={
                                    !canGoNext || disabled
                                        ? 'pointer-events-none opacity-50'
                                        : 'cursor-pointer'
                                }
                            />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
            </div>
        </div>
    );
}
