import { useEffect, useState } from "react";
import { Building2, Check, ChevronsUpDown, Globe2, Loader2 } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useTenantFilterActions, useTenantFilterStore } from "@/presentation/stores/tenantFilterStore";
import { Tenant, TenantAssociation } from "@/core/domain/entities";
import { tenantRepository } from "@/infrastructure/persistence/repositories";
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

/**
 * Root no tiene fila en `user_tenants` (es global, "god mode"), así que
 * `user.tenants` normalmente viene vacío para él. Para poder elegir una
 * empresa igual, convertimos el catálogo completo (Tenant, del CRUD de
 * organizaciones) a la forma TenantAssociation que espera el authStore.
 */
function toRootTenantAssociation(tenant: Tenant): TenantAssociation {
    return {
        id: tenant.id,
        name: tenant.name,
        ruc: tenant.ruc,
        logo_url: tenant.logo_url,
        is_primary: false,
        supervisor_id: null,
        roles: ["root"],
        role: "root",
    };
}

/**
 * Switcher de empresa activa (contexto de sesión: currentTenant/currentRole
 * en authStore). Es el único control de empresa en el Navbar.
 *
 * - No-root: lista `user.tenants` (las empresas a las que pertenece). Si
 *   tiene 1 o 0, se muestra estático (sin dropdown). El filtro de datos
 *   (tenantFilterStore, X-Tenant-Ids) lo maneja TenantMultiSwitcher aparte.
 * - Root: "god mode". Ve el catálogo completo de empresas (no solo las
 *   suyas, porque no tiene ninguna asignada) más una opción "Todas las
 *   empresas" para operar sin scope de empresa. Al elegir, además de fijar
 *   currentTenant, sincroniza tenantFilterStore (X-Tenant-Ids /
 *   X-Tenant-Scope: all) para que listas y dashboard queden filtrados por
 *   la misma empresa — root no tiene un segundo control para eso.
 */
