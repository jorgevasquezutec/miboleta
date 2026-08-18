import {
    VacationRequest,
    CreateVacationRequestDTO,
    RejectVacationRequestDTO,
    VacationBalance,
} from '@/core/domain/entities/VacationRequest';
import { IVacationRepository, VacationFilters, PaginatedVacationRequests } from '@/core/domain/repositories/IVacationRepository';
import apiClient, { toApiError } from '@/infrastructure/http/apiClient';

/**
 * Vacation Repository - Connected to real API
 */
export class VacationRepository implements IVacationRepository {

    // ============ Vacation Requests ============

    async findAll(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());
        if (filters?.wasTaken && filters.wasTaken !== 'all') {
            params.append('was_taken', filters.wasTaken);
        }

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    async findById(id: number): Promise<VacationRequest> {
        try {
            const response = await apiClient.get<{ data: VacationRequest }>(`/vacation-requests/${id}`);
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    async create(data: CreateVacationRequestDTO): Promise<VacationRequest> {
        const payload: any = {
            start_date: data.startDate,
            end_date: data.endDate,
            days_requested: data.daysRequested,
            reason: data.reason,
        };

        // Incluir tenant_id si está especificado (usuarios multi-tenant)
        if (data.tenantId) {
            payload.tenant_id = data.tenantId;
        }

        try {
            const response = await apiClient.post<{ data: VacationRequest }>('/vacation-requests', payload);
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    async cancel(id: number): Promise<void> {
        try {
            await apiClient.delete(`/vacation-requests/${id}`);
        } catch (error) {
            throw toApiError(error);
        }
    }

    // ============ Supervisor Actions ============

    async approve(id: number): Promise<VacationRequest> {
        try {
            const response = await apiClient.put<{ data: VacationRequest }>(
                `/vacation-requests/${id}/approve`
            );
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    async reject(id: number, data: RejectVacationRequestDTO): Promise<VacationRequest> {
        try {
            const response = await apiClient.put<{ data: VacationRequest }>(
                `/vacation-requests/${id}/reject`,
                { reason: data.reason }
            );
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    async markAsTaken(id: number): Promise<VacationRequest> {
        try {
            const response = await apiClient.put<{ data: VacationRequest }>(
                `/vacation-requests/${id}/mark-taken`
            );
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    async markAsNotTaken(id: number): Promise<VacationRequest> {
        try {
            const response = await apiClient.put<{ data: VacationRequest }>(
                `/vacation-requests/${id}/mark-not-taken`
            );
            return this.mapVacationRequest(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    // ============ Supervisor Views ============

    async getPendingApprovals(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests/pending-approval?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    async getPendingConfirmations(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests/pending-confirmation?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    async getMyTeam(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests/my-team?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    async getMyDecisions(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests/my-decisions?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get all vacation requests history for the current tenant
     * This is for admin users to view all company vacation history
     */
    async getAllHistory(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());
        if (filters?.wasTaken && filters.wasTaken !== 'all') {
            params.append('was_taken', filters.wasTaken);
        }
        if (filters?.dateFrom) params.append('date_from', filters.dateFrom);
        if (filters?.dateTo) params.append('date_to', filters.dateTo);
        if (filters?.search) params.append('search', filters.search);
        if (filters?.tenantId) params.append('tenant_id', filters.tenantId.toString());
        // Request all tenant vacation history
        params.append('scope', 'tenant');

        try {
            const response = await apiClient.get<{ data: VacationRequest[]; meta: any }>(
                `/vacation-requests?${params.toString()}`
            );

            return {
                data: response.data.data.map((req) => this.mapVacationRequest(req)),
                meta: {
                    currentPage: response.data.meta.current_page,
                    lastPage: response.data.meta.last_page,
                    perPage: response.data.meta.per_page,
                    total: response.data.meta.total,
                    approvedCount: response.data.meta.approved_count,
                    takenCount: response.data.meta.taken_count,
                },
            };
        } catch (error) {
            throw toApiError(error);
        }
    }

    // ============ Balance ============

    /**
     * Obtiene el saldo de vacaciones del usuario autenticado para una empresa.
     * Si no se pasa tenantId, el backend usa la empresa primaria del usuario.
     */
    async getBalance(tenantId?: number): Promise<VacationBalance> {
        const params = new URLSearchParams();
        if (tenantId) params.append('tenant_id', tenantId.toString());
        const query = params.toString();

        try {
            const response = await apiClient.get<{ data: any }>(
                `/vacation-requests/balance${query ? `?${query}` : ''}`
            );

            return this.mapVacationBalance(response.data.data);
        } catch (error) {
            throw toApiError(error);
        }
    }

    // ============ Mappers ============

    private mapVacationRequest(data: any): VacationRequest {
        return {
            id: data.id,
            userId: data.user_id,
            user: data.user ? this.mapUser(data.user) : undefined,
            tenantId: data.tenant_id,
            startDate: data.start_date,
            endDate: data.end_date,
            daysRequested: data.days_requested,
            reason: data.reason,
            status: data.status,
            statusLabel: data.status_label,
            approvedBy: data.approved_by,
            approvedByUser: data.approved_by_user ? this.mapUser(data.approved_by_user) : null,
            approvedAt: data.approved_at,
            rejectedBy: data.rejected_by,
            rejectedByUser: data.rejected_by_user ? this.mapUser(data.rejected_by_user) : null,
            rejectedAt: data.rejected_at,
            rejectionReason: data.rejection_reason,
            wasTaken: data.was_taken,
            takenLabel: data.taken_label,
            confirmedBy: data.confirmed_by,
            confirmedByUser: data.confirmed_by_user ? this.mapUser(data.confirmed_by_user) : null,
            confirmedAt: data.confirmed_at,
            // Solo llega en los listados del aprobador; en el resto de
            // endpoints la clave no viene y queda null.
            vacationBalance: data.vacation_balance
                ? {
                    tenantId: data.vacation_balance.tenant_id,
                    daysPerYear: Number(data.vacation_balance.days_per_year),
                    pending: Number(data.vacation_balance.pending),
                    taken: Number(data.vacation_balance.taken),
                    truncated: Number(data.vacation_balance.truncated),
                    balance: Number(data.vacation_balance.balance),
                }
                : null,
            durationText: data.duration_text,
            dateRange: data.date_range,
            createdAt: data.created_at,
            updatedAt: data.updated_at,
        };
    }

    private mapUser(data: any) {
        return {
            id: data.id,
            name: data.name,
            lastName: data.last_name,
            fullName: data.full_name,
            email: data.email,
            documentText: data.document_text,
        };
    }

    private mapVacationBalance(data: any): VacationBalance {
        return {
            tenantId: data.tenant_id,
            laborRegime: data.labor_regime,
            daysPerYear: Number(data.days_per_year),
            initial: Number(data.initial),
            accrued: Number(data.accrued),
            taken: Number(data.taken),
            available: Number(data.available),
            // Vacaciones Pendientes / Truncas / Saldo (SPEC-VACACIONES v2).
            pending: Number(data.pending ?? 0),
            truncated: Number(data.truncated ?? 0),
            balance: Number(data.balance ?? 0),
            hireDate: data.hire_date ?? null,
            yearsOfService: Number(data.years_of_service),
            monthsCompleted: Number(data.months_completed ?? 0),
            approver: data.approver
                ? {
                    id: data.approver.id,
                    fullName: data.approver.full_name,
                    email: data.approver.email,
                }
                : null,
            currentPeriodStart: data.current_period_start ?? null,
            currentPeriodEnd: data.current_period_end ?? null,
            requests: {
                pending: Number(data.requests?.pending ?? 0),
                approved: Number(data.requests?.approved ?? 0),
            },
        };
    }
}

// Singleton export
export const vacationRepository = new VacationRepository();
