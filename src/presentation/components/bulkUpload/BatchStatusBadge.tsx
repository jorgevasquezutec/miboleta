import { Badge } from '@/presentation/components/ui/badge';
import type { BatchStatus, BatchStatusBadge } from '@/domain/types/bulkUserUpload.types';

interface BatchStatusBadgeProps {
    status: BatchStatus;
    statusBadge: BatchStatusBadge;
    statusText: string;
}

export function BatchStatusBadgeComponent({ status, statusBadge, statusText }: BatchStatusBadgeProps) {
    const variantMap: Record<BatchStatusBadge, "default" | "secondary" | "destructive" | "outline"> = {
        secondary: 'secondary',
        warning: 'outline',
        success: 'default',
        danger: 'destructive',
        info: 'secondary',
    };

    // Observación 5: 'partial' comparte el badge 'info' con el backend (ver
    // UserBatch::getStatusBadgeAttribute), que aquí caía en la misma variante
    // 'secondary' que 'pending' -ambas grises, indistinguibles-, pese a que
    // 'partial' es una carga YA terminada (parte creada, parte fallida), no
    // una en cola. Se le da tono ámbar/warning propio en vez de heredar la
    // variante 'info'.
    if (status === 'partial') {
        return (
            <Badge
                variant="outline"
                className="border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
            >
                {statusText}
            </Badge>
        );
    }

    return (
        <Badge variant={variantMap[statusBadge]}>
            {statusText}
        </Badge>
    );
}
