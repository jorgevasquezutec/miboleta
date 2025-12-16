import { create } from "zustand";
import { persist } from "zustand/middleware";
import { User, TenantAssociation } from "@/core/domain/entities";
import { userRepository } from "@/infrastructure/persistence/repositories";

interface AuthState {
  user: User | null;
  currentTenant: TenantAssociation | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  me: () => Promise<void>;
  switchTenant: (tenantId: string) => void;
  updateProfile: (updates: Partial<User>) => Promise<void>;
  uploadAvatar: (file: File) => Promise<string>;
  deleteAvatar: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      currentTenant: null,
      isLoading: false,
      error: null,

      login: async (email: string, password: string) => {
        set({ isLoading: true, error: null });

        try {
          console.log('[AuthStore] Attempting login for:', email);

          // Llamar directamente al repositorio (cookies se manejan automáticamente)
          const response = await userRepository.login(email, password);

          console.log('[AuthStore] Login successful, user:', response.user.name);
          console.log('[AuthStore] Checking cookies after login...');
          console.log('   document.cookie:', document.cookie || '(empty - HttpOnly cookies not visible)');

          // Determinar tenant actual (primary o primero de la lista)
          const currentTenant =
            response.user.tenants?.find(t => t.is_primary) ||
            response.user.tenants?.[0] ||
            null;

          console.log('🏢 [AuthStore] Current tenant:', currentTenant?.name || 'None');

          set({
            user: response.user,
            currentTenant,
            isLoading: false,
            error: null,
          });
        } catch (error) {
          console.error('[AuthStore] Login failed:', error);
          set({
            error: error instanceof Error ? error.message : "Error al iniciar sesión",
            isLoading: false,
            user: null,
            currentTenant: null,
          });
          throw error;
        }
      },

      logout: async () => {
        set({ isLoading: true });

        try {
          // Llamar al backend para invalidar tokens y limpiar cookies
          await userRepository.logout();
        } catch (error) {
          console.error('Logout error:', error);
        } finally {
          // Limpiar estado local independientemente del resultado
          set({
            user: null,
            currentTenant: null,
            isLoading: false,
            error: null,
          });

          // Forzar limpieza del localStorage de Zustand
          localStorage.removeItem('auth-storage');
        }
      },

      me: async () => {
        set({ isLoading: true, error: null });

        try {
          const user = await userRepository.me();

          // Actualizar tenant actual si el usuario cambió
          const currentTenant =
            user.tenants?.find(t => t.is_primary) ||
            user.tenants?.[0] ||
            null;

          set({
            user,
            currentTenant,
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al obtener usuario",
            isLoading: false,
          });
          throw error;
        }
      },

      switchTenant: (tenantId: string) => {
        const { user } = get();
        if (!user || !user.tenants) {
          throw new Error('No user or tenants available');
        }

        const tenant = user.tenants.find(t => t.id === tenantId);
        if (!tenant) {
          throw new Error('Tenant not found');
        }

        set({ currentTenant: tenant });
      },

      updateProfile: async (updates: Partial<User>) => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          // Use /profile endpoint for self-update (not /users/{id} which requires admin)
          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          const response = await apiClient.put<{ user: User }>('/profile', updates);
          const updatedUser = response.data.user;

          // Merge current user with updated user to preserve fields like avatar_url
          set({
            user: { ...user, ...updatedUser },
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al actualizar perfil",
            isLoading: false,
          });
          throw error;
        }
      },

      uploadAvatar: async (file: File) => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          const formData = new FormData();
          formData.append('avatar', file);

          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          const response = await apiClient.post('/profile/avatar', formData, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          });

          // Update user with new avatar_url
          set({
            user: { ...user, avatar_url: response.data.avatar_url },
            isLoading: false,
          });

          return response.data.avatar_url;
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al subir avatar",
            isLoading: false,
          });
          throw error;
        }
      },

      deleteAvatar: async () => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          await apiClient.delete('/profile/avatar');

          // Update user without avatar_url
          set({
            user: { ...user, avatar_url: undefined },
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al eliminar avatar",
            isLoading: false,
          });
          throw error;
        }
      },

      clearError: () => set({ error: null }),
    }),
    {
      name: "auth-storage",
      partialize: (state) => ({
        user: state.user,
        currentTenant: state.currentTenant,
        // No persistimos token porque ahora está en cookies HttpOnly
      }),
    }
  )
);
