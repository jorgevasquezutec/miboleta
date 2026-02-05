import { describe, it, expect, beforeEach, vi } from 'vitest';
import { act } from '@testing-library/react';
import { useAuthStore } from '../authStore';
import { mockUser } from '@/test/mocks/handlers';

// Mock the userRepository
vi.mock('@/infrastructure/persistence/repositories', () => ({
  userRepository: {
    login: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
  },
}));

import { userRepository } from '@/infrastructure/persistence/repositories';

describe('authStore', () => {
  beforeEach(() => {
    // Reset store state before each test
    useAuthStore.setState({
      user: null,
      currentTenant: null,
      isLoading: false,
      error: null,
    });
    vi.clearAllMocks();
    // Clear localStorage
    localStorage.clear();
  });

  describe('initial state', () => {
    it('should have null user initially', () => {
      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
    });

    it('should have null currentTenant initially', () => {
      const state = useAuthStore.getState();
      expect(state.currentTenant).toBeNull();
    });

    it('should not be loading initially', () => {
      const state = useAuthStore.getState();
      expect(state.isLoading).toBe(false);
    });

    it('should have no error initially', () => {
      const state = useAuthStore.getState();
      expect(state.error).toBeNull();
    });
  });

  describe('login', () => {
    it('should login successfully and set user', async () => {
      vi.mocked(userRepository.login).mockResolvedValueOnce({ user: mockUser });

      await act(async () => {
        await useAuthStore.getState().login('test@example.com', 'password123');
      });

      const state = useAuthStore.getState();
      expect(state.user).toEqual(mockUser);
      expect(state.isLoading).toBe(false);
      expect(state.error).toBeNull();
    });

    it('should set currentTenant to primary tenant after login', async () => {
      vi.mocked(userRepository.login).mockResolvedValueOnce({ user: mockUser });

      await act(async () => {
        await useAuthStore.getState().login('test@example.com', 'password123');
      });

      const state = useAuthStore.getState();
      expect(state.currentTenant).toBeDefined();
      expect(state.currentTenant?.is_primary).toBe(true);
    });

    it('should set loading state during login', async () => {
      let resolveLogin: (value: any) => void;
      const loginPromise = new Promise((resolve) => {
        resolveLogin = resolve;
      });
      vi.mocked(userRepository.login).mockReturnValueOnce(loginPromise as any);

      const loginAction = useAuthStore.getState().login('test@example.com', 'password123');

      // Check loading is true during login
      expect(useAuthStore.getState().isLoading).toBe(true);

      // Complete the login
      resolveLogin!({ user: mockUser });
      await act(async () => {
        await loginAction;
      });

      expect(useAuthStore.getState().isLoading).toBe(false);
    });

    it('should handle login error', async () => {
      const errorMessage = 'Invalid credentials';
      vi.mocked(userRepository.login).mockRejectedValueOnce(new Error(errorMessage));

      await act(async () => {
        try {
          await useAuthStore.getState().login('test@example.com', 'wrongpassword');
        } catch {
          // Expected error
        }
      });

      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
      expect(state.error).toBe(errorMessage);
      expect(state.isLoading).toBe(false);
    });
  });

  describe('logout', () => {
    it('should logout and clear state', async () => {
      // First set user
      useAuthStore.setState({
        user: mockUser as any,
        currentTenant: mockUser.tenants[0] as any,
      });

      vi.mocked(userRepository.logout).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useAuthStore.getState().logout();
      });

      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
      expect(state.currentTenant).toBeNull();
      expect(state.error).toBeNull();
    });

    it('should clear state even if logout API fails', async () => {
      useAuthStore.setState({
        user: mockUser as any,
        currentTenant: mockUser.tenants[0] as any,
      });

      vi.mocked(userRepository.logout).mockRejectedValueOnce(new Error('Network error'));

      await act(async () => {
        await useAuthStore.getState().logout();
      });

      const state = useAuthStore.getState();
      expect(state.user).toBeNull();
      expect(state.currentTenant).toBeNull();
    });
  });

  describe('switchTenant', () => {
    it('should switch tenant correctly', () => {
      const userWithTenants = {
        ...mockUser,
        tenants: [
          { id: '1', name: 'Tenant 1', is_primary: true },
          { id: '2', name: 'Tenant 2', is_primary: false },
        ],
      };

      useAuthStore.setState({
        user: userWithTenants as any,
        currentTenant: userWithTenants.tenants[0] as any,
      });

      act(() => {
        useAuthStore.getState().switchTenant('2');
      });

      const state = useAuthStore.getState();
      expect(state.currentTenant?.id).toBe('2');
    });

    it('should throw error if tenant not found', () => {
      useAuthStore.setState({
        user: mockUser as any,
        currentTenant: mockUser.tenants[0] as any,
      });

      expect(() => {
        useAuthStore.getState().switchTenant('nonexistent');
      }).toThrow('Tenant not found');
    });

    it('should throw error if no user', () => {
      expect(() => {
        useAuthStore.getState().switchTenant('1');
      }).toThrow('No user or tenants available');
    });
  });

  describe('me', () => {
    it('should fetch current user', async () => {
      vi.mocked(userRepository.me).mockResolvedValueOnce(mockUser);

      await act(async () => {
        await useAuthStore.getState().me();
      });

      const state = useAuthStore.getState();
      expect(state.user).toEqual(mockUser);
      expect(state.isLoading).toBe(false);
    });

    it('should handle me error', async () => {
      vi.mocked(userRepository.me).mockRejectedValueOnce(new Error('Unauthorized'));

      await act(async () => {
        try {
          await useAuthStore.getState().me();
        } catch {
          // Expected error
        }
      });

      const state = useAuthStore.getState();
      expect(state.error).toBe('Unauthorized');
    });
  });

  describe('clearError', () => {
    it('should clear error', () => {
      useAuthStore.setState({ error: 'Some error' });

      act(() => {
        useAuthStore.getState().clearError();
      });

      expect(useAuthStore.getState().error).toBeNull();
    });
  });
});
