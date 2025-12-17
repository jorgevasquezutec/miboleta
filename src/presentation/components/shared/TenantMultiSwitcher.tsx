import { Building2, Check, ChevronsUpDown, Loader2 } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import {
    useTenantFilterStore,
    useTenantFilterActions,
    useTenantFilterSelectors,
} from "@/presentation/stores/tenantFilterStore";
import { Button } from "@/presentation/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { Checkbox } from "@/presentation/components/ui/checkbox";
import { Badge } from "@/presentation/components/ui/badge";
import { toast } from "sonner";
import { cn } from "@/presentation/components/ui/utils";
import { useState, useCallback, useMemo } from "react";
import { queryClient } from "@/presentation/providers/QueryProvider";

/**
 * Componente optimizado de selección múltiple de tenants (empresas)
 * 
 * Features:
 * - ✅ Selección múltiple con checkboxes
 * - ✅ Controles rápidos (Todas/Ninguna)
 * - ✅ Indicadores visuales del filtro activo
 * - ✅ Invalidación automática de cache al cambiar filtro
 * - ✅ Performance optimizada con memoización
 * - ✅ Muestra logo y nombre de empresas
 * - ✅ Marca empresas primarias
 * 
 * Estados posibles:
 * - Sin tenants: Muestra branding estático
 * - 1 tenant: Muestra info estática (no dropdown)
 * - 2+ tenants: Muestra dropdown con selección múltiple
 * 
 * @example
 * ```tsx
 * // En Navbar o cualquier componente
 * <TenantMultiSwitcher />
 * ```
 */
