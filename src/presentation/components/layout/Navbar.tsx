import { Bell, Settings, LogOut, User } from "lucide-react";
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
import { Badge } from "@/presentation/components/ui/badge";
import { TenantSwitcher } from "@/presentation/components/shared/TenantSwitcher";

interface NavbarProps {
  userName: string;
  userRole: string;
  avatarUrl?: string;
  notificationCount?: number;
  onLogout?: () => void;
  onSettings?: () => void;
  onProfile?: () => void;
}

export function Navbar({
  userName,
  userRole,
  avatarUrl,
  notificationCount = 0,
  onLogout,
  onSettings,
  onProfile,
}: NavbarProps) {
  const brandingPrimaryColor = "#2563EB";

  return (
    <nav className="bg-white border-b border-[rgba(0,0,0,0.1)] px-6 py-4">
      <div className="flex items-center justify-between">
        {/* Tenant Switcher replaces Logo and Company Name */}
        <TenantSwitcher />

        {/* Right Section */}
        <div className="flex items-center gap-4">
          {/* Notifications */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="relative">
                <Bell className="w-5 h-5" />
                {notificationCount > 0 && (
                  <Badge className="absolute -top-1 -right-1 w-5 h-5 p-0 flex items-center justify-center bg-[#EF4444] text-white">
                    {notificationCount}
                  </Badge>
                )}
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
              <DropdownMenuLabel>Notificaciones</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <div className="p-4 text-center text-[#64748B]">
                No tienes notificaciones nuevas
              </div>
            </DropdownMenuContent>
          </DropdownMenu>

          {/* User Menu */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="gap-3 pl-2">
                <Avatar>
                  <AvatarImage src={avatarUrl || ""} />
                  <AvatarFallback
                    className="text-white"
                    style={{ backgroundColor: brandingPrimaryColor }}
                  >
                    {userName
                      .split(" ")
                      .map((n) => n[0])
                      .join("")
                      .toUpperCase()
                      .slice(0, 2)}
                  </AvatarFallback>
                </Avatar>
                <div className="text-left">
                  <p>{userName}</p>
                  <p className="text-[#64748B]">{userRole}</p>
                </div>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>Mi Cuenta</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={onProfile}>
                <User className="w-4 h-4 mr-2" />
                Perfil
              </DropdownMenuItem>
              {/* <DropdownMenuItem onClick={onSettings}>
                <Settings className="w-4 h-4 mr-2" />
                Configuración
              </DropdownMenuItem> */}
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
