import { User } from "@/core/domain/entities";
import { ASSIGNABLE_ROLES_BY_ACTOR } from "@/shared/constants";

/**
 * ¿Puede `currentRole` administrar a `targetUser` en la empresa activa?
 *
 * Espeja UserService::canManageUser del backend (Decisión C1). El backend ya
 * filtra del listado la fila propia y cualquier admin_tenant cuando el
 * solicitante no es root, pero un actor puede seguir viendo a PARES de su
 * misma jerarquía (otro admin, otro aprobador) a los que no puede editar:
 * este chequeo por objetivo es lo que gatea los controles en esos casos.
 *
 * Es solo gating de UI. La garantía dura vive en el backend, que responde 403
 * (UserController::update/destroy, PasswordController::adminResetPassword).
 * Se usa desde UsersListPage (lápiz por fila) y UserDetailPage (editar,
 * restablecer contraseña, activar/desactivar).
 */
export function canEditTarget(
  targetUser: User,
  currentUserId: string | undefined,
  currentRole: string | null,
  activeTenantId: string | undefined
): boolean {
  if (!currentRole) return false;
  if (currentRole === "root") return true;
  if (targetUser.id === currentUserId) return false;

  const allowedRoles = ASSIGNABLE_ROLES_BY_ACTOR[currentRole];
  if (!allowedRoles) return false;

  const targetRole = targetUser.tenants?.find(
    (t) => String(t.id) === String(activeTenantId)
  )?.role;

  return !!targetRole && allowedRoles.includes(targetRole);
}
