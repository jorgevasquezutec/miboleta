import { useParams, useNavigate } from 'react-router-dom';
import { useDocumentTitle } from '@/presentation/hooks';
import { Button } from '@/presentation/components/ui/button';
import { ArrowLeft, Download, Users, Loader2 } from 'lucide-react';
import { useBatchProgress } from '@/presentation/hooks/useBatchProgress';
import { BulkUploadProgress } from '@/presentation/components/bulkUpload/BulkUploadProgress';
import { BulkUploadStats } from '@/presentation/components/bulkUpload/BulkUploadStats';
import { BulkUploadErrors } from '@/presentation/components/bulkUpload/BulkUploadErrors';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import { toast } from 'sonner';
import { useState } from 'react';

export function UserBatchDetailPage() {
    useDocumentTitle('Detalle de Carga de Usuarios');
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [isDownloading, setIsDownloading] = useState(false);

    const { batch, isLoading, error } = useBatchProgress({
        id: id!,
        enabled: !!id,
        pollInterval: 3000,
        onComplete: (batch) => {
            if (batch.status === 'completed') {
                toast.success(`✅ Carga completada: ${batch.progress.created_users} usuarios creados`);
            } else if (batch.status === 'partial') {
                toast.warning(`⚠️ Carga completada con ${batch.progress.failed_rows} errores`);
            }
        },
        onError: () => {
            toast.error('Error al cargar el batch');
        },
    });

    const handleDownloadErrors = async () => {
        if (!id) return;

        setIsDownloading(true);
        try {
            await bulkUserUploadService.downloadErrors(id);
            toast.success('Archivo de errores descargado');
        } catch (error) {
            toast.error('Error al descargar errores');
        } finally {
            setIsDownloading(false);
        }
    };

    const handleViewUsers = () => {
        navigate('/users');
    };

    const handleBack = () => {
        navigate('/users/batch');
    };

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center h-[60vh] space-y-4">
                <p className="text-red-600">Error al cargar el batch</p>
                <Button onClick={handleBack}>Volver al Historial</Button>
            </div>
        );
    }

    if (isLoading || !batch) {
        return (
            <div className="flex items-center justify-center h-[60vh]">
                <div className="text-center space-y-4">
                    <Loader2 className="h-12 w-12 animate-spin mx-auto text-blue-600" />
                    <p className="text-gray-600">Cargando información del batch...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" onClick={handleBack}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">{batch.filename}</h1>
                        <p className="text-gray-600 mt-1">
                            {batch.tenant ? `Empresa: ${batch.tenant.name} • ` : ''}
                            Creado por: {batch.created_by?.name || 'N/A'}
                        </p>
                    </div>
                </div>

                <div className="flex gap-2">
                    {batch.has_errors && (
                        <Button
                            variant="outline"
                            onClick={handleDownloadErrors}
                            disabled={isDownloading}
                        >
                            {isDownloading ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Descargando...
                                </>
                            ) : (
                                <>
                                    <Download className="h-4 w-4 mr-2" />
                                    Descargar Errores
                                </>
                            )}
                        </Button>
                    )}

                    {batch.is_completed && (
                        <Button onClick={handleViewUsers}>
                            <Users className="h-4 w-4 mr-2" />
                            Ver Usuarios
                        </Button>
                    )}
                </div>
            </div>

            {/* Progress Card */}
            <BulkUploadProgress batch={batch} />

            {/* Stats */}
            <BulkUploadStats
                totalRows={batch.progress?.total_rows || 0}
                createdUsers={batch.progress?.created_users || 0}
                failedRows={batch.progress?.failed_rows || 0}
            />

            {/* Additional Info */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h3 className="font-semibold mb-2">Información del Archivo</h3>
                    <dl className="space-y-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Nombre:</dt>
                            <dd className="font-medium">{batch.filename}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Tamaño:</dt>
                            <dd className="font-medium">
                                {bulkUserUploadService.formatFileSize(batch.file_size)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Fecha subida:</dt>
                            <dd className="font-medium">
                                {new Date(batch.created_at).toLocaleString()}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div className="bg-gray-50 p-4 rounded-lg">
                    <h3 className="font-semibold mb-2">Estado del Procesamiento</h3>
                    <dl className="space-y-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Estado:</dt>
                            <dd className="font-medium">{batch.status_text}</dd>
                        </div>
                        {batch.started_at && (
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Iniciado:</dt>
                                <dd className="font-medium">
                                    {new Date(batch.started_at).toLocaleString()}
                                </dd>
                            </div>
                        )}
                        {batch.completed_at && (
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Completado:</dt>
                                <dd className="font-medium">
                                    {new Date(batch.completed_at).toLocaleString()}
                                </dd>
                            </div>
                        )}
                        {batch.duration !== null && (
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Duración:</dt>
                                <dd className="font-medium">
                                    {bulkUserUploadService.formatDuration(batch.duration)}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>

            {/* Errors Detail */}
            {batch.errors && Array.isArray(batch.errors) && batch.errors.length > 0 && (
                <BulkUploadErrors errors={batch.errors} />
            )}
        </div>
    );
}
export default UserBatchDetailPage;
