import { create } from "zustand";
import {
    VacationRequest,
    VacationStatus,
    CreateVacationRequestDTO,
    RejectVacationRequestDTO,
} from "@/core/domain/entities";
import { vacationRepository, VacationFilters } from "@/infrastructure/persistence/repositories";

interface VacationsState {
    // Vacation Requests
    vacationRequests: VacationRequest[];
    currentRequest: VacationRequest | null;
    isLoading: boolean;
    error: string | null;

    // Pagination
    page: number;
    perPage: number;
    total: number;
    totalPages: number;

    // Filters
    statusFilter: VacationStatus | 'all';
    yearFilter: number | null;

    // Supervisor views
    pendingApprovals: VacationRequest[];
    pendingApprovalsCount: number;
    pendingConfirmations: VacationRequest[];
    pendingConfirmationsCount: number;
    teamRequests: VacationRequest[];

    // Actions - CRUD
    fetchVacationRequests: (params?: {
        page?: number;
        perPage?: number;
        status?: VacationStatus | 'all';
        year?: number;
    }) => Promise<void>;
    fetchVacationRequestById: (id: number) => Promise<void>;
    createVacationRequest: (data: CreateVacationRequestDTO) => Promise<VacationRequest>;
    cancelVacationRequest: (id: number) => Promise<void>;

    // Actions - Supervisor
    fetchPendingApprovals: (page?: number) => Promise<void>;
    fetchPendingConfirmations: (page?: number) => Promise<void>;
    fetchTeamRequests: (params?: VacationFilters) => Promise<void>;
    approveRequest: (id: number) => Promise<void>;
    rejectRequest: (id: number, reason: string) => Promise<void>;
    markAsTaken: (id: number) => Promise<void>;
    markAsNotTaken: (id: number) => Promise<void>;

    // Utility
    setStatusFilter: (status: VacationStatus | 'all') => void;
    setYearFilter: (year: number | null) => void;
    setPage: (page: number) => void;
    setPerPage: (size: number) => void;
    clearError: () => void;
    reset: () => void;
}

const initialState = {
    vacationRequests: [],
    currentRequest: null,
    isLoading: false,
    error: null,
    page: 1,
    perPage: 10,
    total: 0,
    totalPages: 0,
    statusFilter: 'all' as const,
    yearFilter: null,
    pendingApprovals: [],
    pendingApprovalsCount: 0,
    pendingConfirmations: [],
    pendingConfirmationsCount: 0,
    teamRequests: [],
};

