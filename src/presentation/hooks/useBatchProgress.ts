import { useState, useEffect, useRef, useCallback } from 'react';
import { toApiError } from '@/infrastructure/http/apiClient';
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

    // Las callbacks viven en refs para que fetchBatch NO dependa de ellas.
    // Los llamantes las pasan como arrows inline (identidad nueva en cada
    // render), así que si fetchBatch dependiera de ellas cambiaría también en
    // cada render y el efecto de polling destruiría y recrearía el setInterval
    // continuamente, disparando un fetch por render.
    const onCompleteRef = useRef(onComplete);
    const onErrorRef = useRef(onError);

    useEffect(() => {
        onCompleteRef.current = onComplete;
        onErrorRef.current = onError;
    });

    const fetchBatch = useCallback(async () => {
        try {
            const data = await bulkUserUploadService.getBatch(id);
            setBatch(data);
            setError(null);
            setIsLoading(false);

            // Si completó y aún no lo habíamos notificado
            if (data.is_completed && !hasCompletedRef.current) {
                hasCompletedRef.current = true;
                onCompleteRef.current?.(data);

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
            // Distinguir la causa del error en vez del genérico "Error al
            // cargar el batch": D3 (OBS-CLIENTE 2026-07) rastreó el reporte
            // del cliente hasta un 404 real (batch invisible por el bug de
            // tenant_id) que este hook mostraba con un mensaje que sonaba a
            // fallo de procesamiento en vez de a "no encontrado/sin acceso".
            let message = 'Error de red al cargar el batch';
            const apiError = toApiError(err);

            if (apiError.status === 404) {
                message = 'La carga no existe o no tienes acceso a ella';
            } else if (apiError.status === 403) {
                message = 'No tienes permisos para ver esta carga';
            } else if (apiError.status !== undefined) {
                message = 'Error al cargar el batch';
            } else if (apiError.code !== 'network' && err instanceof Error) {
                // `code === 'network'` es un AxiosError sin `response` (sin conexión,
                // timeout, CORS): ahí se conserva el mensaje genérico de arriba, igual
                // que antes. Cualquier otro Error (no-Axios) sí aporta su propio mensaje.
                message = err.message;
            }

            const error = new Error(message);
            setError(error);
            setIsLoading(false);
            onErrorRef.current?.(error);

            // Detener polling en error
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        }
    }, [id]);

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
    }, [id, enabled, pollInterval, fetchBatch]);

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
