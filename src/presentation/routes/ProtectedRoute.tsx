import { Navigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { useAccessMatrixReady, useCanAny } from "@/presentation/hooks/useCan";
import { PageLoader } from "@/presentation/components/shared/PageLoader";

// Roles operativos por empresa + root (global). Definido localmente (no en
// User.role, que se mantiene como respaldo global legado con solo 3 valores
// para no romper el CRUD de usuarios existente).
export type UserRole = "root" | "admin" | "client" | "aprobador" | "admin_tenant";

interface ProtectedRouteProps {
  children: React.ReactElement;
  /**
   * Ability (o abilities) requeridas para entrar a la ruta, según la Matriz de
   * Accesos que sirve el backend (config/access_matrix.php). Con varias, basta
   * con tener AL MENOS UNA (rutas que agrupan acciones, p. ej. "Vacaciones" =
   * solicitar o ver las propias).
   *
   * Array VACÍO = la ruta solo exige estar autenticado (p. ej. /profile,
   * /notifications): no hay ninguna ability que la restrinja.
   *
   * Se declara la ability, no el rol: el backend autoriza con exactamente el
   * mismo mapa, así que la ruta no puede desalinearse de él (era la causa del
   * bug "menú visible -> 403").
   */
  requires: string | string[];
}

export function ProtectedRoute({ children, requires }: ProtectedRouteProps) {
  const location = useLocation();
  const user = useAuthStore((s) => s.user);
  const abilities = Array.isArray(requires) ? requires : [requires];
  const allowed = useCanAny(abilities);
  const matrixReady = useAccessMatrixReady();

  // Sin sesión: al login.
  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Sesión restaurada pero matriz aún desconocida: ESPERAR, no denegar. Sin
  // mapa toda ability es false, y denegar aquí mandaba a "/", que devolvía
  // aquí, en bucle infinito hasta el "Maximum update depth exceeded".
  if (!matrixReady) {
    return <PageLoader />;
  }

  // Con sesión pero sin permiso en la empresa/rol activos: redirige a "/" para
  // que RootRedirect decida un destino válido (reactivo: al cambiar de empresa
  // o rol se reevalúa sin recargar la página).
  // Sin abilities declaradas basta con la sesión (useCanAny([]) sería false).
  if (abilities.length > 0 && !allowed) {
    return <Navigate to="/" replace />;
  }

  return children;
}
