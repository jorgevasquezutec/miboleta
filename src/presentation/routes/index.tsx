import { lazy, Suspense } from "react";
import { createBrowserRouter, Navigate } from "react-router-dom";
import { ProtectedRoute } from "./ProtectedRoute";
import { RootLayout } from "./RootLayout";
import { PageLoader } from "@/presentation/components/shared/PageLoader";

// Auth pages (lazy loaded) - tienen export default
const LoginView = lazy(() => import("@/presentation/pages/auth/LoginView"));
const ForceChangePasswordPage = lazy(() => import("@/presentation/pages/auth/ForceChangePasswordPage"));
const ForgotPasswordPage = lazy(() => import("@/presentation/pages/auth/ForgotPasswordPage"));
const ResetPasswordPage = lazy(() => import("@/presentation/pages/auth/ResetPasswordPage"));

// Admin pages (lazy loaded)
const AdminDashboardPage = lazy(() => import("@/presentation/pages/admin/DashboardPage"));
const UsersListPage = lazy(() => import("@/presentation/pages/admin/UsersListPage"));
const UserFormPage = lazy(() => import("@/presentation/pages/admin/UserFormPage"));
const UserDetailPage = lazy(() => import("@/presentation/pages/admin/UserDetailPage"));
const TenantsListPage = lazy(() => import("@/presentation/pages/admin/TenantsListPage"));
const TenantFormPage = lazy(() => import("@/presentation/pages/admin/TenantFormPage"));
const BatchesListPage = lazy(() => import("@/presentation/pages/admin/BatchesListPage"));
const BatchDetailPage = lazy(() => import("@/presentation/pages/admin/BatchDetailPage"));
const DocumentsListPage = lazy(() => import("@/presentation/pages/admin/DocumentsListPage"));
const VacationHistoryPage = lazy(() => import("@/presentation/pages/admin/VacationHistoryPage"));
const TeamVacationsPage = lazy(() => import("@/presentation/pages/admin/TeamVacationsPage"));
const AuditLogsPage = lazy(() => import("@/presentation/pages/admin/AuditLogsPage"));
const UserBatchesListPage = lazy(() => import("@/presentation/pages/admin/UserBatchesListPage"));
const UserBatchUploadPage = lazy(() => import("@/presentation/pages/admin/UserBatchUploadPage"));
const UserBatchDetailPage = lazy(() => import("@/presentation/pages/admin/UserBatchDetailPage"));
const SignatureSettingsPage = lazy(() => import("@/presentation/pages/admin/SignatureSettingsPage"));
const PlatformSettingsPage = lazy(() => import("@/presentation/pages/admin/PlatformSettingsPage"));


// Employee pages (lazy loaded)
const EmployeeDashboardPage = lazy(() => import("@/presentation/pages/employee/DashboardPage"));
const DocumentUploadView = lazy(() => import("@/presentation/pages/employee/DocumentUploadView"));
const DocumentViewerView = lazy(() => import("@/presentation/pages/employee/DocumentViewerView"));
const VacationRequestsListPage = lazy(() => import("@/presentation/pages/employee/VacationRequestsListPage"));
const VacationRequestFormPage = lazy(() => import("@/presentation/pages/employee/VacationRequestFormPage"));

// Shared pages (lazy loaded)
const ProfilePage = lazy(() => import("@/presentation/pages/shared/ProfilePage"));
const NotificationsPage = lazy(() => import("@/presentation/pages/shared/NotificationsPage"));
const NotFoundPage = lazy(() => import("@/presentation/pages/NotFoundPage"));
const ErrorPage = lazy(() => import("@/presentation/pages/ErrorPage"));

// Suspense wrapper component
function LazyPage({ children }: { children: React.ReactNode }) {
  return <Suspense fallback={<PageLoader />}>{children}</Suspense>;
}

