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
import { NotificationBell } from "@/presentation/components/notifications/NotificationBell";
import { useNavigate } from "react-router-dom";
import { User } from "@/core/domain/entities/User";

interface NavbarProps {
  user: User | null;
  notificationCount?: number;
  onLogout?: () => void;
  onToggleSidebar?: () => void;
  isSidebarExpanded?: boolean;
}

export function Navbar({
  user,
  onLogout,
  onToggleSidebar,
}: NavbarProps) {
  const navigate = useNavigate();
  const brandingPrimaryColor = "#2563EB";

  const userName = user ? `${user.name || ''} ${user.last_name || ''}`.trim() || user.email : 'Usuario';
  const userRole = user?.role || 'user';

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
    <nav className="fixed top-0 left-0 right-0 bg-white border-b border-[rgba(0,0,0,0.1)] px-6 py-4 z-50">
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

          {/* Tenant Switcher */}
          <TenantSwitcher />
        </div>

        {/* Right Section */}
        <div className="flex items-center gap-4">
          {/* Notifications */}
          <NotificationBell />

          {/* User Menu */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="gap-3 pl-2">
                <Avatar>
                  <AvatarImage
                    src={user?.avatar_url || ""}
                    key={user?.avatar_url || 'no-avatar'}
                  />
                  <AvatarFallback
                    className="text-white"
                    style={{ backgroundColor: brandingPrimaryColor }}
                  >
                    {getInitials(userName)}
                  </AvatarFallback>
                </Avatar>
                <div className="text-left">
                  <p>{userName}</p>
                  <p className="text-[#64748B] text-sm capitalize">{userRole}</p>
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
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </nav>
  );
}

export default Navbar;
