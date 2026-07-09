import { useEffect, useState, useCallback } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate, useParams } from 'react-router-dom';
import { userRepository, roleRepository } from '@/infrastructure/persistence/repositories';
import { useAuthStore } from '@/presentation/stores/authStore';
import { Button } from '@/presentation/components/ui/button';
import {
    PersonalInfoCard,
    RoleStatusCard,
    TenantAssignmentCard,
} from '@/presentation/components/features/users';
import { EMPTY_TENANT_EXTRA, TenantExtra } from '@/presentation/components/features/users/TenantAssignmentCard';
import { ArrowLeft, Save, Loader2, UserPlus, UserCircle } from 'lucide-react';
import { toast } from 'sonner';
import { TenantAssociation } from '@/core/domain/entities/User';
import { Role } from '@/core/domain/entities';

interface FormData {
    name: string;
    last_name: string;
    email: string;
    document_type: string;
    document_text: string;
    phone: string;
    birth_date: string;
    /**
     * Toggle de alto nivel: 'root' (plataforma, sin empresas) o 'client'
     * (usuario de empresa). Los roles operativos reales por empresa
     * (admin_tenant, admin, client, aprobador) viven en
     * tenantExtras[*].roleIds, no aquí.
     */
    role: 'root' | 'client';
    status: 'active' | 'inactive';
}

const initialFormData: FormData = {
    name: '',
    last_name: '',
    email: '',
    document_type: 'dni',
    document_text: '',
    phone: '',
    birth_date: '',
    role: 'client',
    status: 'active',
};

/**
 * ID convencional del rol 'root' (ver RoleSeeder / UserService::ROOT_ROLE_ID
 * en backend: es el primer rol insertado). Se usa como fallback si por
 * alguna razón el catálogo de /roles no cargó a tiempo.
 */
const ROOT_ROLE_ID_FALLBACK = 1;

