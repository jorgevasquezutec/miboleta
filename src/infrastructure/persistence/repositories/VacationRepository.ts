import {
    VacationRequest,
    CreateVacationRequestDTO,
    RejectVacationRequestDTO,
} from '@/core/domain/entities/VacationRequest';
import apiClient from '@/infrastructure/http/apiClient';

export interface VacationFilters {
    page?: number;
    perPage?: number;
    status?: string;
    year?: number;
    wasTaken?: 'true' | 'false' | 'pending' | 'all';
}

export interface PaginatedVacationRequests {
    data: VacationRequest[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
}

/**
 * Vacation Repository - Connected to real API
 */
export class VacationRepository {

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
    }

    async findById(id: number): Promise<VacationRequest> {
        const response = await apiClient.get<{ data: VacationRequest }>(`/vacation-requests/${id}`);
        return this.mapVacationRequest(response.data.data);
    }

    async create(data: CreateVacationRequestDTO): Promise<VacationRequest> {
        const response = await apiClient.post<{ data: VacationRequest }>('/vacation-requests', {
            start_date: data.startDate,
            end_date: data.endDate,
            days_requested: data.daysRequested,
            reason: data.reason,
        });
        return this.mapVacationRequest(response.data.data);
    }

    async cancel(id: number): Promise<void> {
        await apiClient.delete(`/vacation-requests/${id}`);
    }

    // ============ Supervisor Actions ============

    async approve(id: number): Promise<VacationRequest> {
        const response = await apiClient.put<{ data: VacationRequest }>(
            `/vacation-requests/${id}/approve`
        );
        return this.mapVacationRequest(response.data.data);
    }

    async reject(id: number, data: RejectVacationRequestDTO): Promise<VacationRequest> {
        const response = await apiClient.put<{ data: VacationRequest }>(
            `/vacation-requests/${id}/reject`,
            { reason: data.reason }
        );
        return this.mapVacationRequest(response.data.data);
    }

    async markAsTaken(id: number): Promise<VacationRequest> {
        const response = await apiClient.put<{ data: VacationRequest }>(
            `/vacation-requests/${id}/mark-taken`
        );
        return this.mapVacationRequest(response.data.data);
    }

    async markAsNotTaken(id: number): Promise<VacationRequest> {
        const response = await apiClient.put<{ data: VacationRequest }>(
            `/vacation-requests/${id}/mark-not-taken`
        );
        return this.mapVacationRequest(response.data.data);
    }

    // ============ Supervisor Views ============

    async getPendingApprovals(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());

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
    }

    async getPendingConfirmations(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());

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
    }

    async getMyTeam(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());

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
    }

    async getMyDecisions(filters?: VacationFilters): Promise<PaginatedVacationRequests> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.status) params.append('status', filters.status);
        if (filters?.year) params.append('year', filters.year.toString());

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
        // Request all tenant vacation history
        params.append('scope', 'tenant');

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
}

// Singleton export
export const vacationRepository = new VacationRepository();
