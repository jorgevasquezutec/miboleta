import { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { userRepository } from '@/infrastructure/persistence/repositories';
import { useAuthStore } from '@/presentation/stores/authStore';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
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
import { SupervisorSelector } from '@/presentation/components/features/users';
import { TenantMultiSelector } from '@/presentation/components/shared/TenantMultiSelector';
import { ArrowLeft, Save, Loader2, UserPlus, UserCircle, Building2 } from 'lucide-react';
import { toast } from 'sonner';
import { TenantAssociation } from '@/core/domain/entities/User';

export function UserFormPage() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const isEditing = Boolean(id);
    const { user: currentUser } = useAuthStore();

    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Tenant selection state
    const [selectedTenantIds, setSelectedTenantIds] = useState<string[]>([]);
    const [primaryTenantId, setPrimaryTenantId] = useState<string | null>(null);
    const [selectedTenants, setSelectedTenants] = useState<TenantAssociation[]>([]); // Para pasar al selector
    const [supervisorsByTenant, setSupervisorsByTenant] = useState<Record<string, string | null>>({});

    const [formData, setFormData] = useState({
        name: '',
        last_name: '',
        email: '',
        document_type: 'dni',
        document_text: '',
        phone: '',
        role: 'client' as 'root' | 'admin' | 'client',
        status: 'active' as 'active' | 'inactive',
    });

    // Load user data if editing
    useEffect(() => {
        if (isEditing && id) {
            loadUser(id);
        }
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
                    role: user.role || 'client',
                    status: user.status === 'active' ? 'active' : 'inactive',
                });

                // Load user's tenants and supervisors
                if (user.tenants && user.tenants.length > 0) {
                    setSelectedTenantIds(user.tenants.map(t => String(t.id)));
                    setSelectedTenants(user.tenants);

                    const primary = user.tenants.find(t => t.is_primary);
                    setPrimaryTenantId(primary ? String(primary.id) : String(user.tenants[0].id));

                    // Load supervisors per tenant
                    const supervisors: Record<string, string | null> = {};
                    user.tenants.forEach(t => {
                        if (t.supervisor_id) {
                            supervisors[String(t.id)] = String(t.supervisor_id);
                        }
                    });
                    setSupervisorsByTenant(supervisors);
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
            // Validaciones por tipo de documento
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
                if (formData.document_text.length !== 12) {
                    newErrors.document_text = 'El Carné de Extranjería debe tener 12 dígitos';
                } else if (!/^\d+$/.test(formData.document_text)) {
                    newErrors.document_text = 'El Carné de Extranjería solo debe contener números';
                }
            }
        }

        // Non-root users must have at least one tenant
        if (formData.role !== 'root' && selectedTenantIds.length === 0) {
            newErrors.tenants = 'Los usuarios no-root deben tener al menos una organización asignada';
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

    const handleSupervisorChange = useCallback((tenantId: string, supervisorId: string | null) => {
        setSupervisorsByTenant(prev => ({ ...prev, [tenantId]: supervisorId }));
    }, []);

    const handleTenantSelectionChange = useCallback((ids: string[]) => {
        setSelectedTenantIds(ids);
        if (errors.tenants) {
            setErrors(prev => ({ ...prev, tenants: '' }));
        }

        // Limpiar supervisores de tenants removidos
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
    }, [errors.tenants]);

    const handleTenantsChange = useCallback((tenants: any) => {
        // Actualizamos la lista completa de tenants para tener nombres
        setSelectedTenants(tenants as TenantAssociation[]);
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            toast.error('Por favor corrige los errores del formulario');
            return;
        }

        setIsSaving(true);
        try {
            // Mapeo de roles a role_id
            const roleMap: Record<string, number> = {
                'root': 1,
                'admin': 2,
                'client': 3,
            };

            const dataToSend: any = {
                name: formData.name,
                last_name: formData.last_name,
                email: formData.email,
                document_type: formData.document_type,
                document_text: formData.document_text,
                phone: formData.phone,
                role_id: roleMap[formData.role], // Convertir role a role_id
                status: formData.status,
            };

            // Include tenant config for non-root users
            if (formData.role !== 'root') {
                // Configuración detallada por tenant
                const tenantsConfig = selectedTenantIds.map(tenantId => ({
                    tenant_id: parseInt(tenantId),
                    supervisor_id: supervisorsByTenant[tenantId] ? parseInt(supervisorsByTenant[tenantId]!) : null,
                    is_primary: tenantId === primaryTenantId
                }));

                dataToSend.tenants_config = tenantsConfig;

                // Backend espera tenant_id (singular) para compatibilidad básica
                dataToSend.tenant_id = parseInt(primaryTenantId || selectedTenantIds[0]);
            }

            if (isEditing && id) {
                await userRepository.update(id, dataToSend);
                toast.success('Usuario actualizado exitosamente');
            } else {
                await userRepository.create(dataToSend);
                toast.success('Usuario creado exitosamente');
            }

            navigate('/users');
        } catch (error: any) {
            console.error(error);
            toast.error(error.message || 'Error al guardar usuario');
        } finally {
            setIsSaving(false);
        }
    };

    const canChangeRole = currentUser?.role === 'root';

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
            {/* Header */}
            <div className="mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate('/users')}
                >
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
                    <h1 className="text-2xl font-bold text-gray-900">
                        {isEditing ? 'Editar Usuario' : 'Nuevo Usuario'}
                    </h1>
                    <p className="text-gray-500">
                        {isEditing
                            ? 'Actualiza la información del usuario'
                            : 'Completa los datos para crear un nuevo usuario'}
                    </p>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Personal Information */}
                <Card>
                    <CardHeader>
                        <CardTitle>Información Personal</CardTitle>
                        <CardDescription>Datos básicos del usuario</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => handleChange('name', e.target.value)}
                                    placeholder="Juan"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="last_name">Apellido</Label>
                                <Input
                                    id="last_name"
                                    value={formData.last_name}
                                    onChange={(e) => handleChange('last_name', e.target.value)}
                                    placeholder="Pérez"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">Email *</Label>
                            <Input
                                id="email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => handleChange('email', e.target.value)}
                                placeholder="juan@ejemplo.com"
                                className={errors.email ? 'border-red-500' : ''}
                            />
                            {errors.email && (
                                <p className="text-sm text-red-500">{errors.email}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="document_type">Tipo de Documento</Label>
                                <Select
                                    value={formData.document_type}
                                    onValueChange={(value: string) => {
                                        handleChange('document_type', value);
                                        // Limpiar el campo document_text al cambiar el tipo
                                        handleChange('document_text', '');
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="dni">DNI</SelectItem>
                                        <SelectItem value="ruc">RUC</SelectItem>
                                        <SelectItem value="ce">Carné de Extranjería</SelectItem>
                                        <SelectItem value="passport">Pasaporte</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="document_text">Número de Documento *</Label>
                                <Input
                                    id="document_text"
                                    value={formData.document_text}
                                    onChange={(e) => handleChange('document_text', e.target.value)}
                                    placeholder={
                                        formData.document_type === 'dni' ? '12345678' :
                                            formData.document_type === 'ruc' ? '20123456789' :
                                                formData.document_type === 'ce' ? '001234567890' :
                                                    'A1234567'
                                    }
                                    maxLength={
                                        formData.document_type === 'dni' ? 8 :
                                            formData.document_type === 'ruc' ? 11 :
                                                formData.document_type === 'ce' ? 12 :
                                                    20
                                    }
                                    className={errors.document_text ? 'border-red-500' : ''}
                                />
                                <p className="text-xs text-gray-500">
                                    {formData.document_type === 'dni' && 'DNI: 8 dígitos'}
                                    {formData.document_type === 'ruc' && 'RUC: 11 dígitos'}
                                    {formData.document_type === 'ce' && 'CE: 12 dígitos'}
                                    {formData.document_type === 'passport' && 'Pasaporte: hasta 20 caracteres'}
                                </p>
                                {errors.document_text && (
                                    <p className="text-sm text-red-500">{errors.document_text}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                value={formData.phone}
                                onChange={(e) => handleChange('phone', e.target.value)}
                                placeholder="+51 999 999 999"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Role and Status */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rol y Estado</CardTitle>
                        <CardDescription>Permisos y estado del usuario</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="role">Rol</Label>
                                <Select
                                    value={formData.role}
                                    onValueChange={(value: string) => {
                                        handleChange('role', value);
                                        // Si cambia a root, limpiar tenants
                                        if (value === 'root') {
                                            setSelectedTenantIds([]);
                                            setPrimaryTenantId(null);
                                            setSelectedTenants([]);
                                            if (errors.tenants) {
                                                setErrors(prev => ({ ...prev, tenants: '' }));
                                            }
                                        }
                                    }}
                                    disabled={!canChangeRole}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {canChangeRole && (
                                            <SelectItem value="root">Root</SelectItem>
                                        )}
                                        <SelectItem value="admin">Administrador</SelectItem>
                                        <SelectItem value="client">Usuario</SelectItem>
                                    </SelectContent>
                                </Select>
                                {!canChangeRole && (
                                    <p className="text-xs text-gray-500">
                                        Solo Root puede cambiar roles
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">Estado</Label>
                                <Select
                                    value={formData.status}
                                    onValueChange={(value: string) => handleChange('status', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Activo</SelectItem>
                                        <SelectItem value="inactive">Inactivo</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tenant Assignment with Supervisors - Only for non-root users */}
                {formData.role !== 'root' && (
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
                            {/* Buscador/Selector - Solo muestra el popover de búsqueda */}
                            <div>
                                <Label className="text-sm font-medium mb-2 block">Buscar organizaciones</Label>
                                <TenantMultiSelector
                                    selectedTenantIds={selectedTenantIds}
                                    onSelectionChange={handleTenantSelectionChange}
                                    onTenantsChange={handleTenantsChange}
                                    primaryTenantId={primaryTenantId}
                                    onPrimaryChange={setPrimaryTenantId}
                                    selectedTenants={selectedTenants}
                                    minSelections={1}
                                    error={errors.tenants}
                                    hideSelectedTags={true}
                                />
                            </div>

                            {/* Lista de organizaciones seleccionadas CON supervisor integrado */}
                            {selectedTenantIds.length > 0 && (
                                <div className="space-y-3">
                                    <Label className="text-sm font-medium text-gray-700">
                                        Organizaciones seleccionadas ({selectedTenantIds.length})
                                    </Label>
                                    {selectedTenants.filter(t => selectedTenantIds.includes(String(t.id))).map(tenant => {
                                        const isPrimary = String(tenant.id) === primaryTenantId;
                                        return (
                                            <div
                                                key={tenant.id}
                                                className={`p-4 rounded-lg border ${
                                                    isPrimary ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'
                                                }`}
                                            >
                                                {/* Header: Nombre de organización con acciones */}
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
                                                        {!isPrimary && selectedTenantIds.length >= 1 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => setPrimaryTenantId(String(tenant.id))}
                                                                className="h-8 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                            >
                                                                Marcar primaria
                                                            </Button>
                                                        )}
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => {
                                                                const newIds = selectedTenantIds.filter(id => id !== String(tenant.id));
                                                                handleTenantSelectionChange(newIds);
                                                                if (isPrimary && newIds.length > 0) {
                                                                    setPrimaryTenantId(newIds[0]);
                                                                }
                                                            }}
                                                            className="h-8 w-8 text-gray-400 hover:text-red-600 hover:bg-red-50"
                                                        >
                                                            ×
                                                        </Button>
                                                    </div>
                                                </div>

                                                {/* Selector de Supervisor integrado */}
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium text-gray-600">
                                                        Supervisor / Jefe inmediato
                                                    </Label>
                                                    <SupervisorSelector
                                                        value={supervisorsByTenant[String(tenant.id)]}
                                                        onChange={(supervisorId) => handleSupervisorChange(String(tenant.id), supervisorId)}
                                                        excludeUserId={id}
                                                        tenantIds={[String(tenant.id)]}
                                                        placeholder="Seleccionar supervisor..."
                                                    />
                                                </div>
                                            </div>
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
                )}

                {/* Actions */}
                <div className="flex justify-end gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => navigate('/users')}
                    >
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