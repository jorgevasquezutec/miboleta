import { Card, CardHeader, CardTitle, CardContent } from '@/presentation/components/ui/card';
import { Progress } from '@/presentation/components/ui/progress';
import { Loader2, CheckCircle, XCircle, Clock } from 'lucide-react';
import type { UserBatch } from '@/domain/types/bulkUserUpload.types';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';

interface BulkUploadProgressProps {
    batch: UserBatch;
}

export function BulkUploadProgress({ batch }: BulkUploadProgressProps) {
    const getStatusIcon = () => {
        switch (batch.status) {
            case 'processing':
                return <Loader2 className="h-5 w-5 animate-spin text-blue-600" />;
            case 'completed':
                return <CheckCircle className="h-5 w-5 text-green-600" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-red-600" />;
            case 'partial':
                return <CheckCircle className="h-5 w-5 text-yellow-600" />;
            default:
                return <Clock className="h-5 w-5 text-gray-600" />;
        }
    };

    const getStatusColor = () => {
        switch (batch.status) {
            case 'processing':
                return 'text-blue-600';
            case 'completed':
                return 'text-green-600';
            case 'failed':
                return 'text-red-600';
            case 'partial':
                return 'text-yellow-600';
            default:
                return 'text-gray-600';
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {getStatusIcon()}
                    <span>Progreso de Carga</span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Progress Bar */}
                <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="font-medium">Progreso General</span>
                        <span className={`font-bold ${getStatusColor()}`}>
                            {batch.progress.formatted}
                        </span>
                    </div>
                    <Progress value={parseFloat(batch.progress.percentage)} className="h-3" />
                    <div className="flex justify-between text-xs text-gray-500">
                        <span>{batch.progress.processed_rows} de {batch.progress.total_rows} procesados</span>
                        {batch.is_processing && batch.progress.total_chunks > 0 && (
                            <span>Chunk {batch.progress.current_chunk} de {batch.progress.total_chunks}</span>
                        )}
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="text-center">
                        <div className="text-2xl font-bold text-green-600">
                            {batch.progress.created_users}
                        </div>
                        <div className="text-xs text-gray-600">Creados</div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-red-600">
                            {batch.progress.failed_rows}
                        </div>
                        <div className="text-xs text-gray-600">Errores</div>
                    </div>
                </div>

                {/* Duration */}
                {batch.duration !== null && (
                    <div className="text-sm text-gray-600 text-center">
                        <Clock className="h-4 w-4 inline mr-1" />
                        Duración: {bulkUserUploadService.formatDuration(batch.duration)}
                    </div>
                )}

                {/* Completion Message */}
                {batch.is_completed && (
                    <div className={`text-center p-3 rounded-lg ${batch.status === 'completed'
                            ? 'bg-green-50 text-green-800'
                            : 'bg-yellow-50 text-yellow-800'
                        }`}>
                        {batch.status === 'completed'
                            ? '✅ Carga completada exitosamente'
                            : '⚠️ Carga completada con algunos errores'
                        }
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
