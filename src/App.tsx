import React from "react";
import { BrowserRouter, Routes, Route, Navigate, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { LoginView } from "@/presentation/pages/auth";
import {
  DashboardPage as AdminDashboardPage,
  TenantsPage,
  UsersPage,
  SettingsPage,
} from "@/presentation/pages/admin";
import {
  DashboardPage as EmployeeDashboardPage,
  DocumentUploadView,
  DocumentViewerView,
  UserProfileView,
} from "@/presentation/pages/employee";
import { Toaster } from "@/presentation/components/ui/sonner";
import {
  LayoutDashboard,
  FileText,
  Users,
  Building2,
  Settings,
  BarChart3,
} from "lucide-react";

type UserRole = "platform_admin" | "tenant_admin" | "employee" | null;

// Protected Route Component
function ProtectedRoute({
  children,
  allowedRoles,
}: {
  children: React.ReactElement;
  allowedRoles: UserRole[];
}) {
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

// Sidebar Component
function Sidebar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuthStore();

  const isActive = (path: string) => location.pathname === path;

  // Mock tenant colors (TODO: get from tenant store)
  const primaryColor = "#2563EB";
  const secondaryColor = "#1E40AF";

  return (
    <aside className="w-64 bg-white border-r border-[rgba(0,0,0,0.1)] min-h-[calc(100vh-73px)]">
      <nav className="p-4 space-y-2">
        {user?.role === "platform_admin" ? (
          // Platform admin menu
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/admin") ? primaryColor : undefined,
                color: isActive("/admin") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <LayoutDashboard className="w-5 h-5" />
              <span>Dashboard</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/tenants")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/tenants") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/tenants") ? primaryColor : undefined,
                color: isActive("/tenants") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <Building2 className="w-5 h-5" />
              <span>Empresas</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/users")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/users") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/users") ? primaryColor : undefined,
                color: isActive("/users") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <Users className="w-5 h-5" />
              <span>Usuarios</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/settings")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/settings") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/settings") ? primaryColor : undefined,
                color: isActive("/settings") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <Settings className="w-5 h-5" />
              <span>Configuración</span>
            </button>
          </>
        ) : (
          // Tenant admin/employee menu
          <>
            <button
              type="button"
              onClick={() => navigate("/dashboard")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/dashboard") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/dashboard") ? primaryColor : undefined,
                color: isActive("/dashboard") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <LayoutDashboard className="w-5 h-5" />
              <span>Dashboard</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/upload")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/upload") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/upload") ? primaryColor : undefined,
                color: isActive("/upload") ? "#FFFFFF" : secondaryColor,
              }}
            >
              <FileText className="w-5 h-5" />
              <span>Cargar Documentos</span>
            </button>

            {user?.role === "tenant_admin" && (
              <>
                <button
                  type="button"
                  onClick={() => navigate("/users")}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                    isActive("/users") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/users") ? primaryColor : undefined,
                    color: isActive("/users") ? "#FFFFFF" : secondaryColor,
                  }}
                >
                  <Users className="w-5 h-5" />
                  <span>Usuarios</span>
                </button>

                <button
                  type="button"
                  onClick={() => navigate("/reports")}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                    isActive("/reports") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/reports") ? primaryColor : undefined,
                    color: isActive("/reports") ? "#FFFFFF" : secondaryColor,
                  }}
                >
                  <BarChart3 className="w-5 h-5" />
                  <span>Reportes</span>
                </button>

                <button
                  type="button"
                  onClick={() => navigate("/settings")}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                    isActive("/settings") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/settings") ? primaryColor : undefined,
                    color: isActive("/settings") ? "#FFFFFF" : secondaryColor,
                  }}
                >
                  <Settings className="w-5 h-5" />
                  <span>Configuración</span>
                </button>
              </>
            )}
          </>
        )}
      </nav>
    </aside>
  );
}

