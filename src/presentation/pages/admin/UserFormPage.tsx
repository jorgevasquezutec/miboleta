import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { userRepository } from '@/infrastructure/persistence/repositories';
import { useAuthStore } from '@/presentation/stores/authStore';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Checkbox } from '@/presentation/components/ui/checkbox';
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
import { SupervisorSelector } from '@/presentation/components/users';
import { ArrowLeft, Save, Loader2, UserPlus, UserCircle, Building2, Star, Mail } from 'lucide-react';
import { toast } from 'sonner';

export function UserFormPage() {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const isEditing = Boolean(id);
    const { user: currentUser } = useAuthStore();
    const { tenants, fetchTenants } = useTenantsStore();

    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Tenant selection state
    const [selectedTenantIds, setSelectedTenantIds] = useState<string[]>([]);
    const [primaryTenantId, setPrimaryTenantId] = useState<string | null>(null);

    const [formData, setFormData] = useState({
        name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        document_type: 'dni',
        document_text: '',
        phone: '',
        role: 'client' as 'root' | 'admin' | 'client',
        status: 'active' as 'active' | 'inactive',
        immediate_supervisor_id: null as string | null,
    });

    // Load user data if editing
    useEffect(() => {
        fetchTenants(); // Load available tenants
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
                    password: '',
                    password_confirmation: '',
                    document_type: user.document_type || 'dni',
                    document_text: user.document_text || '',
                    phone: user.phone || '',
                    role: user.role || 'client',
                    status: user.status === 'active' ? 'active' : 'inactive',
                    immediate_supervisor_id: user.immediate_supervisor_id || null,
                });
                // Load user's tenants
                if (user.tenants && user.tenants.length > 0) {
                    setSelectedTenantIds(user.tenants.map(t => String(t.id)));
                    const primary = user.tenants.find(t => t.is_primary);
                    setPrimaryTenantId(primary ? String(primary.id) : String(user.tenants[0].id));
                }
            } else {
                toast.error('Usuario no encontrado');
                navigate('/users');
            }
        } catch (error) {
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

        // Password validation only when editing and password is provided
        if (isEditing && formData.password) {
            if (formData.password.length < 8) {
                newErrors.password = 'La contraseña debe tener al menos 8 caracteres';
            }
            if (formData.password !== formData.password_confirmation) {
                newErrors.password_confirmation = 'Las contraseñas no coinciden';
            }
        }

        if (formData.document_text && formData.document_type === 'dni') {
            if (formData.document_text.length !== 8) {
                newErrors.document_text = 'El DNI debe tener 8 dígitos';
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

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            toast.error('Por favor corrige los errores del formulario');
            return;
        }

        setIsSaving(true);
        try {
            const dataToSend: any = {
                name: formData.name,
                last_name: formData.last_name,
                email: formData.email,
                document_type: formData.document_type,
                document_text: formData.document_text,
                phone: formData.phone,
                role: formData.role,
                status: formData.status,
                immediate_supervisor_id: formData.immediate_supervisor_id,
            };

            // Include tenant IDs for non-root users
            if (formData.role !== 'root') {
                dataToSend.tenant_ids = selectedTenantIds;
                dataToSend.primary_tenant_id = primaryTenantId || selectedTenantIds[0];
            }

            if (!isEditing || formData.password) {
                dataToSend.password = formData.password;
                dataToSend.password_confirmation = formData.password_confirmation;
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
                                    onValueChange={(value: string) => handleChange('document_type', value)}
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
                                <Label htmlFor="document_text">Número de Documento</Label>
                                <Input
                                    id="document_text"
                                    value={formData.document_text}
                                    onChange={(e) => handleChange('document_text', e.target.value)}
                                    placeholder="12345678"
                                    className={errors.document_text ? 'border-red-500' : ''}
                                />
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

                {/* Password - Only shown when editing */}
                {isEditing ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Contraseña</CardTitle>
                            <CardDescription>
                                Deja en blanco para mantener la contraseña actual
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="password">Nueva Contraseña</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={formData.password}
                                        onChange={(e) => handleChange('password', e.target.value)}
                                        placeholder="••••••••"
                                        className={errors.password ? 'border-red-500' : ''}
                                    />
                                    {errors.password && (
                                        <p className="text-sm text-red-500">{errors.password}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirmar Contraseña
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={formData.password_confirmation}
                                        onChange={(e) => handleChange('password_confirmation', e.target.value)}
                                        placeholder="••••••••"
                                        className={errors.password_confirmation ? 'border-red-500' : ''}
                                    />
                                    {errors.password_confirmation && (
                                        <p className="text-sm text-red-500">{errors.password_confirmation}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    /* Info alert for new users about email credentials */
                    <Card className="border-blue-200 bg-blue-50">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="p-3 bg-blue-100 rounded-full">
                                    <Mail className="h-6 w-6 text-blue-600" />
                                </div>
                                <div>
                                    <h4 className="font-semibold text-blue-900">Credenciales por Email</h4>
                                    <p className="text-sm text-blue-700 mt-1">
                                        El usuario recibirá un correo electrónico con una contraseña temporal.
                                        Deberá cambiarla en su primer inicio de sesión.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

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
                                    onValueChange={(value: string) => handleChange('role', value)}
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

                {/* Supervisor */}
                <Card>
                    <CardHeader>
                        <CardTitle>Jefe Inmediato</CardTitle>
                        <CardDescription>Asigna un supervisor para este usuario</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SupervisorSelector
                            value={formData.immediate_supervisor_id}
                            onChange={(supervisorId) => handleChange('immediate_supervisor_id', supervisorId)}
                            excludeUserId={id}
                        />
                    </CardContent>
                </Card>

                {/* Tenant Assignment - Only for non-root users */}
                {formData.role !== 'root' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Organizaciones *
                            </CardTitle>
                            <CardDescription>
                                Selecciona las organizaciones a las que pertenecerá el usuario (mínimo una)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {tenants.length === 0 ? (
                                <p className="text-sm text-gray-500">No hay organizaciones disponibles</p>
                            ) : (
                                <div className="space-y-3">
                                    {tenants.map((tenant) => {
                                        const isSelected = selectedTenantIds.includes(String(tenant.id));
                                        const isPrimary = primaryTenantId === String(tenant.id);

                                        return (
                                            <div
                                                key={tenant.id}
                                                className={`
                                                    flex items-center justify-between p-3 rounded-lg border
                                                    ${isSelected ? 'bg-blue-50 border-blue-200' : 'bg-gray-50'}
                                                `}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        checked={isSelected}
                                                        onCheckedChange={(checked: boolean) => {
                                                            if (checked) {
                                                                setSelectedTenantIds(prev => [...prev, String(tenant.id)]);
                                                                if (selectedTenantIds.length === 0) {
                                                                    setPrimaryTenantId(String(tenant.id));
                                                                }
                                                            } else {
                                                                setSelectedTenantIds(prev => prev.filter(id => id !== String(tenant.id)));
                                                                if (primaryTenantId === String(tenant.id)) {
                                                                    const remaining = selectedTenantIds.filter(id => id !== String(tenant.id));
                                                                    setPrimaryTenantId(remaining[0] || null);
                                                                }
                                                            }
                                                            if (errors.tenants) {
                                                                setErrors(prev => ({ ...prev, tenants: '' }));
                                                            }
                                                        }}
                                                    />
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{tenant.name}</span>
                                                            {isPrimary && isSelected && (
                                                                <span className="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full flex items-center gap-1">
                                                                    <Star className="h-3 w-3" />
                                                                    Primaria
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className="text-sm text-gray-500">RUC: {tenant.ruc}</p>
                                                    </div>
                                                </div>

                                                {isSelected && selectedTenantIds.length > 1 && !isPrimary && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => setPrimaryTenantId(String(tenant.id))}
                                                    >
                                                        <Star className="h-3 w-3 mr-1" />
                                                        Primaria
                                                    </Button>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            {errors.tenants && (
                                <p className="text-sm text-red-500">{errors.tenants}</p>
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
