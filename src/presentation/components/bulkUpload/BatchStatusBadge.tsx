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

    return (
        <Badge variant={variantMap[statusBadge]}>
            {statusText}
        </Badge>
    );
}
