import { useState, useEffect, useCallback } from 'react';
import { User } from '@/core/domain/entities/User';
import { userRepository } from '@/infrastructure/persistence/repositories';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/presentation/components/ui/popover';
import { Badge } from '@/presentation/components/ui/badge';
import { Search, UserCircle, Check, X, Loader2, ChevronDown } from 'lucide-react';

interface SupervisorSelectorProps {
    value: string | null;
    onChange: (supervisorId: string | null, supervisor: User | null) => void;
    excludeUserId?: string; // User cannot be their own supervisor
    tenantIds?: string[]; // Filter supervisors by these tenant IDs
    placeholder?: string;
    disabled?: boolean;
}

/**
 * SupervisorSelector - Combobox to search and select a supervisor
 * Filters by role=admin and optionally by organization (tenantIds)
 */
export function SupervisorSelector({
    value,
    onChange,
    excludeUserId,
    tenantIds = [],
    placeholder = 'Seleccionar supervisor...',
    disabled = false
}: SupervisorSelectorProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Check if selector should be disabled (no tenants selected)
    const isDisabled = disabled || tenantIds.length === 0;

    // Load users with search
    const loadUsers = useCallback(async (searchTerm: string) => {
        if (tenantIds.length === 0) {
            setUsers([]);
            return;
        }

        setIsLoading(true);
        try {
            // Filter out current user and merge results
            const allUsers: User[] = [];
            const seenIds = new Set<string>();

            for (const tenantId of tenantIds) {
                const response = await userRepository.getUsers({
                    search: searchTerm,
                    per_page: 50,
                    tenant_id: tenantId,
                });

                for (const user of response.data) {
                    if (!seenIds.has(user.id) && user.id !== excludeUserId && user.status === 'active') {
                        seenIds.add(user.id);
                        allUsers.push(user);
                    }
                }
            }

            setUsers(allUsers);
        } catch (error) {
            console.error('Error loading users:', error);
            setUsers([]);
        } finally {
            setIsLoading(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [excludeUserId, JSON.stringify(tenantIds)]);

    // Debounced search and initial load
    useEffect(() => {
        if (!open) return;

        const timer = setTimeout(() => {
            loadUsers(search);
        }, 300);

        return () => clearTimeout(timer);
    }, [search, open, loadUsers]);

    // Load selected user if value is provided
    useEffect(() => {
        const loadSelectedUser = async () => {
            if (value && !selectedUser) {
                try {
                    const user = await userRepository.findById(value);
                    if (user) {
                        setSelectedUser(user);
                    }
                } catch (error) {
                    console.error('Error loading selected user:', error);
                }
            }
        };
        loadSelectedUser();
    }, [value, selectedUser]);

    const handleSelect = (user: User) => {
        setSelectedUser(user);
        onChange(user.id, user);
        setOpen(false);
        setSearch('');
    };

    const handleClear = () => {
        setSelectedUser(null);
        onChange(null, null);
        setSearch('');
    };

    const getRoleBadge = (role: string) => {
        const variants: Record<string, string> = {
            admin: 'bg-blue-100 text-blue-800',
        };
        const labels: Record<string, string> = {
            admin: 'Admin',
        };
        return (
            <Badge variant="outline" className={variants[role] || ''}>
                {labels[role] || role}
            </Badge>
        );
    };

    return (
        <div className="relative">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between"
                        disabled={isDisabled}
                    >
                        {selectedUser ? (
                            <div className="flex items-center gap-2 truncate">
                                <UserCircle className="h-4 w-4 text-blue-600" />
                                <span className="truncate">
                                    {selectedUser.full_name || `${selectedUser.name} ${selectedUser.last_name || ''}`}
                                </span>
                                {getRoleBadge(selectedUser.role)}
                            </div>
                        ) : (
                            <span className="text-gray-500">
                                {tenantIds.length === 0
                                    ? 'Primero selecciona las organizaciones'
                                    : placeholder}
                            </span>
                        )}
                        <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[350px] p-0" align="start">
                    {/* Search Input */}
                    <div className="flex items-center border-b px-3 py-2">
                        <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                        <Input
                            placeholder="Buscar por nombre o email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="border-0 focus-visible:ring-0 px-0"
                        />
                        {selectedUser && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleClear}
                                className="h-6 w-6 p-0"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>

                    {/* User List */}
                    <div className="max-h-[250px] overflow-y-auto">
                        {isLoading ? (
                            <div className="flex items-center justify-center py-6">
                                <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
                            </div>
                        ) : users.length === 0 ? (
                            <div className="py-6 text-center text-sm text-gray-500">
                                {search ? 'No se encontraron usuarios' : 'No hay supervisores disponibles'}
                            </div>
                        ) : (
                            <div className="p-1">
                                {users.map((user) => {
                                    const isSelectable = user.role === 'admin';
                                    return (
                                        <div
                                            key={user.id}
                                            className={`
                                            flex items-center gap-3 px-3 py-2 rounded-md transition-colors
                                            ${isSelectable ? 'cursor-pointer hover:bg-gray-100' : 'opacity-50 cursor-not-allowed'}
                                            ${selectedUser?.id === user.id ? 'bg-blue-50' : ''}
                                        `}
                                            onClick={() => isSelectable && handleSelect(user)}
                                        >
                                            <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                <span className="text-blue-600 text-sm font-medium">
                                                    {(user.name || '').charAt(0).toUpperCase()}
                                                </span>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-sm truncate">
                                                        {user.full_name || `${user.name} ${user.last_name || ''}`}
                                                    </p>
                                                    {!isSelectable && (
                                                        <span className="text-[10px] bg-gray-100 text-gray-500 px-1 rounded">No Admin</span>
                                                    )}
                                                </div>
                                                <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {getRoleBadge(user.role)}
                                                {selectedUser?.id === user.id && (
                                                    <Check className="h-4 w-4 text-blue-600" />
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Footer hint */}
                    <div className="border-t px-3 py-2 text-xs text-gray-500">
                        {tenantIds.length === 0
                            ? 'Selecciona al menos una organización para ver los supervisores disponibles'
                            : 'Solo usuarios Administradores de las organizaciones seleccionadas'}
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
