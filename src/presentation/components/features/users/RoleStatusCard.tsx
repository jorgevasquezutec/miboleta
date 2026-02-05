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
    role: 'root' | 'admin' | 'client';
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
                <CardTitle>Rol y Estado</CardTitle>
                <CardDescription>Permisos y estado del usuario</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="role">Rol</Label>
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
