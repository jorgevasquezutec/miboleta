import { useCallback, useState, useRef } from 'react';
import type { EditableUser } from '@/domain/types/bulkUserUpload.types';
import { Trash2, AlertCircle } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { cn } from '@/presentation/components/ui/utils';

interface UserDataGridProps {
    users: EditableUser[];
    onCellChange: (rowId: string, field: string, value: any) => void;
    onDeleteRow: (id: string) => void;
}

interface EditingCell {
    rowId: string;
    field: string;
}

export function UserDataGrid({ 
    users, 
    onCellChange, 
    onDeleteRow,
}: UserDataGridProps) {
    const [editingCell, setEditingCell] = useState<EditingCell | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleCellChange = useCallback((rowId: string, field: string, value: any) => {
        onCellChange(rowId, field, value);
    }, [onCellChange]);

    const handleOrgFieldChange = useCallback((userId: string, orgIndex: number, field: 'ruc' | 'supervisor_email', value: string) => {
        const user = users.find(u => u.id === userId);
        if (!user) return;

        // Crear copia de organizaciones
        const orgs = [...(user.organizaciones || [])];
        
        // Asegurar que existe el índice
        while (orgs.length <= orgIndex) {
            orgs.push({ ruc: '', supervisor_email: '' });
        }
        
        // Actualizar el campo
        orgs[orgIndex] = { ...orgs[orgIndex], [field]: value };
        
        // Limpiar orgs vacías al final
        while (orgs.length > 0 && !orgs[orgs.length - 1].ruc && !orgs[orgs.length - 1].supervisor_email) {
            orgs.pop();
        }
        
        onCellChange(userId, 'organizaciones', orgs);
    }, [users, onCellChange]);

    const handleCellClick = useCallback((rowId: string, field: string) => {
        setEditingCell({ rowId, field });
        setTimeout(() => inputRef.current?.focus(), 0);
    }, []);

    const handleCellBlur = useCallback(() => {
        setTimeout(() => setEditingCell(null), 100);
    }, []);

    const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === 'Escape') {
            setEditingCell(null);
        }
    }, []);

    const isEditing = (rowId: string, field: string) => {
        return editingCell?.rowId === rowId && editingCell?.field === field;
    };

    const renderEditableCell = (user: EditableUser, field: keyof EditableUser) => {
        const value = (user[field] as string) || '';
        const error = user._errors[field as string];

        if (isEditing(user.id, field as string)) {
            return (
                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={(e) => handleCellChange(user.id, field as string, e.target.value)}
                    onBlur={handleCellBlur}
                    onKeyDown={handleKeyDown}
                    className="w-full h-full px-2 py-1 text-sm border border-blue-500 rounded outline-none bg-white"
                    autoFocus
                />
            );
        }

        return (
            <div
                onClick={() => handleCellClick(user.id, field as string)}
                className="flex items-center gap-1 cursor-pointer min-h-7"
            >
                <span className={cn('truncate', !value && 'text-gray-400')}>
                    {value || '-'}
                </span>
                {error && <span title={error}><AlertCircle className="h-3.5 w-3.5 text-red-500 shrink-0" /></span>}
            </div>
        );
    };

    const renderSelectCell = (user: EditableUser, field: keyof EditableUser, options: { value: string; label: string }[]) => {
        const value = (user[field] as string) || '';
        const error = user._errors[field as string];

        if (isEditing(user.id, field as string)) {
            return (
                <select
                    value={value}
                    onChange={(e) => {
                        handleCellChange(user.id, field as string, e.target.value);
                        setEditingCell(null);
                    }}
                    onBlur={handleCellBlur}
                    className="w-full h-full px-1 py-1 text-sm border border-blue-500 rounded outline-none bg-white"
                    autoFocus
                >
                    {options.map(opt => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            );
        }

        const label = options.find(o => o.value === value)?.label || value;

        return (
            <div
                onClick={() => handleCellClick(user.id, field as string)}
                className="flex items-center gap-1 cursor-pointer min-h-7"
            >
                <span className="truncate">{label || '-'}</span>
                {error && <AlertCircle className="h-3.5 w-3.5 text-red-500 shrink-0" />}
            </div>
        );
    };

    const renderOrgCell = (user: EditableUser, orgIndex: number, field: 'ruc' | 'supervisor_email') => {
        const org = user.organizaciones?.[orgIndex];
        const value = org?.[field] || '';
        const cellKey = `org${orgIndex}_${field}`;
        const errorKey = `organizaciones.${orgIndex}.${field}`;
        const error = user._errors[errorKey];

        if (isEditing(user.id, cellKey)) {
            return (
                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={(e) => handleOrgFieldChange(user.id, orgIndex, field, e.target.value)}
                    onBlur={handleCellBlur}
                    onKeyDown={handleKeyDown}
                    placeholder={field === 'ruc' ? 'RUC...' : 'Email supervisor...'}
                    className="w-full h-full px-2 py-1 text-sm border border-blue-500 rounded outline-none bg-white"
                    autoFocus
                />
            );
        }

        return (
            <div
                onClick={() => handleCellClick(user.id, cellKey)}
                className="flex items-center gap-1 cursor-pointer min-h-7"
            >
                <span className={cn('truncate text-xs', !value && 'text-gray-400')}>
                    {value || '-'}
                </span>
                {error && <span title={error}><AlertCircle className="h-3 w-3 text-red-500 shrink-0" /></span>}
            </div>
        );
    };

    return (
        <div className="h-full overflow-auto bg-white">
            <table className="w-full border-collapse text-sm">
                <thead className="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r w-8"></th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r w-10">#</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-24">nombre</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-24">apellido</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-40">email</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-20">tipo_doc</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-24">numero_doc</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-16">rol</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-16">estado</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-24">telefono</th>
                        {/* Columna de organización única */}
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-28 bg-blue-50">org1_ruc</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-40 bg-blue-50">org1_supervisor</th>
                        <th className="px-2 py-2 text-center font-semibold text-gray-700 border-b w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    {users.map((user) => {
                        const errorMessages = Object.entries(user._errors).map(([field, msg]) => `${field}: ${msg}`);
                        const hasErrors = errorMessages.length > 0;
                        
                        return (
                        <tr 
                            key={user.id} 
                            className={cn(
                                'hover:bg-blue-50/50',
                                !user._isValid && 'bg-red-50/40'
                            )}
                        >
                            {/* Columna de estado/errores */}
                            <td className="px-1 py-1 border-b border-r text-center">
                                {hasErrors ? (
                                    <div 
                                        className="flex items-center justify-center cursor-help"
                                        title={errorMessages.join('\n')}
                                    >
                                        <AlertCircle className="h-4 w-4 text-red-500" />
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-center">
                                        <div className="h-3 w-3 rounded-full bg-green-500" />
                                    </div>
                                )}
                            </td>
                            <td className="px-2 py-1.5 border-b border-r text-gray-500 font-medium">
                                {user.row_number}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.nombre && "bg-red-50")}>
                                {renderEditableCell(user, 'nombre')}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.apellido && "bg-red-50")}>
                                {renderEditableCell(user, 'apellido')}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.email && "bg-red-50")}>
                                {renderEditableCell(user, 'email')}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.tipo_documento && "bg-red-50")}>
                                {renderSelectCell(user, 'tipo_documento', [
                                    { value: 'dni', label: 'DNI' },
                                    { value: 'ce', label: 'CE' },
                                    { value: 'passport', label: 'Pasaporte' },
                                    { value: 'ruc', label: 'RUC' },
                                ])}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.numero_documento && "bg-red-50")}>
                                {renderEditableCell(user, 'numero_documento')}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.rol && "bg-red-50")}>
                                {renderSelectCell(user, 'rol', [
                                    { value: 'client', label: 'client' },
                                    { value: 'admin', label: 'admin' },
                                    { value: 'root', label: 'root' },
                                ])}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.estado && "bg-red-50")}>
                                {renderSelectCell(user, 'estado', [
                                    { value: 'active', label: 'active' },
                                    { value: 'inactive', label: 'inactive' },
                                ])}
                            </td>
                            <td className={cn("px-2 py-1 border-b border-r", user._errors.telefono && "bg-red-50")}>
                                {renderEditableCell(user, 'telefono')}
                            </td>
                            {/* Celdas de organización 1 */}
                            <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                {renderOrgCell(user, 0, 'ruc')}
                            </td>
                            <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                {renderOrgCell(user, 0, 'supervisor_email')}
                            </td>
                            <td className="px-2 py-1 border-b text-center">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                                    onClick={() => onDeleteRow(user.id)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
