import { useAuthStore } from '@/presentation/stores';
import { User, TenantAssociation } from '@/core/domain/entities';

/**
 * Hook personalizado para autenticación
 * Simplifica el acceso al store de auth
 */
export const useAuth = () => {
  const {
    user,
    currentTenant,
    currentRole,
    isLoading,
    error,
    login,
    logout,
    me,
    switchTenant,
    switchRole,
    updateProfile,
    clearError,
  } = useAuthStore();

  // Computed values
  // Usuario está autenticado si existe user (el token está en cookies HttpOnly)
  const isAuthenticated = !!user;
  const userRole = user?.role || user?.roles?.[0] || null;

  /**
   * Verificar si el usuario tiene un rol específico
   */
  const hasRole = (role: string | string[]): boolean => {
    if (!user) return false;

    const roles = Array.isArray(role) ? role : [role];
    
    // Verificar en user.role (string)
    if (user.role && roles.includes(user.role)) {
      return true;
    }

    // Verificar en user.roles (array)
    if (user.roles && Array.isArray(user.roles)) {
      return user.roles.some(r => roles.includes(r));
    }

    return false;
  };

  /**
   * Verificar si el usuario es Root
   */
  const isRoot = (): boolean => hasRole('root');

  /**
   * Verificar si el usuario es Admin
   */
  const isAdmin = (): boolean => hasRole('admin');

  /**
   * Verificar si el usuario es Client
   */
  const isClient = (): boolean => hasRole('client');

  /**
   * Verificar si el usuario tiene acceso a un tenant específico
   */
  const hasTenantAccess = (tenantId: string): boolean => {
    if (!user) return false;
    if (isRoot()) return true; // Root tiene acceso a todos
    
    return user.tenants?.some(t => t.id === tenantId) || false;
  };

  /**
   * Obtener lista de tenants del usuario
   */
  const getTenants = (): TenantAssociation[] => {
    return user?.tenants || [];
  };

  /**
   * Verificar si el usuario tiene múltiples tenants
   */
  const hasMultipleTenants = (): boolean => {
    return (user?.tenants?.length || 0) > 1;
  };

  /**
   * Obtener nombre completo del usuario
   */
  const getFullName = (): string => {
    if (!user) return '';
    return user.full_name || `${user.name} ${user.last_name || ''}`.trim();
  };

  return {
    // State
    user,
    currentTenant,
    /**
     * Rol activo de la sesión, scoped a currentTenant (ver authStore). Es la
     * fuente de verdad para menús/guards — preferir sobre `userRole` (que es
     * solo el `user.role` global legado) cuando se necesite el rol operativo
     * real dentro de la empresa activa.
     */
    currentRole,
    isLoading,
    error,
    isAuthenticated,
    userRole,

    // Actions
    login,
    logout,
    me,
    switchTenant,
    switchRole,
    updateProfile,
    clearError,

    // Computed helpers
    hasRole,
    isRoot,
    isAdmin,
    isClient,
    hasTenantAccess,
    getTenants,
    hasMultipleTenants,
    getFullName,
  };
};
