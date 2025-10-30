import React, { useState } from "react";
import { BrowserRouter, Routes, Route, Navigate, useNavigate, useLocation } from "react-router-dom";
import { TenantProvider, useTenant } from "./contexts/TenantContext";
import { PlatformProvider, usePlatform } from "./contexts/PlatformContext";
import { Navbar } from "./components/Navbar";
import { LoginView } from "./components/views/LoginView";
import { AdminDashboardView } from "./components/views/AdminDashboardView";
import { EmployeeDashboardView } from "./components/views/EmployeeDashboardView";
import { DocumentUploadView } from "./components/views/DocumentUploadView";
import { DocumentViewerView } from "./components/views/DocumentViewerView";
import { UserManagementView } from "./components/views/UserManagementView";
import { CompanySettingsView } from "./components/views/CompanySettingsView";
import { TenantManagementView } from "./components/views/TenantManagementView";
import { UserProfileView } from "./components/views/UserProfileView";
import { Toaster } from "./components/ui/sonner";
import {
  LayoutDashboard,
  FileText,
  Users,
  Building2,
  Settings,
  BarChart3,
} from "lucide-react";

type UserRole = "platform-admin" | "tenant-admin" | "manager" | "employee" | null;

// Protected Route Component
function ProtectedRoute({
  children,
  allowedRoles,
  currentUserRole,
}: {
  children: React.ReactElement;
  allowedRoles: UserRole[];
  currentUserRole: UserRole;
}) {
  const location = useLocation();

  if (!currentUserRole) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (!allowedRoles.includes(currentUserRole)) {
    return <Navigate to="/" replace />;
  }

  return children;
}

// Sidebar Component
function Sidebar({ currentUser, tenant }: { currentUser: { role: UserRole; tenantId?: string }; tenant: any }) {
  const navigate = useNavigate();
  const location = useLocation();

  const isActive = (path: string) => location.pathname === path;

  return (
    <aside className="w-64 bg-white border-r border-[rgba(0,0,0,0.1)] min-h-[calc(100vh-73px)]">
      <nav className="p-4 space-y-2">
        {currentUser.role === "platform-admin" ? (
          // Platform admin menu
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/admin") ? tenant.branding.primaryColor : undefined,
                color: isActive("/admin") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/tenants") ? tenant.branding.primaryColor : undefined,
                color: isActive("/tenants") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/users") ? tenant.branding.primaryColor : undefined,
                color: isActive("/users") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/settings") ? tenant.branding.primaryColor : undefined,
                color: isActive("/settings") ? "#FFFFFF" : tenant.branding.secondaryColor,
              }}
            >
              <Settings className="w-5 h-5" />
              <span>Configuración</span>
            </button>
          </>
        ) : (
          // Tenant admin menu
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/admin") ? tenant.branding.primaryColor : undefined,
                color: isActive("/admin") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/upload") ? tenant.branding.primaryColor : undefined,
                color: isActive("/upload") ? "#FFFFFF" : tenant.branding.secondaryColor,
              }}
            >
              <FileText className="w-5 h-5" />
              <span>Cargar Documentos</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/documents")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/documents") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/documents") ? tenant.branding.primaryColor : undefined,
                color: isActive("/documents") ? "#FFFFFF" : tenant.branding.secondaryColor,
              }}
            >
              <FileText className="w-5 h-5" />
              <span>Documentos</span>
            </button>

            <button
              type="button"
              onClick={() => navigate("/users")}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                isActive("/users") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/users") ? tenant.branding.primaryColor : undefined,
                color: isActive("/users") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/reports") ? tenant.branding.primaryColor : undefined,
                color: isActive("/reports") ? "#FFFFFF" : tenant.branding.secondaryColor,
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
                backgroundColor: isActive("/settings") ? tenant.branding.primaryColor : undefined,
                color: isActive("/settings") ? "#FFFFFF" : tenant.branding.secondaryColor,
              }}
            >
              <Settings className="w-5 h-5" />
              <span>Configuración</span>
            </button>
          </>
        )}
      </nav>
    </aside>
  );
}