export function TenantMultiSwitcher() {
    const { user } = useAuthStore();
    const { filter } = useTenantFilterStore();
    const { setFilter } = useTenantFilterActions();
    const { getFilterDisplayText } = useTenantFilterSelectors();

    // Estado local para selección temporal (mientras el dropdown está abierto)
    const [tempSelection, setTempSelection] = useState<string[]>(
        filter.tenantIds
    );
    const [isOpen, setIsOpen] = useState(false);
    const [isApplying, setIsApplying] = useState(false);

    // ✅ OPTIMIZACIÓN: Memoizar tenants disponibles
    const availableTenants = useMemo(() => {
        return user?.tenants || [];
    }, [user?.tenants]);

    // ✅ OPTIMIZACIÓN: Callbacks memoizados
    const handleToggleTenant = useCallback((tenantId: string) => {
        setTempSelection(prev => {
            if (prev.includes(tenantId)) {
                return prev.filter(id => id !== tenantId);
            } else {
                return [...prev, tenantId];
            }
        });
    }, []);

    const handleSelectAll = useCallback(() => {
        const allIds = availableTenants.map(t => String(t.id));
        setTempSelection(allIds);
    }, [availableTenants]);

    const handleClearAll = useCallback(() => {
        setTempSelection([]);
    }, []);

    const handleApply = useCallback(async () => {
        setIsApplying(true);

        try {
            // ✅ Actualizar filtro en el store (ahora con availableTenants)
            setFilter(tempSelection, availableTenants);

            // ✅ OPTIMIZACIÓN: Invalidar todas las queries de React Query
            // Esto fuerza un refetch con el nuevo filtro
            await queryClient.invalidateQueries();

            // Mensajes informativos
            const message = tempSelection.length === 0
                ? "Mostrando todas las empresas"
                : tempSelection.length === 1
                    ? `Filtrando por: ${availableTenants.find(t => String(t.id) === tempSelection[0])?.name}`
                    : `Filtrando por ${tempSelection.length} empresas`;

            toast.success(message, {
                description: 'Los datos se actualizarán automáticamente',
            });

            setIsOpen(false);

            // ✅ Pequeño delay para mejor UX
            setTimeout(() => {
                setIsApplying(false);
            }, 500);
        } catch (error) {
            console.error('[TenantMultiSwitcher] Error applying filter:', error);
            toast.error('Error al aplicar filtro');
            setIsApplying(false);
        }
    }, [tempSelection, setFilter, availableTenants]);

    const handleOpenChange = useCallback((open: boolean) => {
        setIsOpen(open);
        if (open) {
            // Resetear selección temporal al abrir (sincronizar con store)
            setTempSelection(filter.tenantIds);
        }
    }, [filter.tenantIds]);

    // ✅ Si no hay tenants, mostrar branding estático
    if (!availableTenants || availableTenants.length === 0) {
        return (
            <div className="flex items-center gap-2">
                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                    <Building2 className="h-5 w-5 text-blue-600" />
                </div>
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-gray-900">MiBoleta</span>
                    <span className="text-xs text-gray-500">Sistema de Gestión</span>
                </div>
            </div>
        );
    }

    // ✅ Si solo hay 1 tenant, mostrar estático (sin dropdown)
    if (availableTenants.length === 1) {
        const tenant = availableTenants[0];
        return (
            <div className="flex items-center gap-2">
                {tenant.logo_url ? (
                    <img
                        src={tenant.logo_url}
                        alt={tenant.name}
                        className="h-10 w-10 rounded-md object-cover flex-shrink-0"
                    />
                ) : (
                    <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                        <Building2 className="h-5 w-5 text-blue-600" />
                    </div>
                )}
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-gray-900">{tenant.name}</span>
                    <span className="text-xs text-gray-500">
                        {tenant.is_primary ? "Principal" : "Secundario"}
                    </span>
                </div>
            </div>
        );
    }

    // ✅ OPTIMIZACIÓN: Memoizar texto de display
    const displayText = getFilterDisplayText();
    const displaySubtext = filter.mode === 'all'
        ? `${availableTenants.length} disponibles`
        : filter.mode === 'single' && filter.tenants[0]
            ? (filter.tenants[0].is_primary ? "Principal" : "Secundario")
            : "Selección múltiple";

    // ✅ Verificar si hay cambios pendientes
    const hasChanges = useMemo(() => {
        const current = [...filter.tenantIds].sort().join(',');
        const temp = [...tempSelection].sort().join(',');
        return current !== temp;
    }, [filter.tenantIds, tempSelection]);

    return (
        <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-[240px] justify-between px-2 h-auto py-2 hover:!bg-gray-50 transition-colors"
                >
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {filter.mode === 'single' && filter.tenants[0]?.logo_url ? (
                            <img
                                src={filter.tenants[0].logo_url}
                                alt={filter.tenants[0].name}
                                className="h-8 w-8 rounded-md object-cover flex-shrink-0"
                            />
                        ) : (
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                                <Building2 className="h-4 w-4 !text-blue-600" />
                            </div>
                        )}
                        <div className="flex flex-col items-start min-w-0 flex-1">
                            <span className="text-sm font-medium !text-gray-900 truncate w-full">
                                {displayText}
                            </span>
                            <span className="text-xs !text-gray-500">
                                {displaySubtext}
                            </span>
                        </div>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 !text-gray-400" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="start"
                className="w-[300px] !bg-white !border-gray-200 shadow-xl"
                sideOffset={5}
            >
                {/* Header con controles rápidos */}
                <div className="flex items-center justify-between p-2 border-b border-gray-100">
                    <DropdownMenuLabel className="text-xs text-gray-500 uppercase font-semibold p-0">
                        Organizaciones ({availableTenants.length})
                    </DropdownMenuLabel>
                    <div className="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleSelectAll}
                            className="h-7 text-xs hover:bg-blue-50 hover:text-blue-600"
                        >
                            Todas
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleClearAll}
                            className="h-7 text-xs hover:bg-red-50 hover:text-red-600"
                        >
                            Ninguna
                        </Button>
                    </div>
                </div>

                {/* Lista de tenants con checkboxes */}
                <div className="max-h-[360px] overflow-y-auto py-1">
                    {availableTenants.map(tenant => {
                        const isSelected = tempSelection.includes(String(tenant.id));

                        return (
                            <DropdownMenuItem
                                key={tenant.id}
                                onClick={(e) => {
                                    e.preventDefault();
                                    handleToggleTenant(String(tenant.id));
                                }}
                                className={cn(
                                    "cursor-pointer px-3 py-2.5 focus:bg-blue-50 transition-colors",
                                    isSelected && "bg-blue-50"
                                )}
                            >
                                <div className="flex items-center gap-3 w-full">
                                    <Checkbox
                                        checked={isSelected}
                                        onCheckedChange={() => handleToggleTenant(String(tenant.id))}
                                        className="shrink-0"
                                    />

                                    {tenant.logo_url ? (
                                        <img
                                            src={tenant.logo_url}
                                            className="h-6 w-6 rounded object-cover shrink-0"
                                            alt={tenant.name}
                                        />
                                    ) : (
                                        <div className="h-6 w-6 rounded bg-gray-100 flex items-center justify-center shrink-0">
                                            <Building2 className="h-3.5 w-3.5 text-gray-400" />
                                        </div>
                                    )}

                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium text-gray-900 truncate">
                                                {tenant.name}
                                            </span>
                                            {tenant.is_primary && (
                                                <Badge className="bg-yellow-100 text-yellow-700 text-xs px-1.5 py-0 shrink-0">
                                                    ★ Principal
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-gray-500">RUC: {tenant.ruc}</p>
                                    </div>

                                    {isSelected && (
                                        <Check className="h-4 w-4 text-blue-600 shrink-0" />
                                    )}
                                </div>
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <DropdownMenuSeparator className="my-1" />

                {/* Footer con contador y botón aplicar */}
                <div className="p-2 flex items-center justify-between gap-2">
                    <span className="text-xs text-gray-500">
                        {tempSelection.length === 0
                            ? "Ninguna seleccionada"
                            : tempSelection.length === availableTenants.length
                                ? "Todas seleccionadas"
                                : `${tempSelection.length} de ${availableTenants.length}`}
                    </span>
                    <Button
                        onClick={handleApply}
                        size="sm"
                        className="h-8"
                        disabled={!hasChanges || isApplying}
                    >
                        {isApplying ? (
                            <>
                                <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                                Aplicando...
                            </>
                        ) : (
                            <>
                                Aplicar
                                {hasChanges && <Badge className="ml-1 bg-blue-600 text-white text-xs px-1">•</Badge>}
                            </>
                        )}
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default TenantMultiSwitcher;
