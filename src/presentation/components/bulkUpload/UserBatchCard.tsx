import { Card, CardContent } from '@/presentation/components/ui/card';
import { Progress } from '@/presentation/components/ui/progress';
import { FileText, Calendar, User, TrendingUp, AlertCircle } from 'lucide-react';
import { BatchStatusBadgeComponent } from './BatchStatusBadge';
import type { UserBatchListItem } from '@/domain/types/bulkUserUpload.types';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';

interface UserBatchCardProps {
    batch: UserBatchListItem;
    onClick: (id: number) => void;
}

export function UserBatchCard({ batch, onClick }: UserBatchCardProps) {
    return (
        <Card
            className="cursor-pointer hover:shadow-lg transition-shadow"
            onClick={() => onClick(batch.id)}
        >
            <CardContent className="p-6">
                <div className="space-y-4">
                    {/* Header */}
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                            <FileText className="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div className="min-w-0 flex-1">
                                <h3 
                                    className="font-semibold text-lg truncate" 
                                    title={batch.filename}
                                >
                                    {batch.filename}
                                </h3>
                                <p className="text-sm text-gray-500">
                                    {bulkUserUploadService.formatFileSize(batch.file_size)}
                                </p>
                            </div>
                        </div>
                        <BatchStatusBadgeComponent
                            status={batch.status}
                            statusBadge={batch.status_badge}
                            statusText={batch.status_text}
                        />
                    </div>

                    {/* Progress Bar (solo si está procesando) */}
                    {batch.status === 'processing' && (
                        <div className="space-y-1">
                            <Progress value={batch.progress_percentage} className="h-2" />
                            <p className="text-xs text-gray-500 text-right">
                                {batch.formatted_progress}
                            </p>
                        </div>
                    )}

                    {/* Stats */}
                    <div className="grid grid-cols-3 gap-4 pt-2">
                        <div className="text-center">
                            <div className="text-lg font-bold text-green-600">
                                {batch.created_users}
                            </div>
                            <div className="text-xs text-gray-600">Creados</div>
                        </div>
                        {/* <div className="text-center">
                            <div className="text-lg font-bold text-yellow-600">
                                {batch.updated_users}
                            </div>
                            <div className="text-xs text-gray-600">Actualizados</div>
                        </div> */}
                        <div className="text-center">
                            <div className="text-lg font-bold text-red-600">
                                {batch.failed_rows}
                            </div>
                            <div className="text-xs text-gray-600">Errores</div>
                        </div>
                    </div>

                    {/* Footer Info */}
                    <div className="flex items-center justify-between text-xs text-gray-500 pt-2 border-t">
                        <div className="flex items-center gap-1">
                            <User className="h-3 w-3" />
                            {batch.created_by?.name || 'N/A'}
                        </div>
                        <div className="flex items-center gap-1">
                            <Calendar className="h-3 w-3" />
                            {new Date(batch.created_at).toLocaleDateString()}
                        </div>
                        {batch.duration !== null && (
                            <div className="flex items-center gap-1">
                                <TrendingUp className="h-3 w-3" />
                                {bulkUserUploadService.formatDuration(batch.duration)}
                            </div>
                        )}
                    </div>

                    {/* Error indicator */}
                    {batch.has_errors && (
                        <div className="flex items-center gap-2 text-xs text-red-600 bg-red-50 p-2 rounded">
                            <AlertCircle className="h-4 w-4" />
                            Esta carga tiene errores. Click para ver detalles.
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
