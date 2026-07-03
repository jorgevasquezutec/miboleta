import { Label } from '@/presentation/components/ui/label';
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

interface RoleStatusCardProps {
    /**
     * Toggle de alto nivel: 'root' (usuario global de plataforma, sin
     * empresas) o 'client' (usuario de empresa). Los roles operativos reales
     * (admin, client, aprobador, administrador_clientes) se asignan por
     * empresa en TenantAssignmentCard, no aquí.
     */
    role: 'root' | 'client';
    status: 'active' | 'inactive';
    canChangeRole: boolean;
    onChange: (field: string, value: string | null) => void;
    onRoleChange?: (role: string) => void;
}

export function RoleStatusCard({
    role,
    status,
    canChangeRole,
    onChange,
    onRoleChange,
}: RoleStatusCardProps) {
    const handleRoleChange = (value: string) => {
        onChange('role', value);
        onRoleChange?.(value);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tipo de Cuenta y Estado</CardTitle>
                <CardDescription>Alcance de la cuenta y estado del usuario</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="role">Tipo de Cuenta</Label>
                        <Select
                            value={role}
                            onValueChange={handleRoleChange}
                            disabled={!canChangeRole}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {canChangeRole && (
                                    <SelectItem value="root">Root (Plataforma)</SelectItem>
                                )}
                                <SelectItem value="client">Usuario de Empresa</SelectItem>
                            </SelectContent>
                        </Select>
                        {!canChangeRole && (
                            <p className="text-xs text-gray-500">
                                Solo Root puede crear otros usuarios Root
                            </p>
                        )}
                        {role !== 'root' && (
                            <p className="text-xs text-gray-500">
                                Los roles concretos (Administrador, Cliente, Aprobador, etc.) se
                                asignan por cada empresa más abajo.
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="status">Estado</Label>
                        <Select
                            value={status}
                            onValueChange={(value: string) => onChange('status', value)}
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
    );
}
