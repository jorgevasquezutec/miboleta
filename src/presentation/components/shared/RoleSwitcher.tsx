import { useMemo } from "react";
import { Check, ChevronsUpDown, ShieldCheck } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { Button } from "@/presentation/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { toast } from "sonner";
import { cn } from "@/presentation/components/ui/utils";
import { USER_ROLE_DISPLAY_LABELS } from "@/shared/constants";

function roleLabel(role: string): string {
    return (USER_ROLE_DISPLAY_LABELS as Record<string, string>)[role] || role;
}

/**
 * Switcher de rol activo (contexto de sesión), scoped a la empresa
 * actualmente seleccionada en TenantSwitcher.
 *
 * No se muestra para root: root es global y su rol siempre es 'root',
 * sin importar la empresa activa (o "Todas las empresas").
 */
export function RoleSwitcher() {
    const { user, currentTenant, currentRole, switchRole } = useAuthStore();

    const availableRoles = useMemo(() => {
        if (!currentTenant) return [];
        if (currentTenant.roles && currentTenant.roles.length > 0) {
            return currentTenant.roles;
        }
        return currentTenant.role ? [currentTenant.role] : [];
    }, [currentTenant]);

    // Root no tiene selector de rol: siempre 'root', a nivel global.
    if (!user || user.role === "root") {
        return null;
    }

    // Sin empresa activa o sin roles asignados (no debería pasar en
    // condiciones normales, pero evitamos romper el render).
    if (!currentTenant || availableRoles.length === 0) {
        return null;
    }

    const handleRoleSwitch = (role: string) => {
        try {
            switchRole(role);
            toast.success(`Ahora estás actuando como ${roleLabel(role)}`);
        } catch (error) {
            toast.error("Error al cambiar de rol");
            console.error("Error switching role:", error);
        }
    };

    // Un solo rol disponible en esta empresa: chip estático, sin dropdown
    if (availableRoles.length <= 1) {
        return (
            <div className="flex items-center gap-2 px-2 h-9">
                <div className="flex h-6 w-6 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                    <ShieldCheck className="h-4 w-4 text-blue-600" />
                </div>
                <span className="text-sm font-medium text-gray-900 truncate max-w-[160px]">
                    {roleLabel(availableRoles[0])}
                </span>
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-[180px] justify-between px-2 h-9 hover:!bg-transparent focus:!bg-transparent active:!bg-transparent data-[state=open]:!bg-transparent"
                >
                    <div className="flex items-center gap-2 min-w-0">
                        <div className="flex h-6 w-6 items-center justify-center rounded-md flex-shrink-0">
                            <ShieldCheck className="h-4 w-4 !text-blue-600" />
                        </div>
                        <span className="text-sm font-medium !text-gray-900 truncate w-full">
                            {currentRole ? roleLabel(currentRole) : "Seleccionar rol"}
                        </span>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 !text-gray-400" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-[220px] !bg-white !border-gray-200 shadow-xl"
                sideOffset={5}
            >
                <DropdownMenuLabel className="text-xs text-gray-500 uppercase font-normal px-2 py-1.5">
                    Rol activo en {currentTenant.name}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {availableRoles.map((role) => {
                    const isActive = currentRole === role;
                    return (
                        <DropdownMenuItem
                            key={role}
                            onClick={() => handleRoleSwitch(role)}
                            className={cn(
                                "cursor-pointer px-2 py-2 text-gray-900 hover:bg-blue-50 hover:text-gray-900 focus:bg-blue-50 focus:text-gray-900",
                                isActive && "bg-blue-50"
                            )}
                        >
                            <div className="flex items-center justify-between w-full gap-2">
                                <span className="text-sm truncate">{roleLabel(role)}</span>
                                {isActive && <Check className="h-4 w-4 text-blue-500 flex-shrink-0" />}
                            </div>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default RoleSwitcher;
