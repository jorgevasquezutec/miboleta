import { Bell, Settings, LogOut, User, Building2 } from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "./ui/avatar";
import { Button } from "./ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";
import { Badge } from "./ui/badge";
import { useTenant } from "../contexts/TenantContext";

interface NavbarProps {
  userName: string;
  userRole: string;
  companyName: string;
  companyLogo?: string;
  notificationCount?: number;
  onLogout?: () => void;
  onSettings?: () => void;
  onProfile?: () => void;
}

export function Navbar({
  userName,
  userRole,
  companyName,
  companyLogo,
  notificationCount = 0,
  onLogout,
  onSettings,
  onProfile,
}: NavbarProps) {
  const { tenant } = useTenant();
  
  // Use tenant branding for colors and logo
  const brandingPrimaryColor = tenant.branding.primaryColor;
  const brandingSecondaryColor = tenant.branding.secondaryColor;
  const brandingLogo = tenant.branding.logoUrl || companyLogo;
  const displayName = tenant.name || companyName;

  return (
    <nav className="bg-white border-b border-[rgba(0,0,0,0.1)] px-6 py-4">
      <div className="flex items-center justify-between max-w-[1400px] mx-auto">
        {/* Logo and Company Name */}
        <div className="flex items-center gap-3">
          {brandingLogo ? (
            <img src={brandingLogo} alt={displayName} className="h-10 object-contain" />
          ) : (
            <div 
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ backgroundColor: brandingPrimaryColor }}
            >
              <Building2 className="w-6 h-6 text-white" />
            </div>
          )}
          <div>
            <h1 style={{ color: brandingSecondaryColor }}>{displayName}</h1>
            <p className="text-[#64748B]">Sistema de Gestión Documental</p>
          </div>
        </div>

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
                  <AvatarImage src="" />
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
              <DropdownMenuItem onClick={onSettings}>
                <Settings className="w-4 h-4 mr-2" />
                Configuración
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
