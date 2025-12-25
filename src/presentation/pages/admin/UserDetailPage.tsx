import { useEffect, useState } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate, useParams } from 'react-router-dom';
import { User } from '@/core/domain/entities/User';
import { userRepository } from '@/infrastructure/persistence/repositories';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { useAuthStore } from '@/presentation/stores/authStore';
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
    // SupervisorBadge,
    SubordinatesList,
    UserTenantsManager,
    PasswordResetModal,
} from '@/presentation/components/features/users';
import { ConfirmDialog } from '@/presentation/components/shared/ConfirmDialog';
import {
    ArrowLeft,
    Pencil,
    Mail,
    Phone,
    FileText,
    Calendar,
    UserCircle,
    Loader2,
    Power,
    Shield,
    KeyRound,
    Building2,
} from 'lucide-react';
import { toast } from 'sonner';
import apiClient from '@/infrastructure/http/apiClient';

export function UserDetailPage() {
    useDocumentTitle('Detalle de Usuario');
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();
    const { user: currentUser } = useAuthStore();
    const { tenants, fetchTenants } = useTenantsStore();

    const [user, setUser] = useState<User | null>(null);
    const [subordinates, setSubordinates] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isUpdating, setIsUpdating] = useState(false);
    const [isPasswordResetOpen, setIsPasswordResetOpen] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<(() => void) | null>(null);

    // Load user data
    useEffect(() => {
        if (id) {
            loadUser(id);
            loadSubordinates(id);
            fetchTenants();
        }
    }, [id]);

    const loadUser = async (userId: string) => {
        setIsLoading(true);
        try {
            const userData = await userRepository.findById(userId);
            if (userData) {
                setUser(userData);
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

    const loadSubordinates = async (userId: string) => {
        try {
            const response = await apiClient.get<{ data: User[] }>(`/users/${userId}/subordinates`);
            setSubordinates(response.data.data || []);
        } catch (error) {
            console.log('No subordinates endpoint or error:', error);
            setSubordinates([]);
        }
    };

    const handleToggleStatus = () => {
        if (!user) return;

        const newStatus = user.status === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activar' : 'desactivar';

        setPendingAction(() => async () => {
            setIsUpdating(true);
            try {
                const updatedUser = await userRepository.update(user.id, { status: newStatus });
                setUser(updatedUser);
                toast.success(`Usuario ${action === 'activar' ? 'activado' : 'desactivado'} exitosamente`);
            } catch (error) {
                toast.error(`Error al ${action} usuario`);
            } finally {
                setIsUpdating(false);
            }
        });
        setIsConfirmOpen(true);
    };

    const handleSetPrimaryTenant = async (tenantId: string) => {
        if (!user) return;
        // This would need a backend endpoint to set primary tenant
        console.log('Set primary tenant:', tenantId);
    };

    const handleAddTenant = async (tenantId: string) => {
        if (!user) return;
        try {
            await apiClient.post(`/tenants/${tenantId}/users`, {
                user_id: user.id,
                is_primary: false,
            });
            // Reload user to get updated tenants
            loadUser(user.id);
        } catch (error) {
            throw error;
        }
    };

    const handleRemoveTenant = async (tenantId: string) => {
        if (!user) return;
        try {
            await apiClient.delete(`/tenants/${tenantId}/users/${user.id}`);
            // Reload user to get updated tenants
            loadUser(user.id);
        } catch (error) {
            throw error;
        }
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<string, string> = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            pending: 'bg-yellow-100 text-yellow-800',
        };
        const labels: Record<string, string> = {
            active: 'Activo',
            inactive: 'Inactivo',
            pending: 'Pendiente',
        };
        return (
            <Badge className={variants[status] || variants.inactive}>
                {labels[status] || status}
            </Badge>
        );
    };

    const getRoleBadge = (role: string) => {
        const variants: Record<string, string> = {
            root: 'bg-purple-100 text-purple-800',
            admin: 'bg-blue-100 text-blue-800',
            client: 'bg-gray-100 text-gray-800',
        };
        const labels: Record<string, string> = {
            root: 'Root',
            admin: 'Administrador',
            client: 'Usuario',
        };
        return (
            <Badge className={variants[role] || variants.client}>
                <Shield className="h-3 w-3 mr-1" />
                {labels[role] || role}
            </Badge>
        );
    };

    const canEdit = currentUser?.role === 'root';

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

    if (!user) {
        return null;
    }

    return (
        <div className="container mx-auto py-6 max-w-5xl space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4 mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate('/users')}
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Volver a Usuarios
                </Button>
            </div>

            {/* User Header Card */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                <span className="text-2xl font-bold text-blue-600">
                                    {(user.name || '').charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">
                                    {user.full_name || `${user.name} ${user.last_name || ''}`}
                                </h1>
                                <div className="flex items-center gap-2 mt-1">
                                    {getRoleBadge(user.role)}
                                    {getStatusBadge(user.status)}
                                </div>
                            </div>
                        </div>

                        {canEdit && (
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setIsPasswordResetOpen(true)}
                                    className="text-orange-600 hover:text-orange-700"
                                >
                                    <KeyRound className="h-4 w-4 mr-2" />
                                    Restablecer Contraseña
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleToggleStatus}
                                    disabled={isUpdating}
                                    className={user.status === 'active' ? 'text-red-600' : 'text-green-600'}
                                >
                                    {isUpdating ? (
                                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                    ) : (
                                        <Power className="h-4 w-4 mr-2" />
                                    )}
                                    {user.status === 'active' ? 'Desactivar' : 'Activar'}
                                </Button>
                                <Button onClick={() => navigate(`/users/${user.id}/edit`)}>
                                    <Pencil className="h-4 w-4 mr-2" />
                                    Editar
                                </Button>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Personal Information */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserCircle className="h-5 w-5" />
                            Información Personal
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <Mail className="h-5 w-5 text-gray-400" />
                            <div>
                                <p className="text-sm text-gray-500">Email</p>
                                <p className="font-medium">{user.email}</p>
                            </div>
                        </div>

                        {user.phone && (
                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <Phone className="h-5 w-5 text-gray-400" />
                                <div>
                                    <p className="text-sm text-gray-500">Teléfono</p>
                                    <p className="font-medium">{user.phone}</p>
                                </div>
                            </div>
                        )}

                        {user.document_text && (
                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <FileText className="h-5 w-5 text-gray-400" />
                                <div>
                                    <p className="text-sm text-gray-500">
                                        {user.document_type === 'dni' ? 'DNI' : user.document_type?.toUpperCase() || 'Documento'}
                                    </p>
                                    <p className="font-medium">{user.document_text}</p>
                                </div>
                            </div>
                        )}

                        {user.created_at && (
                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <Calendar className="h-5 w-5 text-gray-400" />
                                <div>
                                    <p className="text-sm text-gray-500">Fecha de registro</p>
                                    <p className="font-medium">
                                        {new Date(user.created_at).toLocaleDateString('es-PE', {
                                            day: '2-digit',
                                            month: 'long',
                                            year: 'numeric'
                                        })}
                                    </p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Supervisores por Tenant */}
                <Card>
                    <CardHeader>
                        <CardTitle>Supervisores Asignados</CardTitle>
                        <CardDescription>
                            Supervisores asignados por empresa
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {user.tenants && user.tenants.length > 0 ? (
                            <div className="space-y-3">
                                {user.tenants.map((tenant) => (
                                    <div key={tenant.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div className="flex items-center gap-2">
                                            <Building2 className="w-4 h-4 text-gray-500" />
                                            <span className="font-medium">{tenant.name}</span>
                                        </div>
                                        {tenant.supervisor ? (
                                            <div className="flex flex-col items-end">
                                                <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200 mb-1">
                                                    Supervisor asignado
                                                </Badge>
                                                <span className="text-sm text-gray-600">{tenant.supervisor.full_name || tenant.supervisor.name}</span>
                                            </div>
                                        ) : (
                                            <Badge variant="outline" className="bg-gray-100 text-gray-600">
                                                Sin supervisor
                                            </Badge>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No hay empresas asignadas
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Subordinates */}
            <Card>
                <CardHeader>
                    <CardTitle>Subordinados Directos</CardTitle>
                    <CardDescription>
                        Usuarios que reportan directamente a {user.name}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <SubordinatesList
                        subordinates={subordinates}
                        compact={subordinates.length > 5}
                        showActions={currentUser?.role === 'root'}
                    />
                </CardContent>
            </Card>

            {/* Tenants */}
            <UserTenantsManager
                userTenants={user.tenants || []}
                availableTenants={tenants}
                onSetPrimary={handleSetPrimaryTenant}
                onAddTenant={handleAddTenant}
                onRemoveTenant={handleRemoveTenant}
                canEdit={false}
            />

            {/* Password Reset Modal */}
            <PasswordResetModal
                open={isPasswordResetOpen}
                onOpenChange={setIsPasswordResetOpen}
                userId={user.id}
                userName={user.full_name || user.name}
                onSuccess={() => loadUser(user.id)}
            />

            {/* Confirm Dialog */}
            <ConfirmDialog
                open={isConfirmOpen}
                onOpenChange={setIsConfirmOpen}
                title={user.status === 'active' ? 'Desactivar Usuario' : 'Activar Usuario'}
                description={`¿Estás seguro de ${user.status === 'active' ? 'desactivar' : 'activar'} a ${user.full_name || user.name}?`}
                onConfirm={() => pendingAction?.()}
                confirmText={user.status === 'active' ? 'Desactivar' : 'Activar'}
                variant={user.status === 'active' ? 'destructive' : 'default'}
            />
        </div>
    );
}

export default UserDetailPage;
