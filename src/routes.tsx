import { Navigate } from "react-router-dom";
import { AdminDashboardView } from "./components/views/AdminDashboardView";
import { EmployeeDashboardView } from "./components/views/EmployeeDashboardView";
import { DocumentUploadView } from "./components/views/DocumentUploadView";
import { DocumentViewerView } from "./components/views/DocumentViewerView";
import { UserManagementView } from "./components/views/UserManagementView";
import { CompanySettingsView } from "./components/views/CompanySettingsView";
import { TenantManagementView } from "./components/views/TenantManagementView";
import { BarChart3 } from "lucide-react";

export type UserRole = "platform-admin" | "tenant-admin" | "employee" | null;

interface RouteConfig {
  path: string;
  element: JSX.Element;
  allowedRoles: UserRole[];
}

interface RouteProps {
  onNavigate: (view: string) => void;
  onViewDocument: (id: string) => void;
  currentUser: {
    role: UserRole;
    tenantId?: string;
  };
}

export const getRoutes = ({ onNavigate, onViewDocument, currentUser }: RouteProps): RouteConfig[] => [
  {
    path: "/",
    element: <Navigate to={currentUser.role === "employee" ? "/dashboard" : "/admin"} replace />,
    allowedRoles: ["platform-admin", "tenant-admin", "employee"],
  },
  {
    path: "/admin",
    element: <AdminDashboardView onNavigate={onNavigate} />,
    allowedRoles: ["platform-admin", "tenant-admin"],
  },
  {
    path: "/dashboard",
    element: <EmployeeDashboardView onViewDocument={onViewDocument} />,
    allowedRoles: ["employee"],
  },
  {
    path: "/upload",
    element: (
      <DocumentUploadView
        onBack={() => onNavigate(currentUser.role === "employee" ? "/dashboard" : "/admin")}
      />
    ),
    allowedRoles: ["tenant-admin", "employee"],
  },
  {
    path: "/documents",
    element: <EmployeeDashboardView onViewDocument={onViewDocument} />,
    allowedRoles: ["platform-admin", "tenant-admin", "employee"],
  },
  {
    path: "/viewer",
    element: (
      <DocumentViewerView
        onBack={() => onNavigate(currentUser.role === "employee" ? "/dashboard" : "/documents")}
      />
    ),
    allowedRoles: ["platform-admin", "tenant-admin", "employee"],
  },
  {
    path: "/users",
    element: (
      <UserManagementView
        onBack={() => onNavigate("/admin")}
        tenantId={currentUser.role === "tenant-admin" ? currentUser.tenantId : undefined}
      />
    ),
    allowedRoles: ["platform-admin", "tenant-admin"],
  },
  {
    path: "/tenants",
    element: <TenantManagementView onBack={() => onNavigate("/admin")} />,
    allowedRoles: ["platform-admin"],
  },
  {
    path: "/settings",
    element: (
      <CompanySettingsView
        onBack={() => onNavigate(currentUser.role === "employee" ? "/dashboard" : "/admin")}
      />
    ),
    allowedRoles: ["platform-admin", "tenant-admin"],
  },
  {
    path: "/reports",
    element: (
      <div className="space-y-6">
        <h1>Reportes y Análisis</h1>
        <div className="bg-white rounded-lg p-12 text-center border border-[rgba(0,0,0,0.1)]">
          <BarChart3 className="w-16 h-16 text-[#64748B] mx-auto mb-4" />
          <h2 className="text-[#1E40AF] mb-2">Módulo de Reportes</h2>
          <p className="text-[#64748B]">
            Aquí se mostrarán los reportes detallados y análisis de la plataforma
          </p>
        </div>
      </div>
    ),
    allowedRoles: ["tenant-admin"],
  },
];