// App Content with Router
function AppContent() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, login, logout } = useAuthStore();

  const handleLogin = async (email: string, password: string) => {
    try {
      await login(email, password);
      navigate(user?.role === "employee" ? "/dashboard" : "/admin");
    } catch (error) {
      console.error("Login error:", error);
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  const handleViewDocument = (id: string) => {
    console.log("Viewing document:", id);
    navigate("/viewer");
  };

  // Show login if not authenticated
  if (!user && location.pathname !== "/login") {
    return (
      <>
        <LoginView onLogin={handleLogin} />
        <Toaster />
      </>
    );
  }

  // Show login page
  if (location.pathname === "/login") {
    return (
      <>
        <LoginView onLogin={handleLogin} />
        <Toaster />
      </>
    );
  }

  const showSidebar = (user?.role === "platform_admin" || user?.role === "tenant_admin") && location.pathname !== "/viewer";

  return (
    <div className="min-h-screen bg-[#F1F5F9]">
      <Navbar
        userName={user?.name || "Usuario"}
        userRole={
          user?.role === "platform_admin"
            ? "Administrador Plataforma"
            : user?.role === "tenant_admin"
            ? "Administrador"
            : "Empleado"
        }
        companyName="MiBoleta"
        notificationCount={3}
        onLogout={handleLogout}
        onSettings={() => navigate("/settings")}
        onProfile={() => navigate("/profile")}
      />

      <div className="flex">
        {showSidebar && <Sidebar />}

        <main className={`flex-1 ${location.pathname === "/viewer" ? "" : "p-8"}`}>
          <Routes>
            <Route path="/login" element={<LoginView onLogin={handleLogin} />} />

            <Route
              path="/"
              element={
                user?.role === "employee" ? (
                  <Navigate to="/dashboard" replace />
                ) : (
                  <Navigate to="/admin" replace />
                )
              }
            />

            <Route
              path="/admin"
              element={
                <ProtectedRoute allowedRoles={["platform_admin", "tenant_admin"]}>
                  <AdminDashboardPage onNavigate={(path) => navigate(path)} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/dashboard"
              element={
                <ProtectedRoute allowedRoles={["employee", "tenant_admin"]}>
                  <EmployeeDashboardPage onViewDocument={handleViewDocument} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/upload"
              element={
                <ProtectedRoute allowedRoles={["tenant_admin", "employee"]}>
                  <DocumentUploadView onBack={() => navigate(user?.role === "employee" ? "/dashboard" : "/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/viewer"
              element={
                <ProtectedRoute allowedRoles={["platform_admin", "tenant_admin", "employee"]}>
                  <DocumentViewerView onBack={() => navigate(user?.role === "employee" ? "/dashboard" : "/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/users"
              element={
                <ProtectedRoute allowedRoles={["platform_admin", "tenant_admin"]}>
                  <UsersPage
                    onBack={() => navigate("/admin")}
                    tenantId={user?.role === "tenant_admin" && user?.tenantId ? user.tenantId : undefined}
                  />
                </ProtectedRoute>
              }
            />

            <Route
              path="/tenants"
              element={
                <ProtectedRoute allowedRoles={["platform_admin"]}>
                  <TenantsPage onBack={() => navigate("/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/settings"
              element={
                <ProtectedRoute allowedRoles={["platform_admin", "tenant_admin"]}>
                  <SettingsPage onBack={() => navigate(user?.role === "employee" ? "/dashboard" : "/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/profile"
              element={
                <ProtectedRoute allowedRoles={["platform_admin", "tenant_admin", "employee"]}>
                  <UserProfileView
                    onBack={() => navigate(user?.role === "employee" ? "/dashboard" : "/admin")}
                    currentUser={user || undefined}
                  />
                </ProtectedRoute>
              }
            />

            <Route
              path="/reports"
              element={
                <ProtectedRoute allowedRoles={["tenant_admin"]}>
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
              }
            />
          </Routes>
        </main>
      </div>

      <Toaster />
    </div>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AppContent />
    </BrowserRouter>
  );
}
