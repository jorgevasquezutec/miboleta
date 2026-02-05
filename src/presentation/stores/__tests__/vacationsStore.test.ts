import { describe, it, expect, beforeEach, vi } from 'vitest';
import { act } from '@testing-library/react';
import { useVacationsStore } from '../vacationsStore';
import { mockVacationRequest } from '@/test/mocks/handlers';

// Mock the vacationRepository
vi.mock('@/infrastructure/persistence/repositories', () => ({
  vacationRepository: {
    findAll: vi.fn(),
    findById: vi.fn(),
    create: vi.fn(),
    cancel: vi.fn(),
    getPendingApprovals: vi.fn(),
    getPendingConfirmations: vi.fn(),
    getMyTeam: vi.fn(),
    getMyDecisions: vi.fn(),
    getAllHistory: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
    markAsTaken: vi.fn(),
    markAsNotTaken: vi.fn(),
  },
}));

import { vacationRepository } from '@/infrastructure/persistence/repositories';

describe('vacationsStore', () => {
  beforeEach(() => {
    useVacationsStore.getState().reset();
    vi.clearAllMocks();
  });

  describe('initial state', () => {
    it('should have empty vacation requests initially', () => {
      const state = useVacationsStore.getState();
      expect(state.vacationRequests).toEqual([]);
    });

    it('should have null currentRequest initially', () => {
      const state = useVacationsStore.getState();
      expect(state.currentRequest).toBeNull();
    });

    it('should not be loading initially', () => {
      const state = useVacationsStore.getState();
      expect(state.isLoading).toBe(false);
    });

    it('should have empty pending approvals initially', () => {
      const state = useVacationsStore.getState();
      expect(state.pendingApprovals).toEqual([]);
      expect(state.pendingApprovalsCount).toBe(0);
    });
  });

  describe('fetchVacationRequests', () => {
    it('should fetch vacation requests successfully', async () => {
      const mockResponse = {
        data: [mockVacationRequest],
        meta: { currentPage: 1, perPage: 10, total: 1, lastPage: 1 },
      };

      vi.mocked(vacationRepository.findAll).mockResolvedValueOnce(mockResponse);

      await act(async () => {
        await useVacationsStore.getState().fetchVacationRequests();
      });

      const state = useVacationsStore.getState();
      expect(state.vacationRequests).toHaveLength(1);
      expect(state.total).toBe(1);
      expect(state.isLoading).toBe(false);
    });

    it('should handle fetch error', async () => {
      vi.mocked(vacationRepository.findAll).mockRejectedValueOnce(new Error('Network error'));

      await act(async () => {
        await useVacationsStore.getState().fetchVacationRequests();
      });

      const state = useVacationsStore.getState();
      expect(state.error).toBeTruthy();
      expect(state.isLoading).toBe(false);
    });
  });

  describe('createVacationRequest', () => {
    it('should create vacation request and add to state', async () => {
      const newRequest = { ...mockVacationRequest, id: '2' };
      vi.mocked(vacationRepository.create).mockResolvedValueOnce(newRequest as any);

      await act(async () => {
        await useVacationsStore.getState().createVacationRequest({
          start_date: '2024-02-01',
          end_date: '2024-02-05',
          days_requested: 5,
          reason: 'Vacaciones',
        });
      });

      const state = useVacationsStore.getState();
      expect(state.vacationRequests).toHaveLength(1);
      expect(state.isLoading).toBe(false);
    });

    it('should handle create error', async () => {
      vi.mocked(vacationRepository.create).mockRejectedValueOnce(new Error('Validation error'));

      await act(async () => {
        try {
          await useVacationsStore.getState().createVacationRequest({
            start_date: '2024-02-01',
            end_date: '2024-02-05',
            days_requested: 5,
          });
        } catch {
          // Expected error
        }
      });

      const state = useVacationsStore.getState();
      expect(state.error).toBeTruthy();
    });
  });

  describe('cancelVacationRequest', () => {
    it('should cancel request and remove from state', async () => {
      // Use numeric ID to match how the store filters
      const numericId = 1;
      const mockRequest = { ...mockVacationRequest, id: numericId };
      useVacationsStore.setState({
        vacationRequests: [mockRequest as any],
      });

      vi.mocked(vacationRepository.cancel).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useVacationsStore.getState().cancelVacationRequest(numericId);
      });

      const state = useVacationsStore.getState();
      expect(state.vacationRequests).toHaveLength(0);
    });
  });

  describe('supervisor actions', () => {
    it('should fetch pending approvals', async () => {
      const mockResponse = {
        data: [mockVacationRequest],
        meta: { currentPage: 1, perPage: 50, total: 1, lastPage: 1 },
      };

      vi.mocked(vacationRepository.getPendingApprovals).mockResolvedValueOnce(mockResponse);

      await act(async () => {
        await useVacationsStore.getState().fetchPendingApprovals();
      });

      const state = useVacationsStore.getState();
      expect(state.pendingApprovals).toHaveLength(1);
      expect(state.pendingApprovalsCount).toBe(1);
    });

    it('should fetch pending confirmations', async () => {
      const mockResponse = {
        data: [{ ...mockVacationRequest, status: 'approved' }],
        meta: { currentPage: 1, perPage: 50, total: 1, lastPage: 1 },
      };

      vi.mocked(vacationRepository.getPendingConfirmations).mockResolvedValueOnce(mockResponse);

      await act(async () => {
        await useVacationsStore.getState().fetchPendingConfirmations();
      });

      const state = useVacationsStore.getState();
      expect(state.pendingConfirmations).toHaveLength(1);
      expect(state.pendingConfirmationsCount).toBe(1);
    });

    it('should approve request', async () => {
      const numericId = 1;
      const mockRequest = { ...mockVacationRequest, id: numericId };
      const approvedRequest = { ...mockRequest, status: 'approved' };
      useVacationsStore.setState({
        pendingApprovals: [mockRequest as any],
        pendingApprovalsCount: 1,
        vacationRequests: [mockRequest as any],
      });

      vi.mocked(vacationRepository.approve).mockResolvedValueOnce(approvedRequest as any);

      await act(async () => {
        await useVacationsStore.getState().approveRequest(numericId);
      });

      const state = useVacationsStore.getState();
      expect(state.pendingApprovals).toHaveLength(0);
      expect(state.pendingApprovalsCount).toBe(0);
    });

    it('should reject request with reason', async () => {
      const numericId = 1;
      const mockRequest = { ...mockVacationRequest, id: numericId };
      const rejectedRequest = { ...mockRequest, status: 'rejected', rejection_reason: 'Reason' };
      useVacationsStore.setState({
        pendingApprovals: [mockRequest as any],
        pendingApprovalsCount: 1,
      });

      vi.mocked(vacationRepository.reject).mockResolvedValueOnce(rejectedRequest as any);

      await act(async () => {
        await useVacationsStore.getState().rejectRequest(numericId, 'Reason');
      });

      const state = useVacationsStore.getState();
      expect(state.pendingApprovals).toHaveLength(0);
    });

    it('should mark as taken', async () => {
      const numericId = 1;
      const mockRequest = { ...mockVacationRequest, id: numericId };
      const takenRequest = { ...mockRequest, status: 'approved', was_taken: true };
      useVacationsStore.setState({
        pendingConfirmations: [mockRequest as any],
        pendingConfirmationsCount: 1,
      });

      vi.mocked(vacationRepository.markAsTaken).mockResolvedValueOnce(takenRequest as any);

      await act(async () => {
        await useVacationsStore.getState().markAsTaken(numericId);
      });

      const state = useVacationsStore.getState();
      expect(state.pendingConfirmations).toHaveLength(0);
      expect(state.pendingConfirmationsCount).toBe(0);
    });

    it('should mark as not taken', async () => {
      const numericId = 1;
      const mockRequest = { ...mockVacationRequest, id: numericId };
      const notTakenRequest = { ...mockRequest, status: 'approved', was_taken: false };
      useVacationsStore.setState({
        pendingConfirmations: [mockRequest as any],
        pendingConfirmationsCount: 1,
      });

      vi.mocked(vacationRepository.markAsNotTaken).mockResolvedValueOnce(notTakenRequest as any);

      await act(async () => {
        await useVacationsStore.getState().markAsNotTaken(numericId);
      });

      const state = useVacationsStore.getState();
      expect(state.pendingConfirmations).toHaveLength(0);
    });
  });

  describe('filter actions', () => {
    it('should set status filter and reset page', () => {
      useVacationsStore.setState({ page: 5 });

      act(() => {
        useVacationsStore.getState().setStatusFilter('approved');
      });

      const state = useVacationsStore.getState();
      expect(state.statusFilter).toBe('approved');
      expect(state.page).toBe(1);
    });

    it('should set year filter', () => {
      act(() => {
        useVacationsStore.getState().setYearFilter(2024);
      });

      expect(useVacationsStore.getState().yearFilter).toBe(2024);
    });

    it('should set page', () => {
      act(() => {
        useVacationsStore.getState().setPage(3);
      });

      expect(useVacationsStore.getState().page).toBe(3);
    });
  });

  describe('utility actions', () => {
    it('should clear error', () => {
      useVacationsStore.setState({ error: 'Some error' });

      act(() => {
        useVacationsStore.getState().clearError();
      });

      expect(useVacationsStore.getState().error).toBeNull();
    });

    it('should reset store', () => {
      useVacationsStore.setState({
        vacationRequests: [mockVacationRequest as any],
        page: 5,
        statusFilter: 'approved',
      });

      act(() => {
        useVacationsStore.getState().reset();
      });

      const state = useVacationsStore.getState();
      expect(state.vacationRequests).toEqual([]);
      expect(state.page).toBe(1);
      expect(state.statusFilter).toBe('all');
    });
  });
});