// Helper component for role-based redirect
function RootRedirect() {
  const authStorage = localStorage.getItem("auth-storage");
  let role: string | null = null;

  if (authStorage) {
    try {
      const parsed = JSON.parse(authStorage);
      // currentRole es el rol activo scoped a la empresa actual (ver
      // authStore); root/admin/admin_tenant van al panel de
      // administración, client/aprobador a la vista de empleado.
      role = parsed.state?.currentRole ?? parsed.state?.user?.role ?? null;
    } catch (e) {
      console.error("Error parsing auth storage", e);
    }
  }

  if (role === "client" || role === "aprobador") {
    return <Navigate to="/dashboard" replace />;
  }
  return <Navigate to="/admin" replace />;
}

export const router = createBrowserRouter([
  {
    path: "/login",
    element: <LazyPage><LoginView /></LazyPage>,
    errorElement: <LazyPage><ErrorPage /></LazyPage>,
  },
  {
    path: "/forgot-password",
    element: <LazyPage><ForgotPasswordPage /></LazyPage>,
    errorElement: <LazyPage><ErrorPage /></LazyPage>,
  },
  {
    path: "/reset-password",
    element: <LazyPage><ResetPasswordPage /></LazyPage>,
    errorElement: <LazyPage><ErrorPage /></LazyPage>,
  },
  {
    path: "/force-change-password",
    element: <LazyPage><ForceChangePasswordPage /></LazyPage>,
    errorElement: <LazyPage><ErrorPage /></LazyPage>,
  },
  {
    path: "/",
    element: <RootLayout />,
    errorElement: <LazyPage><ErrorPage /></LazyPage>,
    children: [
      {
        index: true,
        element: <RootRedirect />,
      },
      {
        path: "admin",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage>
              <AdminDashboardPage />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "dashboard",
        element: (
          <ProtectedRoute allowedRoles={["client", "admin", "root", "aprobador"]}>
            <LazyPage><EmployeeDashboardPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "upload",
        element: (
          <ProtectedRoute allowedRoles={["admin", "client", "admin_tenant"]}>
            <LazyPage>
              <DocumentUploadView />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "viewer",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client", "aprobador", "admin_tenant"]}>
            <LazyPage>
              <DocumentViewerView />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UsersListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/new",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // ⚠️ IMPORTANTE: Las rutas batch DEBEN ir ANTES de users/:id para evitar conflictos
      {
        path: "users/batch",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserBatchesListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/batch-upload",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserBatchUploadPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/batch/:id",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserBatchDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // Las rutas con parámetros dinámicos van AL FINAL
      {
        path: "users/:id",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/:id/edit",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><UserFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <LazyPage><TenantsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/new",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <LazyPage><TenantFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/:id",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <LazyPage><TenantFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "signature-settings",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <LazyPage><SignatureSettingsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "platform-settings",
        element: (
          <ProtectedRoute allowedRoles={["root"]}>
            <LazyPage><PlatformSettingsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "profile",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client", "aprobador", "admin_tenant"]}>
            <LazyPage>
              <ProfilePage />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "notifications",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client", "aprobador", "admin_tenant"]}>
            <LazyPage><NotificationsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "batches",
        element: (
          <ProtectedRoute allowedRoles={["admin", "admin_tenant"]}>
            <LazyPage><BatchesListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "batches/:id",
        element: (
          <ProtectedRoute allowedRoles={["admin", "admin_tenant"]}>
            <LazyPage><BatchDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "documents",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "admin_tenant"]}>
            <LazyPage><DocumentsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // Vacation Routes
      {
        path: "vacations",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client", "aprobador"]}>
            <LazyPage><VacationRequestsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "vacations/new",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "client", "aprobador"]}>
            <LazyPage><VacationRequestFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "team-vacations",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "aprobador"]}>
            <LazyPage><TeamVacationsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "vacation-history",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "aprobador", "admin_tenant"]}>
            <LazyPage><VacationHistoryPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "audit-logs",
        element: (
          <ProtectedRoute allowedRoles={["root", "admin", "aprobador", "admin_tenant"]}>
            <LazyPage><AuditLogsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "*",
        element: <LazyPage><NotFoundPage /></LazyPage>,
      },
    ],
  },
  {
    path: "*",
    element: <LazyPage><NotFoundPage /></LazyPage>,
  },
]);
