import { create } from "zustand";
import { persist } from "zustand/middleware";
import { User, TenantAssociation } from "@/core/domain/entities";
import { userRepository } from "@/infrastructure/persistence/repositories";

/**
 * Prioridad de roles operativos (no incluye 'root', que es global y siempre
 * gana) para elegir el rol activo por defecto al entrar a una empresa.
 * Debe reflejar exactamente User::ROLE_PRIORITY en el backend.
 */
const ROLE_PRIORITY = ["admin", "administrador_clientes", "aprobador", "client"] as const;

/**
 * Dado un conjunto de nombres de rol (los que el usuario tiene en una
 * empresa específica), retorna el de mayor prioridad según ROLE_PRIORITY.
 * Un rol que no esté en la lista de prioridad se considera de menor
 * prioridad que cualquiera que sí esté, pero igual se devuelve si es el
 * único disponible. Replica User::highestPriorityRole del backend para no
 * depender exclusivamente del valor precalculado que ya viene en
 * `tenant.role`.
 */
function resolveHighestPriorityRole(roleNames?: string[] | null): string | null {
  if (!roleNames || roleNames.length === 0) return null;

  const unique = Array.from(new Set(roleNames.filter(Boolean)));
  if (unique.length === 0) return null;

  return [...unique].sort((a, b) => {
    const posA = ROLE_PRIORITY.indexOf(a as (typeof ROLE_PRIORITY)[number]);
    const posB = ROLE_PRIORITY.indexOf(b as (typeof ROLE_PRIORITY)[number]);
    const rankA = posA === -1 ? ROLE_PRIORITY.length : posA;
    const rankB = posB === -1 ? ROLE_PRIORITY.length : posB;
    return rankA - rankB;
  })[0];
}

/**
 * Calcula el rol activo por defecto para una empresa dada: 'root' si el
 * usuario es root (global), o el de mayor prioridad entre los roles que
 * tiene en esa empresa (con fallback al `role` ya calculado por backend, y
 * al `role` global del usuario si no hay empresa activa).
 */
function resolveCurrentRole(user: User | null, tenant: TenantAssociation | null): string | null {
  if (!user) return null;
  if (user.role === "root") return "root";
  if (!tenant) return user.role ?? null;
  return resolveHighestPriorityRole(tenant.roles) ?? tenant.role ?? null;
}

interface AuthState {
  user: User | null;
  currentTenant: TenantAssociation | null;
  /**
   * Rol activo de la sesión, scoped a `currentTenant` (o 'root' si el
   * usuario es root, que es global y no depende de la empresa activa).
   * Fuente de verdad para el menú (Sidebar/RootLayout) y los guards
   * (ProtectedRoute) — no usar `user.role` para eso, que es solo un
   * respaldo global legado.
   */
  currentRole: string | null;
  isLoading: boolean;
  error: string | null;

