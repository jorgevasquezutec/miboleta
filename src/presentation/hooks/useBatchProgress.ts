import { useState, useEffect, useRef } from 'react';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import type { UserBatch } from '@/domain/types/bulkUserUpload.types';

interface UseBatchProgressOptions {
    id: string | number;
    enabled?: boolean;
    pollInterval?: number; // ms
    onComplete?: (batch: UserBatch) => void;
    onError?: (error: Error) => void;
}

export function useBatchProgress({
    id,
    enabled = true,
    pollInterval = 3000,
    onComplete,
    onError,
}: UseBatchProgressOptions) {
    const [batch, setBatch] = useState<UserBatch | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<Error | null>(null);

    const intervalRef = useRef<NodeJS.Timeout | null>(null);
    const hasCompletedRef = useRef(false);

    const fetchBatch = async () => {
        try {
            const data = await bulkUserUploadService.getBatch(id);
            setBatch(data);
            setError(null);
            setIsLoading(false);

            // Si completó y aún no lo habíamos notificado
            if (data.is_completed && !hasCompletedRef.current) {
                hasCompletedRef.current = true;
                onComplete?.(data);

                // Detener polling
                if (intervalRef.current) {
                    clearInterval(intervalRef.current);
                    intervalRef.current = null;
                }
            }

            // Si está en un estado final (completed, failed), detener polling
            if (data.status === 'completed' || data.status === 'failed' || data.status === 'partial') {
                if (intervalRef.current) {
                    clearInterval(intervalRef.current);
                    intervalRef.current = null;
                }
            }

        } catch (err) {
            const error = err instanceof Error ? err : new Error('Error fetching batch');
            setError(error);
            setIsLoading(false);
            onError?.(error);

            // Detener polling en error
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        }
    };

    useEffect(() => {
        if (!enabled) return;

        // Fetch inicial
        fetchBatch();

        // Setup polling
        intervalRef.current = setInterval(() => {
            fetchBatch();
        }, pollInterval);

        // Cleanup
        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [id, enabled, pollInterval]);

    const refresh = () => {
        fetchBatch();
    };

    return {
        batch,
        isLoading,
        error,
        refresh,
        isProcessing: batch?.is_processing ?? false,
        isCompleted: batch?.is_completed ?? false,
    };
}
