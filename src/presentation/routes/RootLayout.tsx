import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { Toaster } from "@/presentation/components/ui/sonner";
import { useState } from "react";
import {
  Users,
  FileText,
  Building2,
  LayoutDashboard,
  FileStack,
} from "lucide-react";
import { USER_ROLE_DISPLAY_LABELS, NAV_LABELS, ROUTES } from "@/shared/constants";

interface SidebarProps {
  isExpanded: boolean;
}

function Sidebar({ isExpanded }: SidebarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuthStore();

  const isActive = (path: string) => location.pathname === path;

  const primaryColor = "#2563EB";
  const secondaryColor = "#1E40AF";

  return (
    <aside className={`${isExpanded ? 'w-64' : 'w-16'} bg-white border-r border-[rgba(0,0,0,0.1)] min-h-[calc(100vh-73px)] transition-all duration-300`}>
      <nav className={`${isExpanded ? 'p-4' : 'p-2'} pt-4 space-y-2`}>
        {user?.role === "root" ? (
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
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
              onClick={() => navigate(ROUTES.TENANTS)}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive(ROUTES.TENANTS) ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive(ROUTES.TENANTS) ? primaryColor : undefined,
                color: isActive(ROUTES.TENANTS) ? "#FFFFFF" : secondaryColor,
              }}
              title={NAV_LABELS.TENANTS}
            >
              <Building2 className="w-5 h-5" />
              {isExpanded && <span>{NAV_LABELS.TENANTS}</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/users")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/users") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/users") ? primaryColor : undefined,
                color: isActive("/users") ? "#FFFFFF" : secondaryColor,
              }}
              title="Usuarios"
            >
              <Users className="w-5 h-5" />
              {isExpanded && <span>Usuarios</span>}
            </button>

            {/* <button
              type="button"
              onClick={() => navigate("/settings")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/settings") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/settings") ? primaryColor : undefined,
                color: isActive("/settings") ? "#FFFFFF" : secondaryColor,
              }}
              title="Configuración"
            >
              <Settings className="w-5 h-5" />
              {isExpanded && <span>Configuración</span>}
            </button> */}
          </>
        ) : user?.role === "admin" ? (
          <>
            <button
              type="button"
              onClick={() => navigate("/admin")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/admin") ? "text-white" : "hover:bg-[#F1F5F9]"
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
              onClick={() => navigate("/dashboard")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/dashboard") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/dashboard") ? primaryColor : undefined,
                color: isActive("/dashboard") ? "#FFFFFF" : secondaryColor,
              }}
              title="Mis Documentos"
            >
              <FileText className="w-5 h-5" />
              {isExpanded && <span>Mis Documentos</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/upload")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/upload") ? "text-white" : "hover:bg-[#F1F5F9]"
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

            <button
              type="button"
              onClick={() => navigate("/users")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/users") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/users") ? primaryColor : undefined,
                color: isActive("/users") ? "#FFFFFF" : secondaryColor,
              }}
              title="Usuarios"
            >
              <Users className="w-5 h-5" />
              {isExpanded && <span>Usuarios</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/batches")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/batches") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/batches") ? primaryColor : undefined,
                color: isActive("/batches") ? "#FFFFFF" : secondaryColor,
              }}
              title="Lotes de Carga"
            >
              <FileStack className="w-5 h-5" />
              {isExpanded && <span>Lotes de Carga</span>}
            </button>

            <button
              type="button"
              onClick={() => navigate("/documents")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/documents") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/documents") ? primaryColor : undefined,
                color: isActive("/documents") ? "#FFFFFF" : secondaryColor,
              }}
              title="Documentos"
            >
              <FileText className="w-5 h-5" />
              {isExpanded && <span>Documentos</span>}
            </button>
          </>
        ) : user?.role === "client" ? (
          <>
            <button
              type="button"
              onClick={() => navigate("/dashboard")}
              className={`w-full flex items-center ${isExpanded ? 'justify-start' : 'justify-center'} gap-3 ${isExpanded ? 'px-4' : 'px-2'} py-3 rounded-lg transition-colors ${isActive("/dashboard") ? "text-white" : "hover:bg-[#F1F5F9]"
                }`}
              style={{
                backgroundColor: isActive("/dashboard") ? primaryColor : undefined,
                color: isActive("/dashboard") ? "#FFFFFF" : secondaryColor,
              }}
              title="Mis Documentos"
            >
              <FileText className="w-5 h-5" />
              {isExpanded && <span>Mis Documentos</span>}
            </button>
          </>
        ) : null}
      </nav>
    </aside>
  );
}

export function RootLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuthStore();
  const [isSidebarExpanded, setIsSidebarExpanded] = useState(true);

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  const handleToggleSidebar = () => {
    setIsSidebarExpanded(!isSidebarExpanded);
  };

  const showSidebar = user?.role === "root" || user?.role === "admin" || user?.role === "client";

  return (
    <div className="min-h-screen bg-[#F1F5F9]">
      <Navbar
        userName={user?.name || "Usuario"}
        userRole={
          user?.role ? USER_ROLE_DISPLAY_LABELS[user.role] : "Cliente"
        }
        avatarUrl={user?.avatar_url}
        notificationCount={3}
        onLogout={handleLogout}
        onSettings={() => navigate(ROUTES.SETTINGS)}
        onProfile={() => navigate(ROUTES.PROFILE)}
        onToggleSidebar={handleToggleSidebar}
        showSidebar={showSidebar}
      />

      <div className="flex">
        {showSidebar && <Sidebar isExpanded={isSidebarExpanded} />}

        <main className="flex-1 p-8">
          <Outlet />
        </main>
      </div>

      <Toaster />
    </div>
  );
}