export function UserFormPage() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const isEditing = Boolean(id);
    useDocumentTitle(isEditing ? 'Editar Usuario' : 'Nuevo Usuario');
    const { user: currentUser } = useAuthStore();

    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formData, setFormData] = useState<FormData>(initialFormData);

    // Catálogo de roles (para el multi-select por empresa)
    const [availableRoles, setAvailableRoles] = useState<Role[]>([]);

    // Tenant selection state
    const [selectedTenantIds, setSelectedTenantIds] = useState<string[]>([]);
    const [primaryTenantId, setPrimaryTenantId] = useState<string | null>(null);
    const [selectedTenants, setSelectedTenants] = useState<TenantAssociation[]>([]);
    const [supervisorsByTenant, setSupervisorsByTenant] = useState<Record<string, string | null>>({});
    // Roles / fecha de inicio / saldo inicial de vacaciones, por empresa
    const [extrasByTenant, setExtrasByTenant] = useState<Record<string, TenantExtra>>({});

    useEffect(() => {
        roleRepository
            .getAll()
            .then(setAvailableRoles)
            .catch((error) => {
                console.error('Error loading roles catalog:', error);
                toast.error('No se pudo cargar el catálogo de roles');
            });
    }, []);

    useEffect(() => {
        if (isEditing && id) {
            loadUser(id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id, isEditing]);

    const loadUser = async (userId: string) => {
        setIsLoading(true);
        try {
            const user = await userRepository.findById(userId);
            if (user) {
                setFormData({
                    name: user.name || '',
                    last_name: user.last_name || '',
                    email: user.email || '',
                    document_type: user.document_type || 'dni',
                    document_text: user.document_text || '',
                    phone: user.phone || '',
                    birth_date: user.birth_date || '',
                    role: user.role === 'root' ? 'root' : 'client',
                    status: user.status === 'active' ? 'active' : 'inactive',
                });

                if (user.tenants && user.tenants.length > 0) {
                    setSelectedTenantIds(user.tenants.map(t => String(t.id)));
                    setSelectedTenants(user.tenants);

                    const primary = user.tenants.find(t => t.is_primary);
                    setPrimaryTenantId(primary ? String(primary.id) : String(user.tenants[0].id));

                    const supervisors: Record<string, string | null> = {};
                    const extras: Record<string, TenantExtra> = {};
                    user.tenants.forEach(t => {
                        const tenantId = String(t.id);
                        if (t.supervisor_id) {
                            supervisors[tenantId] = String(t.supervisor_id);
                        }
                        extras[tenantId] = {
                            roleIds: t.role_ids ?? [],
                            hireDate: t.hire_date ?? '',
                            vacationBalanceInitial:
                                t.vacation_balance_initial !== null && t.vacation_balance_initial !== undefined
                                    ? String(t.vacation_balance_initial)
                                    : '',
                            department: t.department ?? '',
                            position: t.position ?? '',
                        };
                    });
                    setSupervisorsByTenant(supervisors);
                    setExtrasByTenant(extras);
                }
            } else {
                toast.error('Usuario no encontrado');
                navigate('/users');
            }
        } catch (error) {
            console.error(error);
            toast.error('Error al cargar usuario');
            navigate('/users');
        } finally {
            setIsLoading(false);
        }
    };

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'El nombre es requerido';
        }

        if (!formData.email.trim()) {
            newErrors.email = 'El email es requerido';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
            newErrors.email = 'Email inválido';
        }

        if (!formData.document_text.trim()) {
            newErrors.document_text = 'El número de documento es requerido';
        } else {
            if (formData.document_type === 'dni') {
                if (formData.document_text.length !== 8) {
                    newErrors.document_text = 'El DNI debe tener 8 dígitos';
                } else if (!/^\d+$/.test(formData.document_text)) {
                    newErrors.document_text = 'El DNI solo debe contener números';
                }
            } else if (formData.document_type === 'ruc') {
                if (formData.document_text.length !== 11) {
                    newErrors.document_text = 'El RUC debe tener 11 dígitos';
                } else if (!/^\d+$/.test(formData.document_text)) {
                    newErrors.document_text = 'El RUC solo debe contener números';
                }
            } else if (formData.document_type === 'ce') {
                if (formData.document_text.length < 9 || formData.document_text.length > 12) {
                    newErrors.document_text = 'El Carné de Extranjería debe tener entre 9 y 12 caracteres';
                }
            }
        }

        if (formData.role !== 'root') {
            if (selectedTenantIds.length === 0) {
                newErrors.tenants = 'Los usuarios no-root deben tener al menos una organización asignada';
            } else {
                const tenantWithoutRoles = selectedTenantIds.find(
                    tenantId => (extrasByTenant[tenantId]?.roleIds ?? []).length === 0
                );
                if (tenantWithoutRoles) {
                    newErrors.tenantRoles = 'Cada empresa asignada debe tener al menos un rol seleccionado';
                }
            }
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleChange = (field: string, value: string | null) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => ({ ...prev, [field]: '' }));
        }
    };

    const handleRoleChange = (role: string) => {
        if (role === 'root') {
            setSelectedTenantIds([]);
            setPrimaryTenantId(null);
            setSelectedTenants([]);
            setSupervisorsByTenant({});
            setExtrasByTenant({});
            if (errors.tenants || errors.tenantRoles) {
                setErrors(prev => ({ ...prev, tenants: '', tenantRoles: '' }));
            }
        }
    };

    const handleSupervisorChange = useCallback((tenantId: string, supervisorId: string | null) => {
        setSupervisorsByTenant(prev => ({ ...prev, [tenantId]: supervisorId }));
    }, []);

    const handleExtraChange = useCallback((tenantId: string, extra: Partial<TenantExtra>) => {
        setExtrasByTenant(prev => ({
            ...prev,
            [tenantId]: { ...(prev[tenantId] ?? EMPTY_TENANT_EXTRA), ...extra },
        }));
        setErrors(prev => (prev.tenantRoles ? { ...prev, tenantRoles: '' } : prev));
    }, []);

    const handleTenantSelectionChange = useCallback((ids: string[]) => {
        setSelectedTenantIds(ids);
        if (errors.tenants) {
            setErrors(prev => ({ ...prev, tenants: '' }));
        }

        setSupervisorsByTenant(prev => {
            const next = { ...prev };
            let changed = false;
            Object.keys(next).forEach(key => {
                if (!ids.includes(key)) {
                    delete next[key];
                    changed = true;
                }
            });
            return changed ? next : prev;
        });

        setExtrasByTenant(prev => {
            const next = { ...prev };
            let changed = false;
            Object.keys(next).forEach(key => {
                if (!ids.includes(key)) {
                    delete next[key];
                    changed = true;
                }
            });
            return changed ? next : prev;
        });
    }, [errors.tenants]);

    const handleTenantsChange = useCallback((tenants: TenantAssociation[]) => {
        setSelectedTenants(tenants);
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            toast.error('Por favor corrige los errores del formulario');
            return;
        }

        setIsSaving(true);
        try {
            const dataToSend: Record<string, unknown> = {
                name: formData.name,
                last_name: formData.last_name,
                email: formData.email,
                document_type: formData.document_type,
                document_text: formData.document_text,
                phone: formData.phone,
                birth_date: formData.birth_date || null,
                status: formData.status,
            };

            if (formData.role === 'root') {
                const rootRoleId = availableRoles.find(r => r.name === 'root')?.id ?? ROOT_ROLE_ID_FALLBACK;
                dataToSend.role_id = rootRoleId;
            } else {
                const tenantsConfig = selectedTenantIds.map(tenantId => {
                    const extra = extrasByTenant[tenantId] ?? EMPTY_TENANT_EXTRA;
                    return {
                        tenant_id: parseInt(tenantId, 10),
                        role_ids: extra.roleIds,
                        hire_date: extra.hireDate || null,
                        vacation_balance_initial:
                            extra.vacationBalanceInitial !== '' ? Number(extra.vacationBalanceInitial) : null,
                        supervisor_id: supervisorsByTenant[tenantId] ? parseInt(supervisorsByTenant[tenantId]!, 10) : null,
                        is_primary: tenantId === primaryTenantId,
                        department: extra.department?.trim() ? extra.department.trim() : null,
                        position: extra.position?.trim() ? extra.position.trim() : null,
                    };
                });

                dataToSend.tenants_config = tenantsConfig;
                dataToSend.tenant_id = parseInt(primaryTenantId || selectedTenantIds[0], 10);
            }

            if (isEditing && id) {
                await userRepository.update(id, dataToSend);
                toast.success('Usuario actualizado exitosamente');
            } else {
                await userRepository.create(dataToSend);
                toast.success('Usuario creado exitosamente');
            }

            navigate('/users');
        } catch (error: unknown) {
            console.error(error);
            const message = error instanceof Error ? error.message : 'Error al guardar usuario';
            toast.error(message);
        } finally {
            setIsSaving(false);
        }
    };

    const canChangeRole = currentUser?.role === 'root';
    const nonRootRoles = availableRoles.filter(r => r.name !== 'root');

    if (isLoading) {
        return (
            <div className="container mx-auto py-6 flex items-center justify-center min-h-[400px]">
                <div className="text-center">
                    <Loader2 className="h-8 w-8 animate-spin mx-auto text-blue-600" />
                    <p className="mt-2 text-gray-500">Cargando usuario...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="container mx-auto py-6 max-w-3xl">
            <div className="mb-6">
                <Button variant="ghost" size="sm" onClick={() => navigate('/users')}>
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Volver a Usuarios
                </Button>
            </div>

            <div className="flex items-center gap-4 mb-8">
                <div className="p-3 bg-blue-100 rounded-lg">
                    {isEditing ? (
                        <UserCircle className="h-8 w-8 text-blue-600" />
                    ) : (
                        <UserPlus className="h-8 w-8 text-blue-600" />
                    )}
                </div>
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
                        {isEditing ? 'Editar Usuario' : 'Nuevo Usuario'}
                    </h1>
                    <p className="text-gray-500 text-sm sm:text-base">
                        {isEditing
                            ? 'Actualiza la información del usuario'
                            : 'Completa los datos para crear un nuevo usuario'}
                    </p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <PersonalInfoCard
                    formData={formData}
                    errors={errors}
                    onChange={handleChange}
                />

                <RoleStatusCard
                    role={formData.role}
                    status={formData.status}
                    canChangeRole={canChangeRole}
                    onChange={handleChange}
                    onRoleChange={handleRoleChange}
                />

                {formData.role !== 'root' && (
                    <TenantAssignmentCard
                        selectedTenantIds={selectedTenantIds}
                        primaryTenantId={primaryTenantId}
                        selectedTenants={selectedTenants}
                        supervisorsByTenant={supervisorsByTenant}
                        availableRoles={nonRootRoles}
                        extrasByTenant={extrasByTenant}
                        excludeUserId={id}
                        error={errors.tenants || errors.tenantRoles}
                        onTenantSelectionChange={handleTenantSelectionChange}
                        onTenantsChange={handleTenantsChange}
                        onPrimaryChange={setPrimaryTenantId}
                        onSupervisorChange={handleSupervisorChange}
                        onExtraChange={handleExtraChange}
                    />
                )}

                <div className="flex justify-end gap-4">
                    <Button type="button" variant="outline" onClick={() => navigate('/users')}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={isSaving}>
                        {isSaving ? (
                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                        ) : (
                            <Save className="h-4 w-4 mr-2" />
                        )}
                        {isEditing ? 'Guardar Cambios' : 'Crear Usuario'}
                    </Button>
                </div>
            </form>
        </div>
    );
}

export default UserFormPage;