  // Actions
  /**
   * @param login DNI o correo electrónico (el backend acepta ambos en el
   * campo `login`).
   */
  login: (login: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  me: () => Promise<void>;
  /**
   * Cambia la empresa activa.
   * - `tenantId` puede ser `null` para "Todas las empresas" (god mode, solo
   *   válido para root).
   * - `tenantOverride` permite pasar los datos de una empresa que no está en
   *   `user.tenants` (caso de root, que no tiene fila en user_tenants y ve
   *   el catálogo completo de empresas vía TenantSwitcher).
   * Recalcula `currentRole` automáticamente.
   */
  switchTenant: (tenantId: string | null, tenantOverride?: TenantAssociation) => void;
  /**
   * Cambia el rol activo dentro de la empresa actual, validando que el
   * usuario efectivamente tenga ese rol allí. No aplica a root.
   */
  switchRole: (roleName: string) => void;
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
      currentRole: null,
      isLoading: false,
      error: null,

      login: async (login: string, password: string) => {
        set({ isLoading: true, error: null });

        try {
          console.log('[AuthStore] Attempting login for:', login);

          // Llamar directamente al repositorio (cookies se manejan automáticamente)
          const response = await userRepository.login(login, password);

          console.log('[AuthStore] Login successful, user:', response.user.name);
          console.log('[AuthStore] Checking cookies after login...');
          console.log('   document.cookie:', document.cookie || '(empty - HttpOnly cookies not visible)');

          // Determinar tenant actual (primary o primero de la lista)
          const currentTenant =
            response.user.tenants?.find(t => t.is_primary) ||
            response.user.tenants?.[0] ||
            null;

          const currentRole = resolveCurrentRole(response.user, currentTenant);

          console.log('🏢 [AuthStore] Current tenant:', currentTenant?.name || 'None');
          console.log('🎭 [AuthStore] Current role:', currentRole || 'None');

          set({
            user: response.user,
            currentTenant,
            currentRole,
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
            currentRole: null,
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
            currentRole: null,
            isLoading: false,
            error: null,
          });

          // Forzar limpieza del localStorage de Zustand
          localStorage.removeItem('auth-storage');
          localStorage.removeItem('tenant-filter-storage');
          localStorage.removeItem('pusherTransportNonTLS');
          localStorage.removeItem('TanstackQueryDevtools.open');

        }
      },

      me: async () => {
        set({ isLoading: true, error: null });

        try {
          const user = await userRepository.me();

          console.log('👤 [AuthStore] User data from /me:', {
            userId: user.id,
            name: user.name,
            tenants: user.tenants,
          });

          // Actualizar tenant actual si el usuario cambió, intentando
          // preservar la empresa activa previa si el usuario sigue teniendo
          // acceso a ella (evita "saltar" de empresa en cada refresh de /me).
          const { currentTenant: previousTenant } = get();
          const isRoot = user.role === 'root';

          // Root no tiene fila en user_tenants (ve el catálogo completo vía
          // TenantSwitcher, no user.tenants), así que su selección previa
          // ("Todas" o una empresa puntual) se preserva tal cual en vez de
          // intentar resolverla contra user.tenants (que para root siempre
          // viene vacío, y la resetearía a "Todas" en cada refresh).
          const currentTenant = isRoot
            ? (previousTenant ?? null)
            : (previousTenant && user.tenants?.find(t => t.id === previousTenant.id)) ||
              user.tenants?.find(t => t.is_primary) ||
              user.tenants?.[0] ||
              null;

          const currentRole = resolveCurrentRole(user, currentTenant);

          set({
            user,
            currentTenant,
            currentRole,
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

      switchTenant: (tenantId: string | null, tenantOverride?: TenantAssociation) => {
        const { user } = get();
        if (!user) {
          throw new Error('No user or tenants available');
        }

        const isRoot = user.role === 'root';

        // "Todas las empresas" (god mode): solo root puede operar sin
        // empresa activa.
        if (tenantId === null) {
          if (!isRoot) {
            throw new Error('Solo el rol root puede operar sin una empresa activa');
          }
          set({ currentTenant: null, currentRole: 'root' });
          return;
        }

        if (!isRoot && !user.tenants) {
          throw new Error('No user or tenants available');
        }

        // Root no tiene fila en user_tenants (ve el catálogo completo vía
        // TenantSwitcher), así que aceptamos un `tenantOverride` para ese
        // caso. Para el resto de roles, el tenant debe existir en
        // user.tenants (las empresas a las que realmente pertenece).
        const tenant =
          user.tenants?.find(t => t.id === tenantId) ||
          (isRoot ? tenantOverride : undefined);

        if (!tenant) {
          throw new Error('Tenant not found');
        }

        const currentRole = isRoot ? 'root' : resolveCurrentRole(user, tenant);

        set({ currentTenant: tenant, currentRole });
      },

      switchRole: (roleName: string) => {
        const { user, currentTenant } = get();
        if (!user) {
          throw new Error('No user logged in');
        }

        if (user.role === 'root') {
          // root es global: no tiene selector de rol por empresa.
          throw new Error('El usuario root no tiene selector de rol');
        }

        if (!currentTenant) {
          throw new Error('No hay una empresa activa para cambiar de rol');
        }

        const availableRoles =
          currentTenant.roles && currentTenant.roles.length > 0
            ? currentTenant.roles
            : currentTenant.role
              ? [currentTenant.role]
              : [];

        if (!availableRoles.includes(roleName)) {
          throw new Error('El usuario no tiene ese rol en la empresa activa');
        }

        set({ currentRole: roleName });
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
        currentRole: state.currentRole,
        // No persistimos token porque ahora está en cookies HttpOnly
      }),
    }
  )
);
