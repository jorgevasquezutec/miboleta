import { LogOut, User as UserIcon, Menu } from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/presentation/components/ui/avatar";
import { Button } from "@/presentation/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { TenantSwitcher } from "@/presentation/components/shared/TenantSwitcher";
import { RoleSwitcher } from "@/presentation/components/shared/RoleSwitcher";
import { NotificationBell } from "@/presentation/components/notifications/NotificationBell";
import { useNavigate } from "react-router-dom";
import { User } from "@/core/domain/entities/User";
import { useAuthStore } from "@/presentation/stores/authStore";
import { APP_VERSION, USER_ROLE_DISPLAY_LABELS } from "@/shared/constants";

interface NavbarProps {
  user: User | null;
  notificationCount?: number;
  onLogout?: () => void;
  onToggleSidebar?: () => void;
  isSidebarExpanded?: boolean;
  /**
   * Corrimiento vertical en px desde el borde superior de la ventana.
   * RootLayout lo usa para bajar el navbar cuando el ImpersonationBanner
   * está montado encima (0 en sesión normal). Se aplica con `style`, no con
   * una clase Tailwind, porque el valor es dinámico (no hay forma de generar
   * una clase `top-[Npx]` en build time para un N que solo se conoce en
   * runtime).
   */
  topOffset?: number;
}

export function Navbar({
  user,
  onLogout,
  onToggleSidebar,
  topOffset = 0,
}: NavbarProps) {
  const navigate = useNavigate();
  const currentRole = useAuthStore((state) => state.currentRole);
  const brandingPrimaryColor = "#2563EB";

  const userName = user ? `${user.name || ''} ${user.last_name || ''}`.trim() || user.email : 'Usuario';
  const userRole = currentRole
    ? (USER_ROLE_DISPLAY_LABELS as Record<string, string>)[currentRole] || currentRole
    : (user?.role || 'user');

  // Get initials safely
  const getInitials = (name: string): string => {
    if (!name) return 'U';
    return name
      .split(" ")
      .filter(n => n.length > 0)
      .map((n) => n[0])
      .join("")
      .toUpperCase()
      .slice(0, 2) || 'U';
  };

  const handleProfile = () => {
    navigate('/profile');
  };

  return (
    <nav
      className="fixed left-0 right-0 bg-white border-b border-[rgba(0,0,0,0.1)] px-3 sm:px-6 py-2 sm:py-3 z-50"
      style={{ top: topOffset }}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          {/* Sidebar Toggle Button */}
          {onToggleSidebar && (
            <button
              type="button"
              onClick={onToggleSidebar}
              className="p-2 -ml-2 rounded-lg hover:bg-[#F1F5F9] transition-colors"
              title="Toggle sidebar"
            >
              <Menu className="w-5 h-5" style={{ color: brandingPrimaryColor }} />
            </button>
          )}

          {/* Selector único de empresa activa + rol activo
              (currentTenant/currentRole). Tanto root como no-root usan el
              TenantSwitcher como control único de empresa: fija la empresa
              activa y sincroniza el filtro de datos (tenantFilterStore,
              X-Tenant-Ids). Visible también en móvil. RoleSwitcher no aplica
              a root (retorna null). */}
          <div className="flex items-center gap-2 md:pl-2 md:ml-2 md:border-l md:border-[rgba(0,0,0,0.1)]">
            <TenantSwitcher />
            <RoleSwitcher />
          </div>
        </div>

        {/* Right Section */}
        <div className="flex items-center gap-4">
          {/* Notifications */}
          <NotificationBell />

          {/* User Menu */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="gap-2 sm:gap-3 pl-1 sm:pl-2">
                <Avatar className="ring-2 ring-gray-100 h-8 w-8 sm:h-10 sm:w-10">
                  <AvatarImage
                    src={user?.avatar_url || ""}
                    key={user?.avatar_url || 'no-avatar'}
                    className="object-cover"
                  />
                  <AvatarFallback
                    className="text-white text-sm sm:text-base"
                    style={{ backgroundColor: brandingPrimaryColor }}
                  >
                    {getInitials(userName)}
                  </AvatarFallback>
                </Avatar>
                <div className="text-left hidden sm:block">
                  <p className="text-sm">{userName}</p>
                  <p className="text-[#64748B] text-xs capitalize">{userRole}</p>
                </div>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>Mi Cuenta</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={handleProfile}>
                <UserIcon className="w-4 h-4 mr-2" />
                Perfil
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={onLogout} className="text-[#EF4444]">
                <LogOut className="w-4 h-4 mr-2" />
                Cerrar Sesión
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              {/* Informativo, no es un ítem del menú: va como texto plano para
                  que no reciba foco ni se comporte como opción pulsable. */}
              <p className="px-2 py-1.5 text-xs text-[#64748B]">
                Versión {APP_VERSION}
              </p>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </nav>
  );
}

export default Navbar;
