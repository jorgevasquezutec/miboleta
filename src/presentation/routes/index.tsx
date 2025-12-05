import { createBrowserRouter, Navigate } from "react-router-dom";
import { ProtectedRoute } from "./ProtectedRoute";
import { RootLayout } from "./RootLayout";

// Auth pages
import { LoginView } from "@/presentation/pages/auth";

// Admin pages
import {
  DashboardPage as AdminDashboardPage,
  TenantsPage,
  UsersListPage,
  SettingsPage,
} from "@/presentation/pages/admin";

// Employee pages
import {
  DashboardPage as EmployeeDashboardPage,
  DocumentUploadView,
  DocumentViewerView,
} from "@/presentation/pages/employee";

// Shared pages
import { ProfilePage } from "@/presentation/pages/shared";

import { BarChart3 } from "lucide-react";

// Helper component for role-based redirect
function RootRedirect() {
  const userRole = localStorage.getItem("auth-storage");
  let role = null;

  if (userRole) {
    try {
      const parsed = JSON.parse(userRole);
      role = parsed.state?.user?.role;
    } catch (e) {
      console.error("Error parsing auth storage", e);
    }
  }

  if (role === "client") {
    return <Navigate to="/dashboard" replace />;
  }
  return <Navigate to="/admin" replace />;
}

export const router = createBrowserRouter([
  {
    path: "/login",
    element: <LoginView />,
  },
  {
    path: "/",
    element: <RootLayout />,
    children: [
      {
        index: true,
        element: <RootRedirect />,
      },
      {
        path: "admin",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <AdminDashboardPage onNavigate={(path) => window.location.href = path} />
          </ProtectedRoute>
        ),
      },
      {
        path: "dashboard",
        element: (
          <ProtectedRoute allowedRoles={["client", "admin"]}>
            <EmployeeDashboardPage onViewDocument={(id) => window.location.href = `/viewer?id=${id}`} />
          </ProtectedRoute>
        ),
      },
      {
        path: "upload",
        element: (
          <ProtectedRoute allowedRoles={["admin", "client"]}>
            <DocumentUploadView onBack={() => window.history.back()} />
          </ProtectedRoute>
        ),
      },
      {
        path: "viewer",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client"]}>
            <DocumentViewerView onBack={() => window.history.back()} />
          </ProtectedRoute>
        ),
      },
      {
        path: "admin/users",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <UsersListPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <TenantsPage onBack={() => window.history.back()} />
          </ProtectedRoute>
        ),
      },
      {
        path: "settings",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <SettingsPage onBack={() => window.history.back()} />
          </ProtectedRoute>
        ),
      },
      {
        path: "profile",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client"]}>
            <ProfilePage onBack={() => window.history.back()} />
          </ProtectedRoute>
        ),
      },
      {
        path: "reports",
        element: (
          <ProtectedRoute allowedRoles={["admin"]}>
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
          </ProtectedRoute>
        ),
      },
    ],
  },
]);