export function TenantSwitcher() {
    const { user, currentTenant, switchTenant } = useAuthStore();
    const isRoot = user?.role === "root";
    const { setFilter } = useTenantFilterActions();

    const [allTenants, setAllTenants] = useState<Tenant[]>([]);
    const [isLoadingTenants, setIsLoadingTenants] = useState(false);

    useEffect(() => {
        if (!isRoot) return;

        let cancelled = false;
        setIsLoadingTenants(true);

        tenantRepository
            .getAll({ per_page: 100, status: "active" })
            .then((response) => {
                if (!cancelled) setAllTenants(response.data);
            })
            .catch((error) => {
                console.error("[TenantSwitcher] Error loading tenant catalog for root:", error);
            })
            .finally(() => {
                if (!cancelled) setIsLoadingTenants(false);
            });

        return () => {
            cancelled = true;
        };
    }, [isRoot]);

    // Root: si currentTenant ya existe (p. ej. restaurado de una sesión
    // previa) pero el filtro global sigue en 'all' (desincronizado, porque
    // antes TenantSwitcher no lo tocaba), sincronizarlo para que listas y
    // dashboard respeten la empresa activa desde el primer render.
    useEffect(() => {
        if (!isRoot || !currentTenant) return;
        if (useTenantFilterStore.getState().filter.mode === "all") {
            setFilter([currentTenant.id], [currentTenant]);
        }
    }, [isRoot, currentTenant, setFilter]);

    // ---- Root: god mode ----
    if (isRoot) {
        const currentLabel = currentTenant ? currentTenant.name : "Todas las empresas";

        const handleRootSwitch = (tenantId: string | null, tenant?: Tenant) => {
            try {
                const association = tenant ? toRootTenantAssociation(tenant) : undefined;
                switchTenant(tenantId, association);

                // Root no tiene un segundo control para el filtro de datos:
                // sincronizar tenantFilterStore con la misma elección para
                // que listas (Usuarios/Documentos) y el dashboard queden
                // filtrados por la empresa activa.
                const allAssociations = allTenants.map(toRootTenantAssociation);
                if (tenantId === null) {
                    setFilter([], allAssociations); // modo 'all' -> X-Tenant-Scope: all
                } else if (association) {
                    setFilter([association.id], allAssociations); // modo 'single' -> X-Tenant-Ids
                }

                toast.success(`Cambiando a ${tenantId === null ? "Todas las empresas" : tenant?.name}...`);
            } catch (error) {
                toast.error("Error al cambiar de empresa");
                console.error("Error switching tenant:", error);
            }
        };

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
                                    {currentTenant ? (
                                        <Building2 className="h-4 w-4 !text-blue-600" />
                                    ) : (
                                        <Globe2 className="h-4 w-4 !text-blue-600" />
                                    )}
                                </div>
                            )}
                            <div className="flex flex-col items-start min-w-0 flex-1">
                                <span className="text-sm font-medium !text-gray-900 truncate w-full">
                                    {currentLabel}
                                </span>
                                <span className="text-xs !text-gray-500">
                                    {currentTenant ? "Empresa activa" : "Modo global (root)"}
                                </span>
                            </div>
                        </div>
                        {isLoadingTenants ? (
                            <Loader2 className="ml-2 h-4 w-4 shrink-0 animate-spin !text-gray-400" />
                        ) : (
                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 !text-gray-400" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    className="w-[260px] !bg-white !border-gray-200 shadow-xl"
                    sideOffset={5}
                >
                    <DropdownMenuLabel className="text-xs text-gray-500 uppercase font-normal px-2 py-1.5">
                        Organizaciones
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onClick={() => handleRootSwitch(null)}
                        className={cn(
                            "cursor-pointer px-2 py-2 text-gray-900 hover:bg-blue-50 hover:text-gray-900 focus:bg-blue-50 focus:text-gray-900",
                            !currentTenant && "bg-blue-50"
                        )}
                    >
                        <div className="flex items-center justify-between w-full gap-2">
                            <div className="flex items-center gap-2 min-w-0 flex-1">
                                <Globe2 className="h-4 w-4 flex-shrink-0 text-gray-400" />
                                <span className="text-sm truncate">Todas las empresas</span>
                            </div>
                            {!currentTenant && <Check className="h-4 w-4 text-blue-500 flex-shrink-0" />}
                        </div>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    {allTenants.map((tenant) => {
                        const isActive = currentTenant?.id === tenant.id;
                        return (
                            <DropdownMenuItem
                                key={tenant.id}
                                onClick={() => handleRootSwitch(tenant.id, tenant)}
                                className={cn(
                                    "cursor-pointer px-2 py-2 text-gray-900 hover:bg-blue-50 hover:text-gray-900 focus:bg-blue-50 focus:text-gray-900",
                                    isActive && "bg-blue-50"
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
                                            <div className="flex h-5 w-5 items-center justify-center rounded bg-gray-100 flex-shrink-0">
                                                <Building2 className="h-3 w-3 text-gray-400" />
                                            </div>
                                        )}
                                        <span className="text-sm truncate">{tenant.name}</span>
                                    </div>
                                    {isActive && <Check className="h-4 w-4 text-blue-500 flex-shrink-0" />}
                                </div>
                            </DropdownMenuItem>
                        );
                    })}
                    {!isLoadingTenants && allTenants.length === 0 && (
                        <div className="px-2 py-2 text-xs text-gray-500">No hay empresas registradas</div>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    // ---- No-root: comportamiento basado en user.tenants ----
    const handleTenantSwitch = (tenantId: string) => {
        try {
            switchTenant(tenantId);
            const newTenant = user?.tenants?.find(t => t.id === tenantId);
            if (newTenant) {
                toast.success(`Cambiando a ${newTenant.name}...`);
            }
        } catch (error) {
            toast.error("Error al cambiar de empresa");
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
                className="w-[240px] !bg-white !border-gray-200 shadow-xl"
                sideOffset={5}
            >
                <DropdownMenuLabel className="text-xs text-gray-500 uppercase font-normal px-2 py-1.5">
                    Organizaciones
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {user.tenants.map((tenant, index) => {
                    const isActive = currentTenant?.id === tenant.id;

                    return (
                        <DropdownMenuItem
                            key={tenant.id}
                            onClick={() => handleTenantSwitch(tenant.id)}
                            className={cn(
                                "cursor-pointer px-2 py-2 text-gray-900 hover:bg-blue-50 hover:text-gray-900 focus:bg-blue-50 focus:text-gray-900",
                                isActive && "bg-blue-50"
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
                                        <div className="flex h-5 w-5 items-center justify-center rounded bg-gray-100 flex-shrink-0">
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
