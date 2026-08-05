import { User } from "@/core/domain/entities";
import { MANAGEABLE_ROLES_BY_ACTOR } from "@/shared/constants";

/**
 * ¿Puede `currentRole` administrar a `targetUser` en la empresa activa?
 *
 * Espeja UserService::canManageUser del backend (Decisión C1, endurecida por
 * Observación 1 2026-08: ahora también bloquea target con rol admin en
 * CUALQUIER empresa, no solo admin_tenant — ver comentario de regla 3 más
 * abajo). El backend ya filtra del listado la fila propia y cualquier
 * admin_tenant cuando el solicitante no es root, pero un actor puede seguir
 * viendo a PARES de su misma jerarquía (otro admin, otro aprobador) a los que
 * no puede editar: este chequeo por objetivo es lo que gatea los controles en
 * esos casos.
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

  // Regla 3 de canManageUser (extendida por Observación 1): un target con rol
  // admin o admin_tenant en CUALQUIER empresa es intocable para un actor
  // no-root, sin importar en qué empresa lo comparta con él.
  if (
    targetUser.tenants?.some(
      (t) => t.role === "admin" || t.role === "admin_tenant"
    )
  ) {
    return false;
  }

  const allowedRoles = MANAGEABLE_ROLES_BY_ACTOR[currentRole];
  if (!allowedRoles) return false;

  const targetRole = targetUser.tenants?.find(
    (t) => String(t.id) === String(activeTenantId)
  )?.role;

  return !!targetRole && allowedRoles.includes(targetRole);
}
