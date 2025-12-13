import { User } from '@/core/domain/entities/User';
import { useNavigate } from 'react-router-dom';
import { Badge } from '@/presentation/components/ui/badge';
import { Button } from '@/presentation/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/presentation/components/ui/table';
import { Eye, Pencil, Users, Mail } from 'lucide-react';

interface SubordinatesListProps {
    subordinates: User[];
    isLoading?: boolean;
    onView?: (user: User) => void;
    onEdit?: (user: User) => void;
    showActions?: boolean;
    compact?: boolean;
}

/**
 * SubordinatesList - Displays list of direct reports
 */
export function SubordinatesList({
    subordinates,
    isLoading = false,
    onView,
    onEdit,
    showActions = true,
    compact = false
}: SubordinatesListProps) {
    const navigate = useNavigate();

    const handleView = (user: User) => {
        if (onView) {
            onView(user);
        } else {
            navigate(`/users/${user.id}`);
        }
    };

    const handleEdit = (user: User) => {
        if (onEdit) {
            onEdit(user);
        } else {
            navigate(`/users/${user.id}/edit`);
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
            admin: 'Admin',
            client: 'Usuario',
        };

        return (
            <Badge variant="outline" className={variants[role] || variants.client}>
                {labels[role] || role}
            </Badge>
        );
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center p-8 text-gray-500">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mr-2" />
                Cargando subordinados...
            </div>
        );
    }

    if (subordinates.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center p-8 text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed">
                <Users className="h-10 w-10 text-gray-300 mb-2" />
                <p className="text-sm">No tiene subordinados directos</p>
            </div>
        );
    }

    if (compact) {
        return (
            <div className="space-y-2">
                {subordinates.map((user) => (
                    <div
                        key={user.id}
                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
                        onClick={() => handleView(user)}
                    >
                        <div className="flex items-center gap-3">
                            <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <span className="text-blue-600 text-sm font-medium">
                                    {(user.name || '').charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <p className="font-medium text-sm text-gray-900">
                                    {user.full_name || `${user.name} ${user.last_name || ''}`}
                                </p>
                                <p className="text-xs text-gray-500">{user.email}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {getRoleBadge(user.role)}
                            {getStatusBadge(user.status)}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="rounded-lg border overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow className="bg-gray-50">
                        <TableHead>Nombre</TableHead>
                        <TableHead className="hidden md:table-cell">Email</TableHead>
                        <TableHead>Rol</TableHead>
                        <TableHead>Estado</TableHead>
                        {showActions && (
                            <TableHead className="text-center w-24">Acciones</TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {subordinates.map((user) => (
                        <TableRow key={user.id} className="hover:bg-gray-50">
                            <TableCell>
                                <div className="flex items-center gap-3">
                                    <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <span className="text-blue-600 text-sm font-medium">
                                            {(user.name || '').charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                    <span className="font-medium">
                                        {user.full_name || `${user.name} ${user.last_name || ''}`}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                                <div className="flex items-center gap-1 text-gray-600">
                                    <Mail className="h-3.5 w-3.5" />
                                    {user.email}
                                </div>
                            </TableCell>
                            <TableCell>{getRoleBadge(user.role)}</TableCell>
                            <TableCell>{getStatusBadge(user.status)}</TableCell>
                            {showActions && (
                                <TableCell>
                                    <div className="flex justify-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleView(user)}
                                        >
                                            <Eye className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleEdit(user)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
