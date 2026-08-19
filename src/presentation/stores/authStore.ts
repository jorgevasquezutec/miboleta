import { create } from "zustand";
import { persist } from "zustand/middleware";
import { User, TenantAssociation, ImpersonatorInfo } from "@/core/domain/entities";
import { userRepository } from "@/infrastructure/persistence/repositories";
import { useTenantFilterStore } from "./tenantFilterStore";

/**
 * Prioridad de roles operativos (no incluye 'root', que es global y siempre
 * gana) para elegir el rol activo por defecto al entrar a una empresa.
 * Debe reflejar exactamente User::ROLE_PRIORITY en el backend.
 */
const ROLE_PRIORITY = ["admin_tenant", "admin", "aprobador", "client"] as const;

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
  /**
   * Matriz de Accesos (ability -> roles permitidos), servida por el backend
   * desde config/access_matrix.php — la fuente única de verdad. Llega en el
   * payload de /login y /me.
   *
   * No se refetchea al cambiar de empresa o rol: la matriz no depende de la
   * empresa, solo cambia cuál rol está activo, y de eso ya se encarga
   * `currentRole`. Usar con el helper `can()/useCan()` (hooks/useCan.ts), nunca
   * comparando nombres de rol a mano.
   */
  accessMatrix: Record<string, string[]> | null;
  /**
   * ¿Ya se refrescó la matriz contra el backend en esta carga de la app?
   *
   * NO se persiste (ver `partialize`), así que arranca en false en cada carga
   * y fuerza un refetch por sesión de navegador. Sin esto, la copia de
   * localStorage se quedaba congelada para siempre: al cambiar
   * config/access_matrix.php en el backend, quien tuviera sesión abierta
   * seguía viendo el menú viejo hasta desloguearse (una ability nueva no
   * existía en su mapa y `evaluate()` la denegaba por fail-closed).
   */
  accessMatrixRefreshed: boolean;
  /**
   * Root que está detrás de la sesión activa (ver CONTRATO-IMPERSONATION del
   * backend), o `null` en sesión normal. `user` sigue siendo el empleado
   * impersonado — este campo es solo el rastro de quién opera realmente.
   *
   * NO se persiste (ver `partialize`): tras la recarga dura que hacen
   * `enterImpersonation`/`leaveImpersonation` la única fuente de verdad es lo
   * que devuelva GET /me con las cookies ya canjeadas por el backend. Una
   * copia en localStorage podría quedar "pegada" si la impersonation termina
   * por otra vía (otra pestaña, expiración del token), mostrando el banner
   * de una sesión que ya no es impersonada.
   */
  impersonator: ImpersonatorInfo | null;
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
   * Recupera la Matriz de Accesos desde el backend.
   *
   * Necesario para sesiones restauradas de localStorage que no la tienen
   * guardada (las anteriores a que se persistiera). Sin mapa, useCan() deniega
   * todo. No lanza: ante un fallo conserva la matriz que hubiera.
   */
  fetchAccessMatrix: () => Promise<void>;
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
  /**
   * root entra a operar como `userId` (ver CONTRATO-IMPERSONATION). Al
   * confirmar el backend, hace una RECARGA DURA (`window.location.href`),
   * igual que apiClient.ts al fallar el refresh: hay 9 stores de datos
   * (documentsStore, vacationsStore, tenantsStore, usersStore,
   * notificationsStore, reportsStore, auditSettingsStore,
   * platformSettingsStore, signatureSettingsStore) sin ninguna lógica de
   * reset por cambio de identidad, y la recarga los limpia todos de golpe.
   * Antes de recargar borra 'auth-storage'/'tenant-filter-storage' de
   * localStorage: sin eso, el arranque de la app rehidrataría la identidad
   * vieja (root) desde la copia persistida en vez de tomar la del empleado
   * que sirve /me con las cookies nuevas.
   */
  enterImpersonation: (userId: string) => Promise<void>;
  /** Termina la impersonation activa y vuelve a la sesión de root. Misma recarga dura que `enterImpersonation`. */
  leaveImpersonation: () => Promise<void>;
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
      accessMatrix: null,
      accessMatrixRefreshed: false,
      impersonator: null,
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
            accessMatrix: response.access_matrix ?? null,
            // Un login fresco nunca arranca impersonado: si quedara un valor
            // de una sesión previa en memoria (p. ej. login tras logout sin
            // recarga), se descarta explícitamente.
            impersonator: null,
            isLoading: false,
            error: null,
          });

          // El filtro de empresa vive en localStorage y sobrevive a la sesión:
          // si la anterior murió sin logout (token vencido, navegador cerrado),
          // sus empresas seguirían viajando en X-Tenant-Ids y el backend
          // respondería 403 a TODO, incluido el cambio de contraseña
          // obligatorio. Se reinicia y se sella a nombre de quien entra.
          useTenantFilterStore.getState().resetForUser(response.user.id);
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
          // Limpiar estado local independientemente del resultado.
          // accessMatrixRefreshed vuelve a false para que la próxima sesión en
          // esta misma pestaña refresque la matriz en vez de heredar la marca.
          set({
            user: null,
            currentTenant: null,
            currentRole: null,
            accessMatrixRefreshed: false,
            impersonator: null,
            isLoading: false,
            error: null,
          });

          // El filtro es otro store: borrar su clave no basta, porque el logout
          // navega por SPA sin recargar y el estado en memoria seguiría vivo (y
          // se repersistiría al primer set). Se reinicia primero y luego se
          // borran las claves.
          useTenantFilterStore.getState().resetForUser(null);

          // Forzar limpieza del localStorage de Zustand
          localStorage.removeItem('auth-storage');
          localStorage.removeItem('tenant-filter-storage');
          localStorage.removeItem('pusherTransportNonTLS');
          localStorage.removeItem('TanstackQueryDevtools.open');

        }
      },

      fetchAccessMatrix: async () => {
        try {
          set({ accessMatrix: await userRepository.getAccessMatrix() });
        } catch (error) {
          // Silencioso a propósito: es una recuperación de respaldo. Si falla,
          // el usuario ve la UI mínima pero el backend sigue autorizando bien.
          console.error('[AuthStore] No se pudo obtener la Matriz de Accesos:', error);
        } finally {
          // En `finally`: si el fetch falla, marcarlo igual evita que el efecto
          // que lo dispara vuelva a intentarlo en bucle. Se reintenta en la
          // próxima carga (o al relogear, que trae la matriz en el payload).
          set({ accessMatrixRefreshed: true });
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
            // Si el backend no la envía (versión anterior), conservar la que ya
            // hubiera en el store en vez de borrarla.
            accessMatrix: user.access_matrix ?? get().accessMatrix,
            // A diferencia de accessMatrix, SIN fallback al valor previo: acá
            // "ausente" (undefined o null) significa "ya no hay impersonation
            // activa" y hay que reflejarlo, no conservar un banner viejo.
            impersonator: user.impersonator ?? null,
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

      enterImpersonation: async (userId: string) => {
        set({ isLoading: true, error: null });

        try {
          // El backend ya deja las cookies (access_token/refresh_token del
          // empleado + impersonator_return del root) puestas en la respuesta;
          // acá no hay nada que guardar del payload porque viene la recarga
          // dura de inmediato.
          await userRepository.impersonate(userId);
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al iniciar sesión como el usuario",
            isLoading: false,
          });
          throw error;
        }

        // Limpiar ANTES de recargar (mismo orden que apiClient.ts al fallar
        // el refresh): si el arranque de la app llega a alcanzar a leer
        // 'auth-storage' antes de que /me responda, rehidrataría la sesión
        // de root en vez de esperar a la del empleado.
        localStorage.removeItem('auth-storage');
        localStorage.removeItem('tenant-filter-storage');
        window.location.href = '/';
      },

      leaveImpersonation: async () => {
        set({ isLoading: true, error: null });

        try {
          await userRepository.stopImpersonating();
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al volver a tu cuenta",
            isLoading: false,
          });
          throw error;
        }

        localStorage.removeItem('auth-storage');
        localStorage.removeItem('tenant-filter-storage');
        window.location.href = '/';
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
        // La Matriz de Accesos se persiste como CACHÉ de arranque: sin ella
        // useCan() deniega TODO, y cualquier F5 dejaba al usuario rebotando
        // entre "/" y su ruta hasta agotar la pila de React ("Maximum update
        // depth exceeded"). Con la copia local se puede pintar de inmediato.
        //
        // Es caché, no fuente de verdad: useAccessMatrixReady la refresca
        // contra el backend una vez por carga (ver accessMatrixRefreshed), así
        // que un cambio en config/access_matrix.php llega sin desloguear.
        //
        // No es información sensible: es el mismo mapa para todos y el backend
        // lo sirve en /login, /me y GET /api/access-matrix. Quien autoriza es
        // el backend; esto es solo gating de UI.
        accessMatrix: state.accessMatrix,
        // No persistimos token porque ahora está en cookies HttpOnly
        // Tampoco persistimos `impersonator`: ver su comentario en AuthState.
      }),
    }
  )
);
