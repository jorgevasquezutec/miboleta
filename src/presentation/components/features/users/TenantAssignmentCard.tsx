import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Badge } from '@/presentation/components/ui/badge';
import { Checkbox } from '@/presentation/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/presentation/components/ui/card';
import { SupervisorSelector } from '@/presentation/components/features/users';
import { TenantMultiSelector } from '@/presentation/components/shared/TenantMultiSelector';
import { Building2 } from 'lucide-react';
import { TenantAssociation } from '@/core/domain/entities/User';
import { Role } from '@/core/domain/entities';

/**
 * Configuración por empresa que no vive en TenantAssociation (viene del
 * formulario, no del usuario ya cargado): roles operativos a asignar,
 * fecha de inicio laboral y saldo inicial de vacaciones.
 * Se envía al backend como tenants_config.*.{role_ids,hire_date,vacation_balance_initial}.
 */
export interface TenantExtra {
    roleIds: number[];
    /** YYYY-MM-DD, o '' si no se ha definido */
    hireDate: string;
    /** Días, como string controlado (input), o '' si no se ha definido */
    vacationBalanceInitial: string;
}

export const EMPTY_TENANT_EXTRA: TenantExtra = {
    roleIds: [],
    hireDate: '',
    vacationBalanceInitial: '',
};

interface TenantAssignmentCardProps {
    selectedTenantIds: string[];
    primaryTenantId: string | null;
    selectedTenants: TenantAssociation[];
    supervisorsByTenant: Record<string, string | null>;
    /** Catálogo de roles disponibles para asignar por empresa (sin 'root'). */
    availableRoles: Role[];
    extrasByTenant: Record<string, TenantExtra>;
    excludeUserId?: string;
    error?: string;
    onTenantSelectionChange: (ids: string[]) => void;
    onTenantsChange: (tenants: TenantAssociation[]) => void;
    onPrimaryChange: (id: string | null) => void;
    onSupervisorChange: (tenantId: string, supervisorId: string | null) => void;
    onExtraChange: (tenantId: string, extra: Partial<TenantExtra>) => void;
}

