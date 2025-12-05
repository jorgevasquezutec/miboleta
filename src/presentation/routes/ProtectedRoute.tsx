import { Navigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";

type UserRole = "root" | "admin" | "client";

interface ProtectedRouteProps {
  children: React.ReactElement;
  allowedRoles: UserRole[];
}

export function ProtectedRoute({ children, allowedRoles }: ProtectedRouteProps) {
  const location = useLocation();
  const { user } = useAuthStore();
  const currentUserRole = user?.role || null;

  if (!currentUserRole) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (!allowedRoles.includes(currentUserRole)) {
    return <Navigate to="/" replace />;
  }

  return children;
}
