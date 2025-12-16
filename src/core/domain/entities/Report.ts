/**
 * Dashboard and Report Types
 */

// Document Statistics
export interface DocumentStats {
    total: number;
    signed: number;
    pending: number;
    active: number;
    by_month: MonthlyData[];
    by_type: Record<string, number>;
    status_distribution: ChartData[];
}

// Vacation Statistics
export interface VacationStats {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    total_days_used: number;
    status_distribution: ChartData[];
    current_year: number;
}

// User Statistics
export interface UserStats {
    total: number;
    active: number;
    inactive: number;
    by_role: Record<string, number>;
}

// Activity Entry (from audit logs)
export interface ActivityEntry {
    id: number;
    user: string;
    action: string;
    description: string;
    category: string;
    entity_type: string | null;
    entity_id: number | null;
    created_at: string;
    time_ago: string;
}

// Complete Dashboard Stats
export interface DashboardStats {
    documents: DocumentStats;
    vacations: VacationStats;
    users: UserStats;
    recent_activity: ActivityEntry[];
}

// Chart Data for Recharts
export interface ChartData {
    name: string;
    value: number;
    color?: string;
}

// Monthly data for bar charts
export interface MonthlyData {
    name: string;
    month: string;
    value: number;
}

// Audit Log Entry
export interface AuditLog {
    id: number;
    user_id: number | null;
    tenant_id: number | null;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    tenant?: {
        id: number;
        name: string;
    };
}

// Audit Actions grouped by category
export interface AuditActions {
    user: Record<string, string>;
    document: Record<string, string>;
    vacation: Record<string, string>;
    tenant: Record<string, string>;
}

// Report Filters
export interface ReportFilters {
    tenant_id?: number;
    start_date?: string;
    end_date?: string;
    date_from?: string;
    date_to?: string;
    status?: string;
    document_type?: string;
    year?: number;
    user_id?: number;
    action?: string;
    entity_type?: string;
    search?: string;
    page?: number;
    per_page?: number;
}

// Paginated Audit Response
export interface PaginatedAuditResponse {
    data: AuditLog[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
