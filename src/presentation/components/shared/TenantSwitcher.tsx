import { Building2, Check, ChevronsUpDown } from "lucide-react";
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

export function TenantSwitcher() {
    const { user, currentTenant, switchTenant } = useAuthStore();

    const handleTenantSwitch = (tenantId: string) => {
        try {
            switchTenant(tenantId);
            const newTenant = user?.tenants?.find(t => t.id === tenantId);
            if (newTenant) {
                toast.success(`Cambiado a ${newTenant.name}`);
            }
        } catch (error) {
            toast.error("Error al cambiar de tenant");
            console.error("Error switching tenant:", error);
        }
    };

    // Single tenant or no tenants - show as static branding
    if (!user?.tenants || user.tenants.length <= 1) {
        const tenantName = currentTenant?.name || "MiBoleta";

        return (
            <div className="flex items-center gap-2">
                {currentTenant?.logo_url ? (
                    <img
                        src={currentTenant.logo_url}
                        alt={tenantName}
                        className="h-10 w-10 rounded-md object-cover flex-shrink-0"
                    />
                ) : (
                    <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                        <Building2 className="h-5 w-5 text-blue-600" />
                    </div>
                )}
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-gray-900">
                        {tenantName}
                    </span>
                    <span className="text-xs text-gray-500">
                        Sistema de Gestión Documental
                    </span>
                </div>
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-[200px] justify-between px-2 h-9 hover:!bg-transparent focus:!bg-transparent active:!bg-transparent data-[state=open]:!bg-transparent"
                >
                    <div className="flex items-center gap-2 min-w-0">
                        {currentTenant?.logo_url ? (
                            <img
                                src={currentTenant.logo_url}
                                alt={currentTenant.name}
                                className="h-6 w-6 rounded-md object-cover flex-shrink-0"
                            />
                        ) : (
                            <div className="flex h-6 w-6 items-center justify-center rounded-md flex-shrink-0">
                                <Building2 className="h-4 w-4 !text-blue-600" />
                            </div>
                        )}
                        <div className="flex flex-col items-start min-w-0 flex-1">
                            <span className="text-sm font-medium !text-gray-900 truncate w-full">
                                {currentTenant?.name || "Seleccionar"}
                            </span>
                            <span className="text-xs !text-gray-500">
                                {currentTenant?.is_primary ? "Principal" : "Secundario"}
                            </span>
                        </div>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 !text-gray-400" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-[240px] !bg-gray-800 !border-gray-700 !text-white shadow-lg"
                sideOffset={5}
            >
                <DropdownMenuLabel className="text-xs text-gray-400 uppercase font-normal px-2 py-1.5">
                    Organizaciones
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="bg-gray-700" />
                {user.tenants.map((tenant, index) => {
                    const isActive = currentTenant?.id === tenant.id;

                    return (
                        <DropdownMenuItem
                            key={tenant.id}
                            onClick={() => handleTenantSwitch(tenant.id)}
                            className={cn(
                                "cursor-pointer px-2 py-2 !text-white hover:!bg-gray-700 hover:!text-white focus:!bg-gray-700 focus:!text-white",
                                isActive && "!bg-gray-700"
                            )}
                        >
                            <div className="flex items-center justify-between w-full gap-2">
                                <div className="flex items-center gap-2 min-w-0 flex-1">
                                    {tenant.logo_url ? (
                                        <img
                                            src={tenant.logo_url}
                                            alt={tenant.name}
                                            className="h-5 w-5 rounded object-cover flex-shrink-0"
                                        />
                                    ) : (
                                        <div className="flex h-5 w-5 items-center justify-center rounded bg-gray-700 flex-shrink-0">
                                            <Building2 className="h-3 w-3 text-gray-400" />
                                        </div>
                                    )}
                                    <span className="text-sm truncate">{tenant.name}</span>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    {tenant.is_primary && (
                                        <span className="text-yellow-500 text-xs">★</span>
                                    )}
                                    <span className="text-xs text-gray-500">⌘{index + 1}</span>
                                    {isActive && (
                                        <Check className="h-4 w-4 text-blue-500" />
                                    )}
                                </div>
                            </div>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default TenantSwitcher;
