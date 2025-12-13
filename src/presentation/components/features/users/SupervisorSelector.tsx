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
    placeholder?: string;
    disabled?: boolean;
}

/**
 * SupervisorSelector - Combobox to search and select a supervisor
 */
export function SupervisorSelector({
    value,
    onChange,
    excludeUserId,
    placeholder = 'Seleccionar supervisor...',
    disabled = false
}: SupervisorSelectorProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Load users with search
    const loadUsers = useCallback(async (searchTerm: string) => {
        setIsLoading(true);
        try {
            const response = await userRepository.getUsers({
                search: searchTerm,
                per_page: 20,
            });

            // Filter to only show valid supervisors (admin, root)
            // and exclude the current user
            const validSupervisors = response.data.filter(user =>
                (user.role === 'admin' || user.role === 'root') &&
                user.status === 'active' &&
                user.id !== excludeUserId
            );

            setUsers(validSupervisors);
        } catch (error) {
            console.error('Error loading users:', error);
            setUsers([]);
        } finally {
            setIsLoading(false);
        }
    }, [excludeUserId]);

    // Load initial users when popover opens
    useEffect(() => {
        if (open) {
            loadUsers(search);
        }
    }, [open, loadUsers]);

    // Debounced search
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
            root: 'bg-purple-100 text-purple-800',
            admin: 'bg-blue-100 text-blue-800',
        };
        const labels: Record<string, string> = {
            root: 'Root',
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
                        disabled={disabled}
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
                            <span className="text-gray-500">{placeholder}</span>
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
                                {users.map((user) => (
                                    <div
                                        key={user.id}
                                        className={`
                                            flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer
                                            hover:bg-gray-100 transition-colors
                                            ${selectedUser?.id === user.id ? 'bg-blue-50' : ''}
                                        `}
                                        onClick={() => handleSelect(user)}
                                    >
                                        <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <span className="text-blue-600 text-sm font-medium">
                                                {(user.name || '').charAt(0).toUpperCase()}
                                            </span>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-sm truncate">
                                                {user.full_name || `${user.name} ${user.last_name || ''}`}
                                            </p>
                                            <p className="text-xs text-gray-500 truncate">{user.email}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {getRoleBadge(user.role)}
                                            {selectedUser?.id === user.id && (
                                                <Check className="h-4 w-4 text-blue-600" />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Footer hint */}
                    <div className="border-t px-3 py-2 text-xs text-gray-500">
                        Solo usuarios Admin o Root pueden ser supervisores
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
