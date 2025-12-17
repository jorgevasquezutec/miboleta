// ============================================================
// BULK USER UPLOAD TYPES
// ============================================================

export type BatchStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'partial';

export type BatchStatusBadge = 'secondary' | 'warning' | 'success' | 'danger' | 'info';

export interface UserBatch {
    id: number;
    uuid: string;
    filename: string;
    file_size: number;
    tenant: {
        id: number;
        name: string;
        ruc?: string;
    };
    created_by: {
        id: number;
        name: string;
        email: string;
    };
    status: BatchStatus;
    status_text: string;
    status_badge: BatchStatusBadge;
    total_rows: number;
    processed_rows: number;
    created_users: number;
    updated_users: number;
    failed_rows: number;
    progress_percentage: number;
    formatted_progress: string;
    current_chunk: number;
    total_chunks: number;
    error_summary?: any;
    success_summary?: any;
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
    tenant: string;
    created_by: string;
    status: BatchStatus;
    status_text: string;
    status_badge: BatchStatusBadge;
    total_rows: number;
    created_users: number;
    updated_users: number;
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

export interface BatchProgress {
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
