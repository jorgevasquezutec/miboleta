// ============================================================
// BULK USER UPLOAD TYPES
// ============================================================

export type BatchStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'partial';

export type BatchStatusBadge = 'secondary' | 'warning' | 'success' | 'danger' | 'info';

export interface BatchProgress {
    total_rows: number;
    processed_rows: number;
    created_users: number;
    failed_rows: number;
    percentage: string;
    formatted: string;
    current_chunk: number;
    total_chunks: number;
}

export interface UserBatch {
    id: number;
    uuid: string;
    filename: string;
    file_size: number;
    file_path: string;
    tenant: {
        id: number;
        name: string;
        ruc?: string;
    } | null;
    created_by: {
        id: number;
        name: string;
        email: string;
    };
    status: BatchStatus;
    status_text: string;
    status_badge: BatchStatusBadge;
    progress: BatchProgress;
    batch_progress?: {
        total_jobs: number;
        pending_jobs: number;
        processed_jobs: number;
        failed_jobs: number;
        progress_percentage: number;
        finished: boolean;
        cancelled: boolean;
    };
    errors?: any;
    summary?: any;
    processing_options?: any;
    duration: number | null;
    has_errors: boolean;
    is_processing: boolean;
    is_completed: boolean;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface UserBatchListItem {
    id: number;
    uuid: string;
    filename: string;
    file_size: number;
    tenant: {
        id: number;
        name: string;
    } | null;
    created_by: {
        id: number;
        name: string;
    } | null;
    status: BatchStatus;
    status_text: string;
    status_badge: BatchStatusBadge;
    total_rows: number;
    created_users: number;
    failed_rows: number;
    progress_percentage: number;
    formatted_progress: string;
    duration: number | null;
    has_errors: boolean;
    created_at: string;
    completed_at: string | null;
}

export interface TemplateConfig {
    max_organizations: number;
    organization_ids?: number[];
}

export interface BulkUploadConfigData {
    organizations: Array<{
        id: number;
        ruc: string;
        name: string;
        supervisors_count: number;
    }>;
    supervisors_by_org: Record<number, Array<{
        id: number;
        email: string;
        full_name: string;
    }>>;
    max_organizations_limit: number;
    default_organizations: number;
}

export interface ValidationError {
    row: number;
    field: string;
    message: string;
}

export interface ValidationWarning {
    row: number;
    field: string;
    message: string;
}

export interface ValidationSummary {
    total: number;
    valid: number;
    errors: number;
    warnings: number;
    consolidated_users?: number;
}

export interface BulkUploadOptions {
    send_welcome_emails: boolean;
    update_existing: boolean;
}

export interface BatchProgressEvent {
    type: 'progress' | 'error' | 'complete';
    chunk?: number;
    total_chunks?: number;
    processed?: number;
    total?: number;
    percentage?: number;
    created?: number;
    updated?: number;
    failed?: number;
    message?: string;
    summary?: any;
}

export interface PaginatedBatchList {
    data: UserBatchListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
}

// ============================================================
// EDITABLE PREVIEW TYPES
// ============================================================

export interface EditableOrganization {
    ruc: string;
    tenant_id?: string;
    supervisor_email?: string;
}

export interface EditableUser {
    id: string; // UUID temporal
    row_number: number;
    nombre: string;
    apellido: string;
    email: string;
    tipo_documento: 'dni' | 'ce' | 'passport' | 'ruc';
    numero_documento: string;
    rol: 'client' | 'root' | 'admin';
    estado: 'active' | 'inactive';
    telefono?: string;
    organizaciones: EditableOrganization[];
    // Metadatos de validación
    _errors: Record<string, string>; // { email: "Email inválido" }
    _warnings: Record<string, string>;
    _isValid: boolean;
    _isNew: boolean; // Agregado manualmente
    _isModified: boolean; // Editado desde el Excel
    _originalData?: any; // Datos originales antes de editar
}

export interface CellPosition {
    rowId: string;
    columnKey: string;
}
