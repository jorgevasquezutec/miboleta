// Domain Entity - VacationRequest (aligned with backend)
export interface VacationRequest {
    id: number;
    userId: number;
    user?: VacationUser;
    tenantId: number;
    startDate: string; // YYYY-MM-DD
    endDate: string; // YYYY-MM-DD
    daysRequested: number;
    reason?: string | null;
    status: VacationStatus;
    statusLabel: string;

    // Approval info
    approvedBy?: number | null;
    approvedByUser?: VacationUser | null;
    approvedAt?: string | null;

    // Rejection info
    rejectedBy?: number | null;
    rejectedByUser?: VacationUser | null;
    rejectedAt?: string | null;
    rejectionReason?: string | null;

    // Confirmation info (was taken?)
    wasTaken?: boolean | null;
    takenLabel?: string | null;
    confirmedBy?: number | null;
    confirmedByUser?: VacationUser | null;
    confirmedAt?: string | null;

    // Computed
    durationText: string;
    dateRange: string;

    // Timestamps
    createdAt: string;
    updatedAt: string;
}

export type VacationStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export interface VacationUser {
    id: number;
    name?: string;
    fullName: string;
    email?: string;
    lastName?: string;
    documentText?: string;
}

// Request DTOs
export interface CreateVacationRequestDTO {
    startDate: string;
    endDate: string;
    daysRequested: number;
    reason?: string;
}

export interface RejectVacationRequestDTO {
    reason: string;
}

// Status helpers
export const vacationStatusLabels: Record<VacationStatus, string> = {
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
};

export const vacationStatusColors: Record<VacationStatus, string> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'destructive',
    cancelled: 'secondary',
};

// Taken status helpers
export const vacationTakenLabels: Record<string, string> = {
    true: 'Tomada',
    false: 'No tomada',
    null: 'Pendiente',
};

export const vacationTakenColors: Record<string, string> = {
    true: 'success',
    false: 'destructive',
    null: 'secondary',
};
