import { useState, useEffect, useCallback } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardContent } from '@/presentation/components/ui/card';
import { Plus, RefreshCw } from 'lucide-react';
import { UserBatchCard } from '@/presentation/components/bulkUpload/UserBatchCard';
import { PaginationControls } from '@/presentation/components/shared/PaginationControls';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import type { UserBatchListItem, PaginatedBatchList } from '@/domain/types/bulkUserUpload.types';
import { showApiError } from '@/presentation/utils/showApiError';

export function UserBatchesListPage() {
    useDocumentTitle('Historial de Cargas de Usuarios');
    const navigate = useNavigate();
    const [batches, setBatches] = useState<UserBatchListItem[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [totalBatches, setTotalBatches] = useState(0);
    const [perPage, setPerPage] = useState(12);
    const [statusFilter, setStatusFilter] = useState<string>('');

    // useCallback para que la identidad solo cambie con los filtros reales: así
    // el efecto puede depender de fetchBatches (lo que ESLint exige) sin
    // recrearla en cada render, que dispararía un fetch por render en bucle.
    // Las dependencias son las mismas que ya declaraba el efecto.
    const fetchBatches = useCallback(async () => {
        setIsLoading(true);
        try {
            const data: PaginatedBatchList = await bulkUserUploadService.listBatches({
                page: currentPage,
                per_page: perPage,
                status: statusFilter || undefined,
            });

            setBatches(data.data);
            setTotalPages(data.meta.last_page);
            setTotalBatches(data.meta.total);
        } catch (error) {
            console.error('Error fetching batches:', error);
            showApiError(error, 'Error al cargar el historial');
        } finally {
            setIsLoading(false);
        }
    }, [currentPage, perPage, statusFilter]);

    useEffect(() => {
        fetchBatches();
    }, [fetchBatches]);

    const handleBatchClick = (id: number) => {
        navigate(`/users/batch/${id}`);
    };

    const handleNewUpload = () => {
        navigate('/users/batch-upload');
    };

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
    };

    const handlePerPageChange = (newPerPage: number) => {
        setPerPage(newPerPage);
        setCurrentPage(1); // Reset to first page when changing per page
    };

    // Observación 5: el mensaje de "sin resultados" antes era genérico y no
    // explicaba el filtro activo. Cada chip agrupa más de un status del
    // backend (ver UserBatchController::index()): 'completed' incluye
    // 'partial', 'processing' incluye 'pending'. El caso 'failed' además
    // aclara que "fallido" es un error DURANTE el procesamiento en el
    // servidor, no errores de validación del archivo -esos se corrigen en el
    // editor antes de confirmar y, si nunca se confirma, ni siquiera generan
    // un registro aquí (ver el subtítulo del header)-.
    const emptyStateContent: Record<string, { title: string; description: string }> = {
        '': {
            title: 'No se encontraron cargas masivas',
            description: 'Todavía no hay ninguna carga masiva de usuarios confirmada.',
        },
        processing: {
            title: 'No hay cargas en proceso',
            description: 'Aquí aparecerían las cargas recién confirmadas, mientras sus chunks todavía se están procesando en segundo plano.',
        },
        completed: {
            title: 'No hay cargas completadas',
            description: 'Incluye tanto cargas totalmente exitosas como parciales (con algunas filas fallidas). Ninguna carga terminó así todavía.',
        },
        failed: {
            title: 'No hay cargas fallidas',
            description: '"Fallido" es un error durante el procesamiento en el servidor, no errores de validación del archivo (esos se corrigen en el editor antes de confirmar la carga).',
        },
    };
    const currentEmptyState = emptyStateContent[statusFilter] ?? emptyStateContent[''];

    return (
        <div className="space-y-4 sm:space-y-6 overflow-x-hidden">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold">Historial de Cargas Masivas</h1>
                    <p className="text-gray-600 mt-1 text-sm sm:text-base">
                        Revisa el historial de todas las cargas masivas de usuarios
                    </p>
                    <p className="text-gray-500 mt-1 text-xs sm:text-sm">
                        Solo se listan aquí las cargas que llegaste a confirmar. Un archivo
                        validado con errores que corregiste en el editor pero nunca confirmaste
                        no genera registro. "Completados" incluye cargas parciales (con algunas
                        filas fallidas).
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={fetchBatches} size="icon" className="h-9 w-9 sm:h-10 sm:w-10">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                    <Button onClick={handleNewUpload} className="h-9 sm:h-10 px-3 sm:px-4">
                        <Plus className="h-4 w-4 mr-2" />
                        Nueva Carga
                    </Button>
                </div>
            </div>

            {/* Filters - scrollable on mobile, wraps on larger screens */}
            <Card>
                <CardContent className="p-3 sm:p-4">
                    <div className="flex gap-2 overflow-x-auto sm:overflow-x-visible sm:flex-wrap pb-3 sm:pb-1 -mb-2 sm:-mb-1 scrollbar-thin">
                        <Button
                            variant={statusFilter === '' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('')}
                            size="sm"
                            className="flex-shrink-0"
                        >
                            Todos
                        </Button>
                        <Button
                            variant={statusFilter === 'processing' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('processing')}
                            size="sm"
                            className="flex-shrink-0"
                        >
                            En Proceso
                        </Button>
                        <Button
                            variant={statusFilter === 'completed' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('completed')}
                            size="sm"
                            className="flex-shrink-0"
                        >
                            Completados
                        </Button>
                        <Button
                            variant={statusFilter === 'failed' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('failed')}
                            size="sm"
                            className="flex-shrink-0"
                        >
                            Fallidos Procesados
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Batches Grid */}
            {isLoading ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {[...Array(6)].map((_, i) => (
                        <Card key={i} className="h-64 animate-pulse bg-gray-100" />
                    ))}
                </div>
            ) : batches.length === 0 ? (
                <Card>
                    <CardContent className="p-12 text-center">
                        <p className="text-gray-700 font-medium">{currentEmptyState.title}</p>
                        <p className="text-gray-500 text-sm mt-1 max-w-md mx-auto">
                            {currentEmptyState.description}
                        </p>
                        {statusFilter === '' ? (
                            <Button onClick={handleNewUpload} className="mt-4">
                                <Plus className="h-4 w-4 mr-2" />
                                Crear Primera Carga
                            </Button>
                        ) : (
                            <Button variant="outline" onClick={() => setStatusFilter('')} className="mt-4">
                                Ver todas las cargas
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {batches.map((batch) => (
                            <UserBatchCard
                                key={batch.uuid}
                                batch={batch}
                                onClick={handleBatchClick}
                            />
                        ))}
                    </div>

                    {/* Pagination Controls */}
                    <PaginationControls
                        currentPage={currentPage}
                        totalPages={totalPages}
                        total={totalBatches}
                        perPage={perPage}
                        onPageChange={handlePageChange}
                        onPerPageChange={handlePerPageChange}
                        disabled={isLoading}
                        perPageOptions={[6, 12, 24, 48]}
                        className="pt-4 border-t"
                    />
                </>
            )}
        </div>
    );
}

export default UserBatchesListPage;
