import { useState, useEffect, useRef, useMemo } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import { ChevronLeft, ChevronRight, ZoomIn, ZoomOut, Loader2, PanelLeftClose, PanelLeft } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { cn } from '@/presentation/components/ui/utils';

// Configure PDF.js worker using local bundled file instead of CDN
pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url,
).toString();

interface PDFViewerProps {
    url: string;
}

export function PDFViewer({ url }: PDFViewerProps) {
    const [numPages, setNumPages] = useState<number>(0);
    const [pageNumber, setPageNumber] = useState(1);
    // Default to 50% zoom on mobile (< 640px), 100% on desktop
    const [scale, setScale] = useState(() => {
        if (typeof window !== 'undefined') {
            return window.innerWidth < 640 ? 0.5 : 1.0;
        }
        return 1.0;
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [pdfBytes, setPdfBytes] = useState<Uint8Array | null>(null);
    // Default to hidden on mobile (< 640px)
    const [showThumbnails, setShowThumbnails] = useState(() => {
        if (typeof window !== 'undefined') {
            return window.innerWidth >= 640;
        }
        return true;
    });
    const thumbnailsRef = useRef<HTMLDivElement>(null);

    // Track previous width to detect mobile <-> desktop transitions
    const prevWidthRef = useRef<number>(typeof window !== 'undefined' ? window.innerWidth : 640);

    // Listen for window resize to adjust scale and thumbnails
    useEffect(() => {
        const handleResize = () => {
            const currentWidth = window.innerWidth;
            const prevWidth = prevWidthRef.current;
            const BREAKPOINT = 640;

            // Transitioning from mobile to desktop
            if (prevWidth < BREAKPOINT && currentWidth >= BREAKPOINT) {
                setScale(1.0);
                setShowThumbnails(true);
            }
            // Transitioning from desktop to mobile
            else if (prevWidth >= BREAKPOINT && currentWidth < BREAKPOINT) {
                setScale(0.5);
                setShowThumbnails(false);
            }

            prevWidthRef.current = currentWidth;
        };

        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    useEffect(() => {
        // Reset state when URL changes to avoid showing stale data
        setPdfBytes(null);
        setNumPages(0);
        setPageNumber(1);
        setError(null);
        setLoading(true);

        const controller = new AbortController();

        const fetchPdf = async () => {
            try {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/pdf',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        throw new Error('No autorizado. Por favor, inicia sesión nuevamente.');
                    }
                    throw new Error('Error al cargar el documento');
                }

                const arrayBuffer = await response.arrayBuffer();
                const bytes = new Uint8Array(arrayBuffer.slice(0));
                setPdfBytes(bytes);
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') return;
                setError(err instanceof Error ? err.message : 'Error desconocido');
            } finally {
                setLoading(false);
            }
        };

        if (url) {
            fetchPdf();
        }

        return () => controller.abort();
    }, [url]);

    // Memoize file data to prevent unnecessary Document reloads
    const fileData = useMemo(
        () => (pdfBytes ? { data: pdfBytes } : null),
        [pdfBytes]
    );

    function onDocumentLoadSuccess({ numPages }: { numPages: number }) {
        setNumPages(numPages);
        setLoading(false);
    }

    function onDocumentLoadError(err: Error) {
        console.error('PDF Load Error:', err);
        setError(err.message);
        setLoading(false);
    }

    const goToPrevPage = () => {
        setPageNumber((prev) => Math.max(prev - 1, 1));
    };

    const goToNextPage = () => {
        setPageNumber((prev) => Math.min(prev + 1, numPages));
    };

    const goToPage = (page: number) => {
        setPageNumber(page);
    };

    const zoomIn = () => {
        setScale((prev) => Math.min(prev + 0.25, 2.5));
    };

    const zoomOut = () => {
        setScale((prev) => Math.max(prev - 0.25, 0.5));
    };

    // Scroll thumbnail into view when page changes
    useEffect(() => {
        if (thumbnailsRef.current && showThumbnails) {
            const thumbnail = thumbnailsRef.current.querySelector(`[data-page="${pageNumber}"]`);
            if (thumbnail) {
                thumbnail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }, [pageNumber, showThumbnails]);

    // Lazy-load thumbnails: only render those near the viewport
    const [visibleThumbnails, setVisibleThumbnails] = useState<Set<number>>(new Set());

    useEffect(() => {
        if (!showThumbnails || numPages === 0 || !thumbnailsRef.current) return;

        const observer = new IntersectionObserver(
            (entries) => {
                setVisibleThumbnails((prev) => {
                    const next = new Set(prev);
                    for (const entry of entries) {
                        const page = Number((entry.target as HTMLElement).dataset.page);
                        if (entry.isIntersecting) {
                            next.add(page);
                        }
                    }
                    return next;
                });
            },
            { root: thumbnailsRef.current, rootMargin: '200px 0px' }
        );

        const buttons = thumbnailsRef.current.querySelectorAll('[data-page]');
        buttons.forEach((btn) => observer.observe(btn));

        return () => observer.disconnect();
    }, [showThumbnails, numPages]);

    if (loading && !pdfBytes) {
        return (
            <div className="flex items-center justify-center h-96 bg-gray-100 rounded-lg">
                <div className="text-center">
                    <Loader2 className="w-8 h-8 animate-spin text-[#2563EB] mx-auto mb-2" />
                    <p className="text-[#64748B]">Cargando documento...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center h-96 bg-gray-100 rounded-lg">
                <div className="text-center">
                    <p className="text-red-500 mb-2">Error al cargar el PDF</p>
                    <p className="text-[#64748B] text-sm">{error}</p>
                </div>
            </div>
        );
    }

    const pageNumbers = Array.from({ length: numPages }, (_, i) => i + 1);

    return (
        <div className="flex flex-col h-full">
            {/* Controls */}
            <div className="flex items-center justify-between p-2 sm:p-3 bg-gray-100 border-b gap-1 sm:gap-2">
                <div className="flex items-center gap-1 sm:gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowThumbnails(!showThumbnails)}
                        title={showThumbnails ? "Ocultar miniaturas" : "Mostrar miniaturas"}
                        className="h-8 w-8 sm:h-9 sm:w-9 p-0"
                    >
                        {showThumbnails ? (
                            <PanelLeftClose className="w-4 h-4" />
                        ) : (
                            <PanelLeft className="w-4 h-4" />
                        )}
                    </Button>
                    <div className="w-px h-5 sm:h-6 bg-gray-300 mx-0.5 sm:mx-1 hidden xs:block" />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={goToPrevPage}
                        disabled={pageNumber <= 1}
                        className="h-8 w-8 sm:h-9 sm:w-9 p-0"
                    >
                        <ChevronLeft className="w-4 h-4" />
                    </Button>
                    <span className="text-xs sm:text-sm text-[#64748B] min-w-[70px] sm:min-w-[100px] text-center">
                        <span className="hidden xs:inline">Página </span>{pageNumber} de {numPages}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={goToNextPage}
                        disabled={pageNumber >= numPages}
                        className="h-8 w-8 sm:h-9 sm:w-9 p-0"
                    >
                        <ChevronRight className="w-4 h-4" />
                    </Button>
                </div>
                <div className="flex items-center gap-1 sm:gap-2">
                    <Button variant="outline" size="sm" onClick={zoomOut} className="h-8 w-8 sm:h-9 sm:w-9 p-0">
                        <ZoomOut className="w-4 h-4" />
                    </Button>
                    <span className="text-xs sm:text-sm text-[#64748B] min-w-[40px] sm:min-w-[60px] text-center">
                        {Math.round(scale * 100)}%
                    </span>
                    <Button variant="outline" size="sm" onClick={zoomIn} className="h-8 w-8 sm:h-9 sm:w-9 p-0">
                        <ZoomIn className="w-4 h-4" />
                    </Button>
                </div>
            </div>

            {/* Main content area */}
            <div className="flex-1 flex overflow-hidden">
                {fileData && (
                    <Document
                        file={fileData}
                        onLoadSuccess={onDocumentLoadSuccess}
                        onLoadError={onDocumentLoadError}
                        loading={
                            <div className="flex-1 flex items-center justify-center">
                                <Loader2 className="w-8 h-8 animate-spin text-[#2563EB]" />
                            </div>
                        }
                        className="flex flex-1"
                    >
                        {/* Thumbnails sidebar */}
                        {showThumbnails && numPages > 0 && (
                            <div
                                ref={thumbnailsRef}
                                className="w-28 bg-gray-100 border-r overflow-y-auto p-2 space-y-2 flex-shrink-0"
                            >
                                {pageNumbers.map((page) => (
                                    <button
                                        key={page}
                                        data-page={page}
                                        onClick={() => goToPage(page)}
                                        className={cn(
                                            "w-full p-1.5 rounded transition-all",
                                            pageNumber === page
                                                ? "ring-2 ring-[#2563EB] bg-blue-50"
                                                : "hover:bg-gray-200"
                                        )}
                                    >
                                        {visibleThumbnails.has(page) ? (
                                            <Page
                                                pageNumber={page}
                                                width={80}
                                                renderTextLayer={false}
                                                renderAnnotationLayer={false}
                                                className="shadow-sm rounded overflow-hidden"
                                                loading={
                                                    <div className="h-24 w-full bg-gray-200 animate-pulse rounded" />
                                                }
                                            />
                                        ) : (
                                            <div className="h-24 w-full bg-gray-200 rounded" />
                                        )}
                                        <p className={cn(
                                            "text-xs mt-1 text-center",
                                            pageNumber === page ? "text-[#2563EB] font-medium" : "text-[#64748B]"
                                        )}>
                                            {page}
                                        </p>
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Main PDF view - allows horizontal scroll on mobile */}
                        <div className="flex-1 overflow-x-auto overflow-y-auto bg-gray-200 p-2 sm:p-4">
                            <div className="min-w-fit flex justify-center">
                                <Page
                                    pageNumber={pageNumber}
                                    scale={scale}
                                    renderTextLayer={false}
                                    renderAnnotationLayer={false}
                                    className="shadow-lg"
                                />
                            </div>
                        </div>
                    </Document>
                )}
            </div>
        </div>
    );
}

export default PDFViewer;
