import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { Toaster } from "@/presentation/components/ui/sonner";
import { useState } from "react";
import {
  LayoutDashboard,
  FileText,
  Users,
  Building2,
  Settings,
  BarChart3,
  Menu,
} from "lucide-react";

function Sidebar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuthStore();
  const [isExpanded, setIsExpanded] = useState(true);

  const isActive = (path: string) => location.pathname === path;

  const primaryColor = "#2563EB";
  const secondaryColor = "#1E40AF";

  return (
    <aside className={`${isExpanded ? 'w-64' : 'w-16'} bg-white border-r border-[rgba(0,0,0,0.1)] min-h-[calc(100vh-73px)] transition-all duration-300`}>
      {/* Toggle Button */}
      <div className="p-2 border-b border-[rgba(0,0,0,0.1)]">
        <button
          type="button"
          onClick={() => setIsExpanded(!isExpanded)}
          className="w-full flex items-center justify-center px-2 py-2 rounded-lg hover:bg-[#F1F5F9] transition-colors"
          title={isExpanded ? "Contraer sidebar" : "Expandir sidebar"}
        >
          <Menu className="w-5 h-5" style={{ color: secondaryColor }} />
        </button>
      </div>

      <nav className={`${isExpanded ? 'p-4' : 'p-2'} space-y-2`}>
        {user?.role === "root" ? (
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/admin") ? primaryColor : undefined,
                color: isActive("/admin") ? "#FFFFFF" : secondaryColor,
              }}
              title="Dashboard"
            >
              <LayoutDashboard className="w-5 h-5" />
              {isExpanded && <span>Dashboard</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/tenants")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/tenants") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/tenants") ? primaryColor : undefined,
                color: isActive("/tenants") ? "#FFFFFF" : secondaryColor,
              }}
              title="Empresas"
            >
              <Building2 className="w-5 h-5" />
              {isExpanded && <span>Empresas</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/admin/users")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/admin/users") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/admin/users") ? primaryColor : undefined,
                color: isActive("/admin/users") ? "#FFFFFF" : secondaryColor,
              }}
              title="Usuarios"
            >
              <Users className="w-5 h-5" />
              {isExpanded && <span>Usuarios</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/settings")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/settings") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/settings") ? primaryColor : undefined,
                color: isActive("/settings") ? "#FFFFFF" : secondaryColor,
              }}
              title="Configuración"
            >
              <Settings className="w-5 h-5" />
              {isExpanded && <span>Configuración</span>}
            </button>
          </>
        ) : (
          <>
            <button
              type="button"
              onClick={() => navigate("/dashboard")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/dashboard") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/dashboard") ? primaryColor : undefined,
                color: isActive("/dashboard") ? "#FFFFFF" : secondaryColor,
              }}
              title="Dashboard"
            >
              <LayoutDashboard className="w-5 h-5" />
              {isExpanded && <span>Dashboard</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/upload")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                isActive("/upload") ? "text-white" : "hover:bg-[#F1F5F9]"
              }`}
              style={{
                backgroundColor: isActive("/upload") ? primaryColor : undefined,
                color: isActive("/upload") ? "#FFFFFF" : secondaryColor,
              }}
              title="Cargar Documentos"
            >
              <FileText className="w-5 h-5" />
              {isExpanded && <span>Cargar Documentos</span>}
            </button>

            {user?.role === "admin" && (
              <>
                <button
                  type="button"
                  onClick={() => navigate("/admin/users")}
                  className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                    isActive("/admin/users") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/admin/users") ? primaryColor : undefined,
                    color: isActive("/admin/users") ? "#FFFFFF" : secondaryColor,
                  }}
                  title="Usuarios"
                >
                  <Users className="w-5 h-5" />
                  {isExpanded && <span>Usuarios</span>}
                </button>

                <button
                  type="button"
                  onClick={() => navigate("/reports")}
                  className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                    isActive("/reports") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/reports") ? primaryColor : undefined,
                    color: isActive("/reports") ? "#FFFFFF" : secondaryColor,
                  }}
                  title="Reportes"
                >
                  <BarChart3 className="w-5 h-5" />
                  {isExpanded && <span>Reportes</span>}
                </button>

                <button
                  type="button"
                  onClick={() => navigate("/settings")}
                  className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${
                    isActive("/settings") ? "text-white" : "hover:bg-[#F1F5F9]"
                  }`}
                  style={{
                    backgroundColor: isActive("/settings") ? primaryColor : undefined,
                    color: isActive("/settings") ? "#FFFFFF" : secondaryColor,
                  }}
                  title="Configuración"
                >
                  <Settings className="w-5 h-5" />
                  {isExpanded && <span>Configuración</span>}
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
