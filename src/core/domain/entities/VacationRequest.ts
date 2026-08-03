// Domain Entity - VacationRequest (aligned with backend)
// `LaborRegime` vive en `./Tenant` (es una propiedad de la empresa) y ya se
// reexporta desde ahí en el barrel `entities/index.ts` — solo se importa
// aquí para tipar `VacationBalance`, sin volver a exportarlo (evitaría
// ambigüedad de nombres duplicados en el `export *` del barrel).
import type { LaborRegime } from './Tenant';

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

    // Saldo del EMPLEADO SOLICITANTE en la empresa de esta solicitud, que el
    // backend embebe en los listados del aprobador. Ausente en el resto de
    // endpoints (p.ej. "Mis Vacaciones", donde el saldo propio se pide aparte
    // con GET /vacation-requests/balance).
    vacationBalance?: VacationRequestBalance | null;

    // Computed
    durationText: string;
    dateRange: string;

    // Timestamps
    createdAt: string;
    updatedAt: string;
}

// Saldo del solicitante embebido en cada solicitud de los listados del
// aprobador (pending-approval / pending-confirmation / my-decisions), para que
// la tarjeta muestre los 4 conceptos sin un fetch por fila. Es un subconjunto
// de `VacationBalance` (más abajo): solo las cifras que se pintan, sin los
// metadatos del período ni el aprobador, que ahí no aportan.
export interface VacationRequestBalance {
    tenantId: number;
    daysPerYear: number;
    pending: number;
    taken: number;
    truncated: number;
    balance: number;
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
    tenantId?: number; // Para usuarios multi-tenant
}

export interface RejectVacationRequestDTO {
    reason: string;
}

// ============ Vacation Balance ============
// Aligned with backend GET /vacation-requests/balance (Sprint 2)

export interface VacationBalanceApprover {
    id: number;
    fullName: string;
    email?: string;
}

// Conteo de solicitudes propias por estado (para las tarjetas "Solicitudes
// Pendiente" / "Aprobada" de "Mis Vacaciones"). Viene del mismo endpoint de
// balance para no depender de la paginación del listado (ver E2).
export interface VacationBalanceRequestCounts {
    pending: number;
    approved: number;
}

// Los 4 conceptos de vacaciones del cliente (definiciones confirmadas
// 31/07/2026, SPEC-VACACIONES v2 — SUPERSEDE el mapeo original de la Fase 2):
//   - pending ("Vacaciones Pendientes")  = initial + accrued (SIN restar taken)
//   - taken   ("Vacaciones Gozadas")     = días ya tomados/aprobados
//   - truncated ("Vacaciones Truncas")   = perMonth × mesesCompletos del año en curso
//   - balance ("Saldo Vacaciones")       = pending + truncated − taken
// `available` (= initial + accrued − taken = pending − taken) se mantiene
// por compatibilidad: lo sigue usando ProfilePage.tsx ("días disponibles").
export interface VacationBalance {
    tenantId: number;
    laborRegime: LaborRegime;
    daysPerYear: number;
    initial: number;
    accrued: number;
    taken: number;
    available: number;
    pending: number;
    truncated: number;
    balance: number;
    hireDate: string | null;
    yearsOfService: number;
    // Meses completos transcurridos desde el último aniversario (tope 11),
    // usados para calcular `truncated`.
    monthsCompleted: number;
    approver: VacationBalanceApprover | null;
    // Rango del año laboral EN CURSO (desde el último aniversario cumplido
    // hasta el próximo) durante el cual se devengan las Truncas. Null si el
    // usuario no tiene hire_date.
    currentPeriodStart: string | null;
    currentPeriodEnd: string | null;
    requests: VacationBalanceRequestCounts;
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
