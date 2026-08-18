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
import { FieldError } from '@/presentation/components/shared/FieldError';
import { Building2 } from 'lucide-react';
import { TenantAssociation } from '@/core/domain/entities/User';
import { Role } from '@/core/domain/entities';
import { useAuthStore } from '@/presentation/stores/authStore';
import { ASSIGNABLE_ROLES_BY_ACTOR } from '@/shared/constants';

/**
 * Roles que un usuario autenticado con los roles `actorRoleNames` (los que
 * tiene EN LA EMPRESA que se está configurando, no su rol global) puede
 * asignar a otro usuario en esa empresa. Espeja la misma jerarquía RBAC que
 * UserService::MANAGEABLE_ROLES/assignableRoleNamesFor en el backend, vía
 * ASSIGNABLE_ROLES_BY_ACTOR (fuente única compartida con UsersListPage):
 * - admin_tenant: puede asignar admin, aprobador, client (no admin_tenant ni root).
 * - admin: puede asignar aprobador, client (no admin ni admin_tenant).
 * - cualquier otro caso (o ninguno): no puede asignar roles operativos aquí
 *   (no debería ocurrir: solo root/admin/admin_tenant llegan a este formulario).
 */
function assignableRoleNamesFor(actorRoleNames: string[]): string[] {
    // Recorre las claves del mapa EN SU ORDEN DE DECLARACIÓN (admin_tenant
    // antes que admin), no el de actorRoleNames: preserva la precedencia de
    // la jerarquía RBAC aunque un usuario llegue a tener varios roles en la
    // misma empresa.
    for (const actorRole of Object.keys(ASSIGNABLE_ROLES_BY_ACTOR)) {
        if (actorRoleNames.includes(actorRole)) {
            return ASSIGNABLE_ROLES_BY_ACTOR[actorRole];
        }
    }
    return [];
}

/**
 * Configuración por empresa que no vive en TenantAssociation (viene del
 * formulario, no del usuario ya cargado): roles operativos a asignar,
 * fecha de inicio laboral, saldo inicial de vacaciones y departamento/cargo.
 * Se envía al backend como
 * tenants_config.*.{role_ids,hire_date,vacation_balance_initial,department,position}.
 */
export interface TenantExtra {
    roleIds: number[];
    /** YYYY-MM-DD, o '' si no se ha definido */
    hireDate: string;
    /** Días, como string controlado (input), o '' si no se ha definido */
    vacationBalanceInitial: string;
    /** Departamento/área del usuario en esta empresa, o '' si no se ha definido */
    department: string;
    /** Cargo/puesto del usuario en esta empresa, o '' si no se ha definido */
    position: string;
}

export const EMPTY_TENANT_EXTRA: TenantExtra = {
    roleIds: [],
    hireDate: '',
    vacationBalanceInitial: '',
    department: '',
    position: '',
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
    /** Error de validación de CLIENTE (p.ej. "sin organizaciones seleccionadas"), no del backend. */
    error?: string;
    /**
     * Errores de validación del BACKEND por empresa: tenantId -> campo del
     * backend (`hire_date`, `role_ids`, `vacation_balance_initial`,
     * `supervisor_id`, `department`, `position`) -> mensaje. Viene de
     * `useFormErrors.nestedErrors`, ya reagrupado desde `tenants_config.{i}.*`.
     */
    fieldErrorsByTenant?: Record<string, Record<string, string>>;
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
    fieldErrorsByTenant,
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
                                        fieldErrors={fieldErrorsByTenant?.[String(tenant.id)]}
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
    /** Errores del backend para ESTA empresa, con las claves tal cual las manda (ver `fieldErrorsByTenant`). */
    fieldErrors?: Record<string, string>;
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
    fieldErrors,
    excludeUserId,
    canSetPrimary,
    onSetPrimary,
    onRemove,
    onSupervisorChange,
    onExtraChange,
}: TenantItemProps) {
    const { user: actor } = useAuthStore();

    const toggleRole = (roleId: number) => {
        const next = extra.roleIds.includes(roleId)
            ? extra.roleIds.filter(id => id !== roleId)
            : [...extra.roleIds, roleId];
        onExtraChange({ roleIds: next });
    };

    /**
     * Roles visibles para asignar en ESTA empresa: root ve el catálogo
     * completo (ya sin 'root', filtrado por el caller); el resto se filtra
     * según el rol del usuario autenticado (`actor`) EN ESTA empresa
     * específica (no su rol global, ni el de la empresa activa del
     * TenantSwitcher — puede estar editando un usuario de otra empresa a la
     * que también pertenece con un rol distinto).
     */
    const visibleRoles = (() => {
        if (!actor || actor.role === 'root') {
            return availableRoles;
        }

        const actorTenant = actor.tenants?.find(t => String(t.id) === String(tenant.id));
        const actorRoleNames =
            actorTenant?.roles && actorTenant.roles.length > 0
                ? actorTenant.roles
                : actorTenant?.role
                    ? [actorTenant.role]
                    : [];

        const allowedNames = assignableRoleNamesFor(actorRoleNames);
        return availableRoles.filter(role => allowedNames.includes(role.name));
    })();

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
                    ) : visibleRoles.length === 0 ? (
                        <p className="text-xs text-gray-400">
                            No tienes permisos para asignar roles en esta empresa.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-3 p-2 bg-white rounded-md border border-gray-200">
                            {visibleRoles.map(role => (
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
                    <FieldError message={fieldErrors?.role_ids} />
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
                            className={fieldErrors?.hire_date ? 'bg-white border-red-500' : 'bg-white'}
                        />
                        <FieldError message={fieldErrors?.hire_date} />
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
                            className={fieldErrors?.vacation_balance_initial ? 'bg-white border-red-500' : 'bg-white'}
                        />
                        <FieldError message={fieldErrors?.vacation_balance_initial} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium text-gray-600">
                            Departamento
                        </Label>
                        <Input
                            type="text"
                            placeholder="Ej: Sistemas"
                            value={extra.department}
                            onChange={(e) => onExtraChange({ department: e.target.value })}
                            className={fieldErrors?.department ? 'bg-white border-red-500' : 'bg-white'}
                        />
                        <FieldError message={fieldErrors?.department} />
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-xs font-medium text-gray-600">
                            Cargo
                        </Label>
                        <Input
                            type="text"
                            placeholder="Ej: Analista Programador"
                            value={extra.position}
                            onChange={(e) => onExtraChange({ position: e.target.value })}
                            className={fieldErrors?.position ? 'bg-white border-red-500' : 'bg-white'}
                        />
                        <FieldError message={fieldErrors?.position} />
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
                    <FieldError message={fieldErrors?.supervisor_id} />
                </div>
            </div>
        </div>
    );
}