export const useVacationsStore = create<VacationsState>((set, get) => ({
    ...initialState,

    // ============ CRUD Actions ============

    fetchVacationRequests: async (params) => {
        set({ isLoading: true, error: null });
        try {
            const filters: VacationFilters = {
                page: params?.page ?? get().page,
                perPage: params?.perPage ?? get().perPage,
                status: params?.status !== 'all' ? params?.status : undefined,
                year: params?.year ?? get().yearFilter ?? undefined,
            };

            const result = await vacationRepository.findAll(filters);

            set({
                vacationRequests: result.data,
                page: result.meta.currentPage,
                totalPages: result.meta.lastPage,
                total: result.meta.total,
                isLoading: false,
            });
        } catch (error: any) {
            set({ error: error.message || 'Error al cargar solicitudes', isLoading: false });
        }
    },

    fetchVacationRequestById: async (id) => {
        set({ isLoading: true, error: null });
        try {
            const request = await vacationRepository.findById(id);
            set({ currentRequest: request, isLoading: false });
        } catch (error: any) {
            set({ error: error.message || 'Error al cargar la solicitud', isLoading: false });
        }
    },

    createVacationRequest: async (data) => {
        set({ isLoading: true, error: null });
        try {
            const newRequest = await vacationRepository.create(data);
            set((state) => ({
                vacationRequests: [newRequest, ...state.vacationRequests],
                isLoading: false,
            }));
            return newRequest;
        } catch (error: any) {
            set({ error: error.message || 'Error al crear la solicitud', isLoading: false });
            throw error;
        }
    },

    cancelVacationRequest: async (id) => {
        set({ isLoading: true, error: null });
        try {
            await vacationRepository.cancel(id);
            set((state) => ({
                vacationRequests: state.vacationRequests.filter((r) => r.id !== id),
                isLoading: false,
            }));
        } catch (error: any) {
            set({ error: error.message || 'Error al cancelar la solicitud', isLoading: false });
            throw error;
        }
    },

    // ============ Supervisor Actions ============

    fetchPendingApprovals: async (page = 1) => {
        set({ isLoading: true, error: null });
        try {
            const result = await vacationRepository.getPendingApprovals({ page, perPage: 50 });
            set({
                pendingApprovals: result.data,
                pendingApprovalsCount: result.meta.total,
                isLoading: false,
            });
        } catch (error: any) {
            set({ error: error.message || 'Error al cargar pendientes', isLoading: false });
        }
    },

    fetchPendingConfirmations: async (page = 1) => {
        set({ isLoading: true, error: null });
        try {
            const result = await vacationRepository.getPendingConfirmations({ page, perPage: 50 });
            set({
                pendingConfirmations: result.data,
                pendingConfirmationsCount: result.meta.total,
                isLoading: false,
            });
        } catch (error: any) {
            set({ error: error.message || 'Error al cargar confirmaciones pendientes', isLoading: false });
        }
    },

    fetchTeamRequests: async (params) => {
        set({ isLoading: true, error: null });
        try {
            const result = await vacationRepository.getMyTeam(params);
            set({
                teamRequests: result.data,
                page: result.meta.currentPage,
                totalPages: result.meta.lastPage,
                total: result.meta.total,
                isLoading: false,
            });
        } catch (error: any) {
            set({ error: error.message || 'Error al cargar vacaciones del equipo', isLoading: false });
        }
    },

    approveRequest: async (id) => {
        set({ isLoading: true, error: null });
        try {
            const updated = await vacationRepository.approve(id);

            set((state) => ({
                pendingApprovals: state.pendingApprovals.filter((r) => r.id !== id),
                pendingApprovalsCount: state.pendingApprovalsCount - 1,
                vacationRequests: state.vacationRequests.map((r) =>
                    r.id === id ? updated : r
                ),
                currentRequest: state.currentRequest?.id === id ? updated : state.currentRequest,
                isLoading: false,
            }));
        } catch (error: any) {
            set({ error: error.message || 'Error al aprobar la solicitud', isLoading: false });
            throw error;
        }
    },

    rejectRequest: async (id, reason) => {
        set({ isLoading: true, error: null });
        try {
            const updated = await vacationRepository.reject(id, { reason });

            set((state) => ({
                pendingApprovals: state.pendingApprovals.filter((r) => r.id !== id),
                pendingApprovalsCount: state.pendingApprovalsCount - 1,
                vacationRequests: state.vacationRequests.map((r) =>
                    r.id === id ? updated : r
                ),
                currentRequest: state.currentRequest?.id === id ? updated : state.currentRequest,
                isLoading: false,
            }));
        } catch (error: any) {
            set({ error: error.message || 'Error al rechazar la solicitud', isLoading: false });
            throw error;
        }
    },

    markAsTaken: async (id) => {
        set({ isLoading: true, error: null });
        try {
            const updated = await vacationRepository.markAsTaken(id);

            set((state) => ({
                pendingConfirmations: state.pendingConfirmations.filter((r) => r.id !== id),
                pendingConfirmationsCount: state.pendingConfirmationsCount - 1,
                vacationRequests: state.vacationRequests.map((r) =>
                    r.id === id ? updated : r
                ),
                currentRequest: state.currentRequest?.id === id ? updated : state.currentRequest,
                isLoading: false,
            }));
        } catch (error: any) {
            set({ error: error.message || 'Error al marcar como tomada', isLoading: false });
            throw error;
        }
    },

    markAsNotTaken: async (id) => {
        set({ isLoading: true, error: null });
        try {
            const updated = await vacationRepository.markAsNotTaken(id);

            set((state) => ({
                pendingConfirmations: state.pendingConfirmations.filter((r) => r.id !== id),
                pendingConfirmationsCount: state.pendingConfirmationsCount - 1,
                vacationRequests: state.vacationRequests.map((r) =>
                    r.id === id ? updated : r
                ),
                currentRequest: state.currentRequest?.id === id ? updated : state.currentRequest,
                isLoading: false,
            }));
        } catch (error: any) {
            set({ error: error.message || 'Error al marcar como no tomada', isLoading: false });
            throw error;
        }
    },

    // ============ Utility Actions ============

    setStatusFilter: (status) => set({ statusFilter: status, page: 1 }),
    setYearFilter: (year) => set({ yearFilter: year, page: 1 }),
    setPage: (page) => set({ page }),
    setPerPage: (perPage) => set({ perPage, page: 1 }),
    clearError: () => set({ error: null }),
    reset: () => set(initialState),
}));
