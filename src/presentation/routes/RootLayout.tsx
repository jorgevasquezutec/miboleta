import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { Toaster } from "@/presentation/components/ui/sonner";
import {
  LayoutDashboard,
  FileText,
  Users,
  Building2,
  Settings,
  BarChart3,
} from "lucide-react";

function Sidebar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuthStore();

  const isActive = (path: string) => location.pathname === path;

  const primaryColor = "#2563EB";
  const secondaryColor = "#1E40AF";

  return (
    <aside className="w-64 bg-white border-r border-[rgba(0,0,0,0.1)] min-h-[calc(100vh-73px)]">
      <nav className="p-4 space-y-2">
        {user?.role === "root" ? (
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

            {user?.role === "admin" && (
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

export function RootLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuthStore();

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  const showSidebar = (user?.role === "root" || user?.role === "admin") && location.pathname !== "/viewer";

  return (
    <div className="min-h-screen bg-[#F1F5F9]">
      <Navbar
        userName={user?.name || "Usuario"}
        userRole={
          user?.role === "root"
            ? "Administrador Plataforma"
            : user?.role === "admin"
            ? "Administrador"
            : "Cliente"
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
          <Outlet />
        </main>
      </div>

      <Toaster />
    </div>
  );
}
