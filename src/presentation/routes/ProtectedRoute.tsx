import { Navigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";

// Roles operativos por empresa + root (global). Definido localmente (no en
// User.role, que se mantiene como respaldo global legado con solo 3 valores
// para no romper el CRUD de usuarios existente).
export type UserRole = "root" | "admin" | "client" | "aprobador" | "admin_tenant";

interface ProtectedRouteProps {
  children: React.ReactElement;
  allowedRoles: UserRole[];
}

export function ProtectedRoute({ children, allowedRoles }: ProtectedRouteProps) {
  const location = useLocation();
  const { user, currentRole } = useAuthStore();

  // Sin sesión: al login.
  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Con sesión pero sin rol activo resuelto (o rol no permitido en esta
  // ruta): redirige a "/" para que RootRedirect decida un destino válido
  // según el rol activo (reactivo, no requiere recargar la página).
  if (!currentRole || !allowedRoles.includes(currentRole as UserRole)) {
    return <Navigate to="/" replace />;
  }

  return children;
}