export function TenantAssignmentCard({
    selectedTenantIds,
    primaryTenantId,
    selectedTenants,
    supervisorsByTenant,
    availableRoles,
    extrasByTenant,
    excludeUserId,
    error,
    onTenantSelectionChange,
    onTenantsChange,
    onPrimaryChange,
    onSupervisorChange,
    onExtraChange,
}: TenantAssignmentCardProps) {
    const handleRemoveTenant = (tenantId: string) => {
        const newIds = selectedTenantIds.filter(id => id !== tenantId);
        onTenantSelectionChange(newIds);
        if (tenantId === primaryTenantId && newIds.length > 0) {
            onPrimaryChange(newIds[0]);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Building2 className="h-5 w-5" />
                    Organizaciones, Roles y Supervisores *
                </CardTitle>
                <CardDescription>
                    Busca y selecciona organizaciones. Luego asigna uno o varios roles, la fecha de
                    inicio laboral, el saldo inicial de vacaciones y un supervisor para cada una.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <Label className="text-sm font-medium mb-2 block">Buscar organizaciones</Label>
                    <TenantMultiSelector
                        selectedTenantIds={selectedTenantIds}
                        onSelectionChange={onTenantSelectionChange}
                        onTenantsChange={onTenantsChange}
                        primaryTenantId={primaryTenantId}
                        onPrimaryChange={onPrimaryChange}
                        selectedTenants={selectedTenants}
                        minSelections={1}
                        error={error}
                        hideSelectedTags={true}
                    />
                </div>

                {selectedTenantIds.length > 0 && (
                    <div className="space-y-3">
                        <Label className="text-sm font-medium text-gray-700">
                            Organizaciones seleccionadas ({selectedTenantIds.length})
                        </Label>
                        {selectedTenants
                            .filter(t => selectedTenantIds.includes(String(t.id)))
                            .map(tenant => {
                                const isPrimary = String(tenant.id) === primaryTenantId;
                                return (
                                    <TenantItem
                                        key={tenant.id}
                                        tenant={tenant}
                                        isPrimary={isPrimary}
                                        supervisorId={supervisorsByTenant[String(tenant.id)]}
                                        availableRoles={availableRoles}
                                        extra={extrasByTenant[String(tenant.id)] ?? EMPTY_TENANT_EXTRA}
                                        excludeUserId={excludeUserId}
                                        canSetPrimary={selectedTenantIds.length >= 1}
                                        onSetPrimary={() => onPrimaryChange(String(tenant.id))}
                                        onRemove={() => handleRemoveTenant(String(tenant.id))}
                                        onSupervisorChange={(supervisorId) =>
                                            onSupervisorChange(String(tenant.id), supervisorId)
                                        }
                                        onExtraChange={(extra) => onExtraChange(String(tenant.id), extra)}
                                    />
                                );
                            })}
                    </div>
                )}

                {selectedTenantIds.length === 0 && (
                    <p className="text-sm text-gray-500 text-center py-4">
                        No hay organizaciones seleccionadas. Usa el buscador para agregar.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

interface TenantItemProps {
    tenant: TenantAssociation;
    isPrimary: boolean;
    supervisorId: string | null | undefined;
    availableRoles: Role[];
    extra: TenantExtra;
    excludeUserId?: string;
    canSetPrimary: boolean;
    onSetPrimary: () => void;
    onRemove: () => void;
    onSupervisorChange: (supervisorId: string | null) => void;
    onExtraChange: (extra: Partial<TenantExtra>) => void;
}

function TenantItem({
    tenant,
    isPrimary,
    supervisorId,
    availableRoles,
    extra,
    excludeUserId,
    canSetPrimary,
    onSetPrimary,
    onRemove,
    onSupervisorChange,
    onExtraChange,
}: TenantItemProps) {
    const toggleRole = (roleId: number) => {
        const next = extra.roleIds.includes(roleId)
            ? extra.roleIds.filter(id => id !== roleId)
            : [...extra.roleIds, roleId];
        onExtraChange({ roleIds: next });
    };

    return (
        <div
            className={`p-4 rounded-lg border ${
                isPrimary ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'
            }`}
        >
            <div className="flex items-start justify-between gap-3 mb-3">
                <div className="flex items-center gap-2 flex-1 min-w-0">
                    <Building2 className="h-4 w-4 text-gray-400 shrink-0" />
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                            <span className="font-medium text-gray-900">{tenant.name}</span>
                            {isPrimary && (
                                <Badge className="bg-blue-600 text-white text-xs shrink-0">
                                    Primaria
                                </Badge>
                            )}
                        </div>
                        <p className="text-xs text-gray-500 mt-0.5">RUC: {tenant.ruc}</p>
                    </div>
                </div>

                <div className="flex items-center gap-1 shrink-0">
                    {!isPrimary && canSetPrimary && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onSetPrimary}
                            className="h-8 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                        >
                            Marcar primaria
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onRemove}
                        className="h-8 w-8 text-gray-400 hover:text-red-600 hover:bg-red-50"
                    >
                        ×
                    </Button>
                </div>
            </div>

            <div className="space-y-3">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-gray-600">
                        Roles en esta empresa *
                    </Label>
                    {availableRoles.length === 0 ? (
                        <p className="text-xs text-gray-400">Cargando catálogo de roles...</p>
                    ) : (
                        <div className="flex flex-wrap gap-3 p-2 bg-white rounded-md border border-gray-200">
                            {availableRoles.map(role => (
                                <label
                                    key={role.id}
                                    className="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer"
                                >
                                    <Checkbox
                                        checked={extra.roleIds.includes(role.id)}
                                        onCheckedChange={() => toggleRole(role.id)}
                                    />
                                    {role.display_name}
                                </label>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium text-gray-600">
                            Fecha de inicio laboral
                        </Label>
                        <Input
                            type="date"
                            value={extra.hireDate}
                            onChange={(e) => onExtraChange({ hireDate: e.target.value })}
                            className="bg-white"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium text-gray-600">
                            Saldo inicial de vacaciones (días)
                        </Label>
                        <Input
                            type="number"
                            min="0"
                            step="0.5"
                            placeholder="0"
                            value={extra.vacationBalanceInitial}
                            onChange={(e) => onExtraChange({ vacationBalanceInitial: e.target.value })}
                            className="bg-white"
                        />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-gray-600">
                        Supervisor / Jefe inmediato
                    </Label>
                    <SupervisorSelector
                        value={supervisorId ?? null}
                        onChange={onSupervisorChange}
                        excludeUserId={excludeUserId}
                        tenantIds={[String(tenant.id)]}
                        placeholder="Seleccionar supervisor..."
                    />
                </div>
            </div>
        </div>
    );
}
