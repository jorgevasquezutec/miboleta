// Domain Entity - DocumentBatch
export interface DocumentBatch {
    id: number;
    tenantId: number;
    uploadedBy: number;
    typeId: number;
    period: string;
    originalFilename: string;
    totalFiles: number;
    processedFiles: number;
    successCount: number;
    replacedCount: number;
    orphanCount: number;
    errorCount: number;
    errors: BatchError[] | null;
    notifyEmployees: boolean;
    notificationsSent: boolean;
    requiresSignature: boolean;
    /** Tamaño de página de las boletas del lote (a4|a5|a10|letter). Ver backend config/signature.php. */
    pageSize?: PageSize;
    status: BatchStatus;
    progressPercentage: number;
    startedAt: string | null;
    completedAt: string | null;
    createdAt: string;

    // Relations (when loaded)
    documentType?: {
        id: number;
        name: string;
        displayName: string;
    };
    uploader?: {
        id: number;
        name: string;
    };
}

export type BatchStatus = 'pending' | 'processing' | 'completed' | 'completed_with_errors' | 'failed' | 'partial';

// Ítem 36: tamaño de página de las boletas, elegido antes de la carga
// masiva para calibrar correctamente la posición de la firma. 'a10' es el
// formato calibrado histórico (comportamiento por defecto).
export type PageSize = 'a4' | 'a5' | 'a10' | 'letter';

export const pageSizeLabels: Record<PageSize, string> = {
    a10: 'A10 (formato estándar de boleta)',
    a4: 'A4',
    a5: 'A5',
    letter: 'Carta (Letter)',
};

export interface BatchError {
    file: string;
    message: string;
}

export interface BatchDocumentsSummary {
    total: number;
    pending: number;
    signed: number;
    orphan: number;
}

// Status helpers
export const batchStatusLabels: Record<BatchStatus, string> = {
    pending: 'Pendiente',
    processing: 'Procesando',
    completed: 'Completado',
    completed_with_errors: 'Completado con errores',
    partial: 'Parcial',
    failed: 'Fallido',
};

export const batchStatusColors: Record<BatchStatus, string> = {
    pending: 'secondary',
    processing: 'warning',
    completed: 'success',
    completed_with_errors: 'warning',
    partial: 'warning',
    failed: 'destructive',
};

// Upload request
export interface BatchUploadRequest {
    file: File;
    typeId: number;
    period: string;
    notifyEmployees: boolean;
    requiresSignature: boolean;
    tenantId?: number;
    /** Tamaño de página de las boletas del ZIP (default 'a10' si se omite). */
    pageSize?: PageSize;
}

// Preview response
export interface ZipPreviewResponse {
    totalFiles: number;
    validPdfs: number;
    invalidNames: { file: string; reason: string }[];
    invalidFormats: { file: string; reason: string }[];
    files: ZipPreviewFile[];
}

export interface ZipPreviewFile {
    name: string;
    size: number;
    valid: boolean;
    documentNumber?: string;
    reason?: string;
}
