import { describe, it, expect, beforeEach, afterAll, vi } from 'vitest';
import { act } from '@testing-library/react';
import { useAuthStore } from '../authStore';
import { mockUser } from '@/test/mocks/handlers';
import type { MeResponse, ImpersonateResponse, LeaveImpersonationResponse } from '@/core/domain/repositories/IUserRepository';

// Mock the userRepository
vi.mock('@/infrastructure/persistence/repositories', () => ({
  userRepository: {
    login: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
    impersonate: vi.fn(),
    stopImpersonating: vi.fn(),
  },
}));

import { userRepository } from '@/infrastructure/persistence/repositories';

describe('authStore', () => {
  beforeEach(() => {
    // Reset store state before each test
    useAuthStore.setState({
      user: null,
      currentTenant: null,
      impersonator: null,
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

    // Ver CONTRATO-IMPERSONATION: /me es la ÚNICA fuente de verdad para
    // `impersonator` (no se persiste, ver authStore.impersonator).
    it('should populate impersonator when /me reports an active impersonation', async () => {
      const impersonator = { id: '99', full_name: 'Root Admin', email: 'root@example.com' };
      vi.mocked(userRepository.me).mockResolvedValueOnce({ ...mockUser, impersonator } as unknown as MeResponse);

      await act(async () => {
        await useAuthStore.getState().me();
      });

      expect(useAuthStore.getState().impersonator).toEqual(impersonator);
    });

    it('should clear a stale impersonator when /me no longer reports one', async () => {
      useAuthStore.setState({
        impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
      });
      vi.mocked(userRepository.me).mockResolvedValueOnce(mockUser as unknown as MeResponse);

      await act(async () => {
        await useAuthStore.getState().me();
      });

      expect(useAuthStore.getState().impersonator).toBeNull();
    });
  });

  describe('enterImpersonation / leaveImpersonation', () => {
    // window.location.href no se puede asignar de verdad en jsdom sin que
    // intente "navegar"; se reemplaza por un objeto controlado y se restaura
    // al final para no filtrar estado entre archivos de test.
    const originalLocation = window.location;

    beforeEach(() => {
      Object.defineProperty(window, 'location', {
        configurable: true,
        writable: true,
        value: { ...originalLocation, href: 'http://localhost/users' },
      });
      localStorage.setItem('auth-storage', JSON.stringify({ state: { user: mockUser } }));
      localStorage.setItem('tenant-filter-storage', JSON.stringify({ state: {} }));
    });

    afterAll(() => {
      Object.defineProperty(window, 'location', {
        configurable: true,
        writable: true,
        value: originalLocation,
      });
    });

    it('should call the repository, wipe the cached identity and hard-reload to "/" on enterImpersonation', async () => {
      vi.mocked(userRepository.impersonate).mockResolvedValueOnce({
        user: mockUser,
        impersonator: { id: '99', full_name: 'Root Admin', email: 'root@example.com' },
      } as unknown as ImpersonateResponse);

      await act(async () => {
        await useAuthStore.getState().enterImpersonation('42');
      });

      expect(userRepository.impersonate).toHaveBeenCalledWith('42');
      // Se limpia ANTES de recargar: si no, el arranque de la app rehidrata
      // la identidad vieja (root) desde localStorage en vez de esperar a /me.
      expect(localStorage.getItem('auth-storage')).toBeNull();
      expect(localStorage.getItem('tenant-filter-storage')).toBeNull();
      expect(window.location.href).toBe('/');
    });

    it('should NOT clear storage or redirect if the backend rejects enterImpersonation', async () => {
      vi.mocked(userRepository.impersonate).mockRejectedValueOnce(new Error('No puedes impersonar a otro root'));

      await act(async () => {
        await expect(useAuthStore.getState().enterImpersonation('42')).rejects.toThrow();
      });

      expect(localStorage.getItem('auth-storage')).not.toBeNull();
      expect(window.location.href).toBe('http://localhost/users');
      expect(useAuthStore.getState().error).toBe('No puedes impersonar a otro root');
      expect(useAuthStore.getState().isLoading).toBe(false);
    });

    it('should call the repository, wipe the cached identity and hard-reload to "/" on leaveImpersonation', async () => {
      vi.mocked(userRepository.stopImpersonating).mockResolvedValueOnce({ user: mockUser } as unknown as LeaveImpersonationResponse);

      await act(async () => {
        await useAuthStore.getState().leaveImpersonation();
      });

      expect(userRepository.stopImpersonating).toHaveBeenCalledTimes(1);
      expect(localStorage.getItem('auth-storage')).toBeNull();
      expect(localStorage.getItem('tenant-filter-storage')).toBeNull();
      expect(window.location.href).toBe('/');
    });

    it('should NOT clear storage or redirect if the backend rejects leaveImpersonation', async () => {
      vi.mocked(userRepository.stopImpersonating).mockRejectedValueOnce(new Error('No hay impersonation activa'));

      await act(async () => {
        await expect(useAuthStore.getState().leaveImpersonation()).rejects.toThrow();
      });

      expect(localStorage.getItem('auth-storage')).not.toBeNull();
      expect(window.location.href).toBe('http://localhost/users');
      expect(useAuthStore.getState().error).toBe('No hay impersonation activa');
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
