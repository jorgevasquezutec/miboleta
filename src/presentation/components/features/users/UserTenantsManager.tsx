import { useState } from 'react';
import { ConfirmDialog } from '@/presentation/components/shared/ConfirmDialog';
import { TenantAssociation } from '@/core/domain/entities/User';
import { Tenant } from '@/core/domain/entities/Tenant';
import { Button } from '@/presentation/components/ui/button';
import { Badge } from '@/presentation/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/presentation/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/presentation/components/ui/select';
import { Building2, Star, Plus, Trash2, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { showApiError } from '@/presentation/utils/showApiError';

interface UserTenantsManagerProps {
    userTenants: TenantAssociation[];
    availableTenants: Tenant[];
    onSetPrimary: (tenantId: string) => Promise<void>;
    onAddTenant: (tenantId: string) => Promise<void>;
    onRemoveTenant: (tenantId: string) => Promise<void>;
    isLoading?: boolean;
    canEdit?: boolean;
}

/**
 * UserTenantsManager - Manage user's tenant associations
 */
export function UserTenantsManager({
    userTenants,
    availableTenants,
    onSetPrimary,
    onAddTenant,
    onRemoveTenant,
    isLoading = false,
    canEdit = true
}: UserTenantsManagerProps) {
    const [selectedTenantToAdd, setSelectedTenantToAdd] = useState<string>('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [tenantToRemove, setTenantToRemove] = useState<{ id: string; name: string } | null>(null);

    // Filter out tenants already assigned
    const assignedTenantIds = userTenants.map(t => t.id);
    const tenantsToAdd = availableTenants.filter(t => !assignedTenantIds.includes(t.id));

    const handleSetPrimary = async (tenantId: string) => {
        setActionLoading(`primary-${tenantId}`);
        try {
            await onSetPrimary(tenantId);
            toast.success('Tenant primario actualizado');
        } catch (error) {
            showApiError(error, 'Error al actualizar tenant primario');
        } finally {
            setActionLoading(null);
        }
    };

    const handleAddTenant = async () => {
        if (!selectedTenantToAdd) return;

        setActionLoading('add');
        try {
            await onAddTenant(selectedTenantToAdd);
            setSelectedTenantToAdd('');
            toast.success('Tenant agregado exitosamente');
        } catch (error) {
            showApiError(error, 'Error al agregar tenant');
        } finally {
            setActionLoading(null);
        }
    };

    const handleRemoveTenant = (tenantId: string, tenantName: string, isPrimary: boolean) => {
        if (isPrimary) {
            toast.error('No puedes eliminar el tenant primario. Asigna otro como primario primero.');
            return;
        }
        setTenantToRemove({ id: tenantId, name: tenantName });
        setIsConfirmOpen(true);
    };

    const confirmRemove = async () => {
        if (!tenantToRemove) return;

        setActionLoading(`remove-${tenantToRemove.id}`);
        try {
            await onRemoveTenant(tenantToRemove.id);
            toast.success('Tenant removido exitosamente');
        } catch (error) {
            showApiError(error, 'Error al remover tenant');
        } finally {
            setActionLoading(null);
        }
    };

    if (isLoading) {
        return (
            <Card>
                <CardContent className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Building2 className="h-5 w-5" />
                    Organizaciones Asignadas
                </CardTitle>
                <CardDescription>
                    Gestiona las organizaciones a las que pertenece el usuario
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Current tenants list */}
                {userTenants.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 bg-gray-50 rounded-lg border-2 border-dashed">
                        <Building2 className="h-10 w-10 text-gray-300 mb-2" />
                        <p className="text-sm text-gray-500">No tiene organizaciones asignadas</p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {userTenants.map((tenant) => (
                            <div
                                key={tenant.id}
                                className={`
                                    flex items-center justify-between p-3 rounded-lg border
                                    ${tenant.is_primary ? 'bg-blue-50 border-blue-200' : 'bg-gray-50'}
                                `}
                            >
                                <div className="flex items-center gap-3">
                                    {tenant.logo_path ? (
                                        <img
                                            src={tenant.logo_path}
                                            alt={tenant.name}
                                            className="h-10 w-10 rounded object-cover"
                                        />
                                    ) : (
                                        <div className="h-10 w-10 rounded bg-blue-100 flex items-center justify-center">
                                            <Building2 className="h-5 w-5 text-blue-600" />
                                        </div>
                                    )}
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{tenant.name}</span>
                                            {tenant.is_primary && (
                                                <Badge className="bg-blue-100 text-blue-800">
                                                    <Star className="h-3 w-3 mr-1" />
                                                    Primario
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500">RUC: {tenant.ruc}</p>
                                    </div>
                                </div>

                                {canEdit && (
                                    <div className="flex items-center gap-2">
                                        {!tenant.is_primary && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleSetPrimary(tenant.id)}
                                                disabled={actionLoading !== null}
                                            >
                                                {actionLoading === `primary-${tenant.id}` ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <>
                                                        <Star className="h-4 w-4 mr-1" />
                                                        Primario
                                                    </>
                                                )}
                                            </Button>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleRemoveTenant(tenant.id, tenant.name, tenant.is_primary)}
                                            disabled={actionLoading !== null || tenant.is_primary}
                                            className={tenant.is_primary ? 'opacity-50' : 'text-red-600 hover:bg-red-50'}
                                        >
                                            {actionLoading === `remove-${tenant.id}` ? (
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                            ) : (
                                                <Trash2 className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Add tenant section */}
                {canEdit && tenantsToAdd.length > 0 && (
                    <div className="flex gap-2 pt-4 border-t">
                        <Select
                            value={selectedTenantToAdd}
                            onValueChange={setSelectedTenantToAdd}
                        >
                            <SelectTrigger className="flex-1">
                                <SelectValue placeholder="Seleccionar organización..." />
                            </SelectTrigger>
                            <SelectContent>
                                {tenantsToAdd.map((tenant) => (
                                    <SelectItem key={tenant.id} value={tenant.id}>
                                        <div className="flex items-center gap-2">
                                            <Building2 className="h-4 w-4" />
                                            {tenant.name} ({tenant.ruc})
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            onClick={handleAddTenant}
                            disabled={!selectedTenantToAdd || actionLoading !== null}
                        >
                            {actionLoading === 'add' ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <>
                                    <Plus className="h-4 w-4 mr-1" />
                                    Agregar
                                </>
                            )}
                        </Button>
                    </div>
                )}

                {canEdit && tenantsToAdd.length === 0 && userTenants.length > 0 && (
                    <p className="text-sm text-gray-500 text-center pt-4 border-t">
                        El usuario ya pertenece a todas las organizaciones disponibles
                    </p>
                )}
            </CardContent>

            {/* Confirm Remove Dialog */}
            <ConfirmDialog
                open={isConfirmOpen}
                onOpenChange={setIsConfirmOpen}
                title="Remover Tenant"
                description={tenantToRemove ? `¿Estás seguro de remover "${tenantToRemove.name}" del usuario?` : ''}
                onConfirm={confirmRemove}
                confirmText="Remover"
                variant="destructive"
            />
        </Card>
    );
}
