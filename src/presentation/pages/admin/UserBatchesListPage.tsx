import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardContent } from '@/presentation/components/ui/card';
import { Plus, RefreshCw } from 'lucide-react';
import { UserBatchCard } from '@/presentation/components/bulkUpload/UserBatchCard';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import type { UserBatchListItem, PaginatedBatchList } from '@/domain/types/bulkUserUpload.types';
import { toast } from 'sonner';

export function UserBatchesListPage() {
    const navigate = useNavigate();
    const [batches, setBatches] = useState<UserBatchListItem[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [statusFilter, setStatusFilter] = useState<string>('');

    const fetchBatches = async () => {
        setIsLoading(true);
        try {
            const data: PaginatedBatchList = await bulkUserUploadService.listBatches({
                page: currentPage,
                per_page: 12,
                status: statusFilter || undefined,
            });

            setBatches(data.data);
            setTotalPages(data.meta.last_page);
        } catch (error) {
            console.error('Error fetching batches:', error);
            toast.error('Error al cargar el historial');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchBatches();
    }, [currentPage, statusFilter]);

    // Auto-refresh si hay batches procesando
    useEffect(() => {
        const hasProcessing = batches.some((b) => b.status === 'processing');

        if (!hasProcessing) return;

        const interval = setInterval(() => {
            fetchBatches();
        }, 5000);

        return () => clearInterval(interval);
    }, [batches]);

    const handleBatchClick = (uuid: string) => {
        navigate(`/admin/users/batch/${uuid}`);
    };

    const handleNewUpload = () => {
        navigate('/admin/users/batch/new');
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold">Historial de Cargas Masivas</h1>
                    <p className="text-gray-600 mt-1">
                        Revisa el historial de todas las cargas masivas de usuarios
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={fetchBatches} size="icon">
                        <RefreshCw className="h-4 w-4" />
                    </Button>
                    <Button onClick={handleNewUpload}>
                        <Plus className="h-4 w-4 mr-2" />
                        Nueva Carga
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex gap-2">
                        <Button
                            variant={statusFilter === '' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('')}
                            size="sm"
                        >
                            Todos
                        </Button>
                        <Button
                            variant={statusFilter === 'processing' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('processing')}
                            size="sm"
                        >
                            En Proceso
                        </Button>
                        <Button
                            variant={statusFilter === 'completed' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('completed')}
                            size="sm"
                        >
                            Completados
                        </Button>
                        <Button
                            variant={statusFilter === 'failed' ? 'default' : 'outline'}
                            onClick={() => setStatusFilter('failed')}
                            size="sm"
                        >
                            Fallidos
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
                        <p className="text-gray-500">No se encontraron cargas masivas</p>
                        <Button onClick={handleNewUpload} className="mt-4">
                            <Plus className="h-4 w-4 mr-2" />
                            Crear Primera Carga
                        </Button>
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

                    {/* Pagination */}
                    {totalPages > 1 && (
                        <div className="flex justify-center gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                disabled={currentPage === 1}
                            >
                                Anterior
                            </Button>
                            <div className="flex items-center px-4">
                                Página {currentPage} de {totalPages}
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                disabled={currentPage === totalPages}
                            >
                                Siguiente
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

export default UserBatchesListPage;
