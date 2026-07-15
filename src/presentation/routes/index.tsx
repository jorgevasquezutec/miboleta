import { lazy, Suspense } from "react";
import { createBrowserRouter, Navigate } from "react-router-dom";
import { ProtectedRoute } from "./ProtectedRoute";
import { RootLayout } from "./RootLayout";
import { PageLoader } from "@/presentation/components/shared/PageLoader";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useAccessMatrixReady, useFirstAllowed } from "@/presentation/hooks/useCan";

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
const AuditSettingsPage = lazy(() => import("@/presentation/pages/admin/AuditSettingsPage"));


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

/**
 * Abilities que exige cada ruta con varias opciones, declaradas UNA vez y
 * compartidas entre la ruta y RootRedirect.
 *
 * Que estén juntas no es estética: si RootRedirect manda a un destino con un
 * criterio distinto del que la ruta exige, el usuario rebota entre "/" y el
 * destino en bucle infinito. Compartir la lista hace que no puedan discrepar.
 */
const ROUTE_ABILITIES = {
  admin: ["dashboard.global_metrics", "dashboard.org_metrics"],
  dashboard: ["documents.view_own", "dashboard.own_summary"],
  teamVacations: ["vacations.approve_reject_team", "vacations.view_team_calendar"],
  vacations: ["vacations.request_own", "vacations.view_own_requests"],
};

/**
 * Destinos de aterrizaje en orden de preferencia. Se elige el primero que el
 * usuario pueda abrir DE VERDAD, con las mismas abilities que exige la ruta.
 *
 * Antes se decidía por nombre de rol (client/aprobador -> /dashboard, resto ->
 * /admin) mientras las rutas ya gateaban por ability. Con la matriz aplicada
 * literal el aprobador no tiene 'documents.view_own' ni 'dashboard.own_summary',
 * así que aterrizaba en /dashboard, /dashboard lo devolvía a "/", y vuelta a
 * empezar. Su sitio es "Mi Equipo": aprobar vacaciones es lo único que la
 * matriz le concede.
 */
const HOME_CANDIDATES = [
  { path: "/admin", abilities: ROUTE_ABILITIES.admin },
  { path: "/dashboard", abilities: ROUTE_ABILITIES.dashboard },
  { path: "/team-vacations", abilities: ROUTE_ABILITIES.teamVacations },
  { path: "/vacations", abilities: ROUTE_ABILITIES.vacations },
];

function RootRedirect() {
  const user = useAuthStore((s) => s.user);
  const matrixReady = useAccessMatrixReady();
  const home = useFirstAllowed(HOME_CANDIDATES);

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // Sin matriz no se puede elegir destino sin arriesgar un rebote: esperar.
  if (!matrixReady) {
    return <PageLoader />;
  }

  if (home) {
    return <Navigate to={home} replace />;
  }

  // Ningún destino disponible (p. ej. sin rol en la empresa activa). Terminal a
  // propósito: aquí NO se redirige a ningún lado, porque redirigir a una ruta
  // que va a denegar es exactamente lo que produce el bucle infinito.
  return <NoAccessPage />;
}

function NoAccessPage() {
  const currentTenant = useAuthStore((s) => s.currentTenant);

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 p-6 text-center">
      <h1 className="text-xl font-semibold">Sin accesos disponibles</h1>
      <p className="max-w-md text-sm text-gray-500">
        Tu usuario no tiene permisos asignados
        {currentTenant?.name ? ` en ${currentTenant.name}` : ""}. Si tienes más de
        una empresa, prueba a cambiarla desde el menú superior; si no, contacta al
        administrador.
      </p>
    </div>
  );
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
          <ProtectedRoute requires={ROUTE_ABILITIES.admin}>
            <LazyPage>
              <AdminDashboardPage />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "dashboard",
        element: (
          <ProtectedRoute requires={ROUTE_ABILITIES.dashboard}>
            <LazyPage><EmployeeDashboardPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "upload",
        element: (
          <ProtectedRoute requires="documents.bulk_upload_zip">
            <LazyPage>
              <DocumentUploadView />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "viewer",
        element: (
          <ProtectedRoute requires={["documents.view_own", "documents.view_org"]}>
            <LazyPage>
              <DocumentViewerView />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users",
        element: (
          <ProtectedRoute requires="users.view_list">
            <LazyPage><UsersListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/new",
        element: (
          <ProtectedRoute requires={["users.create_any_role", "users.create_limited_role"]}>
            <LazyPage><UserFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // ⚠️ IMPORTANTE: Las rutas batch DEBEN ir ANTES de users/:id para evitar conflictos
      {
        path: "users/batch",
        element: (
          <ProtectedRoute requires="users.bulk_upload">
            <LazyPage><UserBatchesListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/batch-upload",
        element: (
          <ProtectedRoute requires="users.bulk_upload">
            <LazyPage><UserBatchUploadPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/batch/:id",
        element: (
          <ProtectedRoute requires="users.bulk_upload">
            <LazyPage><UserBatchDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // Las rutas con parámetros dinámicos van AL FINAL
      {
        path: "users/:id",
        element: (
          <ProtectedRoute requires="users.view_list">
            <LazyPage><UserDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "users/:id/edit",
        element: (
          <ProtectedRoute requires="users.update">
            <LazyPage><UserFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants",
        element: (
          <ProtectedRoute requires="tenants.manage">
            <LazyPage><TenantsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/new",
        element: (
          <ProtectedRoute requires="tenants.manage">
            <LazyPage><TenantFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "tenants/:id",
        element: (
          <ProtectedRoute requires="tenants.manage">
            <LazyPage><TenantFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "signature-settings",
        element: (
          <ProtectedRoute requires="platform.manage">
            <LazyPage><SignatureSettingsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "platform-settings",
        element: (
          <ProtectedRoute requires="platform.manage">
            <LazyPage><PlatformSettingsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "audit-settings",
        element: (
          <ProtectedRoute requires="platform.manage">
            <LazyPage><AuditSettingsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "profile",
        element: (
          <ProtectedRoute requires={[]}>
            <LazyPage>
              <ProfilePage />
            </LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "notifications",
        element: (
          <ProtectedRoute requires={[]}>
            <LazyPage><NotificationsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "batches",
        element: (
          <ProtectedRoute requires="documents.view_batches">
            <LazyPage><BatchesListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "batches/:id",
        element: (
          <ProtectedRoute requires="documents.view_batches">
            <LazyPage><BatchDetailPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "documents",
        element: (
          <ProtectedRoute requires="documents.view_org">
            <LazyPage><DocumentsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      // Vacation Routes
      {
        path: "vacations",
        element: (
          <ProtectedRoute requires={ROUTE_ABILITIES.vacations}>
            <LazyPage><VacationRequestsListPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "vacations/new",
        element: (
          <ProtectedRoute requires="vacations.request_own">
            <LazyPage><VacationRequestFormPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "team-vacations",
        element: (
          <ProtectedRoute requires={ROUTE_ABILITIES.teamVacations}>
            <LazyPage><TeamVacationsPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        path: "vacation-history",
        element: (
          <ProtectedRoute requires="vacations.view_history">
            <LazyPage><VacationHistoryPage /></LazyPage>
          </ProtectedRoute>
        ),
      },
      {
        // Matriz de Accesos ("Ver registro de auditoría"): root, admin y
        // admin_tenant. El aprobador NO tiene acceso a auditoría.
        path: "audit-logs",
        element: (
          <ProtectedRoute requires="audit.view">
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