// App Content with Router
function AppContent() {
  const { tenant } = useTenant();
  const { users, tenants } = usePlatform();
  const navigate = useNavigate();
  const location = useLocation();

  const [currentUser, setCurrentUser] = useState<{
    role: UserRole | null;
    name: string;
    email: string;
    tenantId?: string;
  }>({ role: null, name: "", email: "" });

  const handleLogin = (email: string, _password: string) => {
    // Try find a user in the platform mock
    const found = users.find((u) => u.email.toLowerCase() === email.toLowerCase());
    if (found) {
      setCurrentUser({ role: found.role as UserRole, name: found.name, email: found.email, tenantId: found.tenantId });
      navigate(found.role === "employee" ? "/dashboard" : "/admin");
      return;
    }

    // Fallback mock heuristics
    if (email.includes("platform")) {
      setCurrentUser({ role: "platform-admin", name: "Platform Admin", email });
      navigate("/admin");
    } else if (email.includes("admin") || email.includes("carlos")) {
      const tenantId = tenants && tenants.length ? tenants[0].id : undefined;
      setCurrentUser({ role: "tenant-admin", name: "Admin (mock)", email, tenantId });
      navigate("/admin");
    } else {
      const tenantId = tenants && tenants.length ? tenants[0].id : undefined;
      setCurrentUser({ role: "employee", name: "Empleado (mock)", email, tenantId });
      navigate("/dashboard");
    }
  };

  const handleLogout = () => {
    setCurrentUser({ role: null, name: "", email: "" });
    navigate("/login");
  };

  const handleViewDocument = (id: string) => {
    console.log("Viewing document:", id);
    navigate("/viewer");
  };

  // Show login if not authenticated
  if (!currentUser.role && location.pathname !== "/login") {
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

  const showSidebar = (currentUser.role === "platform-admin" || currentUser.role === "tenant-admin") && location.pathname !== "/viewer";

  return (
    <div className="min-h-screen bg-[#F1F5F9]">
      <Navbar
        userName={currentUser.name}
        userRole={
          currentUser.role === "platform-admin"
            ? "Administrador Plataforma"
            : currentUser.role === "tenant-admin"
            ? "Administrador"
            : "Empleado"
        }
        companyName={tenant.name}
        companyLogo={tenant.branding.logoUrl || undefined}
        notificationCount={3}
        onLogout={handleLogout}
        onSettings={() => navigate("/settings")}
        onProfile={() => navigate("/profile")}
      />

      <div className="flex">
        {showSidebar && <Sidebar currentUser={currentUser} tenant={tenant} />}

        <main className={`flex-1 ${location.pathname === "/viewer" ? "" : "p-8"}`}>
          <Routes>
            <Route path="/login" element={<LoginView onLogin={handleLogin} />} />

            <Route
              path="/"
              element={
                currentUser.role === "employee" ? (
                  <Navigate to="/dashboard" replace />
                ) : (
                  <Navigate to="/admin" replace />
                )
              }
            />

            <Route
              path="/admin"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin"]} currentUserRole={currentUser.role}>
                  <AdminDashboardView onNavigate={(path) => navigate(path)} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/dashboard"
              element={
                <ProtectedRoute allowedRoles={["employee"]} currentUserRole={currentUser.role}>
                  <EmployeeDashboardView onViewDocument={handleViewDocument} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/upload"
              element={
                <ProtectedRoute allowedRoles={["tenant-admin", "employee"]} currentUserRole={currentUser.role}>
                  <DocumentUploadView onBack={() => navigate(currentUser.role === "employee" ? "/dashboard" : "/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/documents"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin", "employee"]} currentUserRole={currentUser.role}>
                  <EmployeeDashboardView onViewDocument={handleViewDocument} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/viewer"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin", "employee"]} currentUserRole={currentUser.role}>
                  <DocumentViewerView onBack={() => navigate(currentUser.role === "employee" ? "/dashboard" : "/documents")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/users"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin"]} currentUserRole={currentUser.role}>
                  <UserManagementView
                    onBack={() => navigate("/admin")}
                    tenantId={currentUser.role === "tenant-admin" ? currentUser.tenantId : undefined}
                  />
                </ProtectedRoute>
              }
            />

            <Route
              path="/tenants"
              element={
                <ProtectedRoute allowedRoles={["platform-admin"]} currentUserRole={currentUser.role}>
                  <TenantManagementView onBack={() => navigate("/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/settings"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin"]} currentUserRole={currentUser.role}>
                  <CompanySettingsView onBack={() => navigate(currentUser.role === "employee" ? "/dashboard" : "/admin")} />
                </ProtectedRoute>
              }
            />

            <Route
              path="/profile"
              element={
                <ProtectedRoute allowedRoles={["platform-admin", "tenant-admin", "manager", "employee"]} currentUserRole={currentUser.role}>
                  <UserProfileView
                    onBack={() => navigate(currentUser.role === "employee" ? "/dashboard" : "/admin")}
                    currentUser={currentUser}
                  />
                </ProtectedRoute>
              }
            />

            <Route
              path="/reports"
              element={
                <ProtectedRoute allowedRoles={["tenant-admin"]} currentUserRole={currentUser.role}>
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
      <PlatformProvider>
        <TenantProvider>
          <AppContent />
        </TenantProvider>
      </PlatformProvider>
    </BrowserRouter>
  );
}
