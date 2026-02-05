import { Button } from '@/presentation/components/ui/button';
import { Label } from '@/presentation/components/ui/label';
import { Badge } from '@/presentation/components/ui/badge';
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

interface TenantAssignmentCardProps {
    selectedTenantIds: string[];
    primaryTenantId: string | null;
    selectedTenants: TenantAssociation[];
    supervisorsByTenant: Record<string, string | null>;
    excludeUserId?: string;
    error?: string;
    onTenantSelectionChange: (ids: string[]) => void;
    onTenantsChange: (tenants: TenantAssociation[]) => void;
    onPrimaryChange: (id: string | null) => void;
    onSupervisorChange: (tenantId: string, supervisorId: string | null) => void;
}

export function TenantAssignmentCard({
    selectedTenantIds,
    primaryTenantId,
    selectedTenants,
    supervisorsByTenant,
    excludeUserId,
    error,
    onTenantSelectionChange,
    onTenantsChange,
    onPrimaryChange,
    onSupervisorChange,
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
                    Organizaciones y Supervisores *
                </CardTitle>
                <CardDescription>
                    Busca y selecciona organizaciones. Luego asigna un supervisor para cada una.
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
                                        excludeUserId={excludeUserId}
                                        canSetPrimary={selectedTenantIds.length >= 1}
                                        onSetPrimary={() => onPrimaryChange(String(tenant.id))}
                                        onRemove={() => handleRemoveTenant(String(tenant.id))}
                                        onSupervisorChange={(supervisorId) =>
                                            onSupervisorChange(String(tenant.id), supervisorId)
                                        }
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
    excludeUserId?: string;
    canSetPrimary: boolean;
    onSetPrimary: () => void;
    onRemove: () => void;
    onSupervisorChange: (supervisorId: string | null) => void;
}

function TenantItem({
    tenant,
    isPrimary,
    supervisorId,
    excludeUserId,
    canSetPrimary,
    onSetPrimary,
    onRemove,
    onSupervisorChange,
}: TenantItemProps) {
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
    );
}
