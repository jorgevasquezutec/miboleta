import { createBrowserRouter, Navigate } from "react-router-dom";
import { ProtectedRoute } from "./ProtectedRoute";
import { RootLayout } from "./RootLayout";

// Auth pages
import { LoginView } from "@/presentation/pages/auth";
import ForceChangePasswordPage from "@/presentation/pages/auth/ForceChangePasswordPage";
import ForgotPasswordPage from "@/presentation/pages/auth/ForgotPasswordPage";
import ResetPasswordPage from "@/presentation/pages/auth/ResetPasswordPage";

// Admin pages
import {
  DashboardPage as AdminDashboardPage,
  UsersListPage,
  SettingsPage,
} from "@/presentation/pages/admin";
import { TenantsListPage } from "@/presentation/pages/admin/TenantsListPage";
import { TenantFormPage } from "@/presentation/pages/admin/TenantFormPage";
import { UserDetailPage } from "@/presentation/pages/admin/UserDetailPage";
import { UserFormPage } from "@/presentation/pages/admin/UserFormPage";

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
    path: "/forgot-password",
    element: <ForgotPasswordPage />,
  },
  {
    path: "/reset-password",
    element: <ResetPasswordPage />,
  },
  {
    path: "/force-change-password",
    element: <ForceChangePasswordPage />,
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
        path: "users",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <UsersListPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "users/new",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <UserFormPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "users/:id",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <UserDetailPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "users/:id/edit",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin"]}>
            <UserFormPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <TenantsListPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/new",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <TenantFormPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/:id",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <TenantFormPage />
          </ProtectedRoute>
        ),
      },
      // {
      //   path: "settings",
      //   element: (
      //     <ProtectedRoute allowedRoles={["root", "admin"]}>
      //       <SettingsPage onBack={() => window.history.back()} />
      //     </ProtectedRoute>
      //   ),
      // },
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
