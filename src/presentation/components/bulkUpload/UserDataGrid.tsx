import { useCallback, useState, useRef, useMemo } from 'react';
import type { EditableUser, BulkUploadConfigData } from '@/domain/types/bulkUserUpload.types';
import { Trash2, AlertCircle } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { cn } from '@/presentation/components/ui/utils';

interface UserDataGridProps {
    users: EditableUser[];
    configData: BulkUploadConfigData | null;
    onCellChange: (rowId: string, field: string, value: any) => void;
    onDeleteRow: (id: string) => void;
}

interface EditingCell {
    rowId: string;
    field: string;
}

export function UserDataGrid({ 
    users, 
    configData,
    onCellChange, 
    onDeleteRow,
}: UserDataGridProps) {
    const [editingCell, setEditingCell] = useState<EditingCell | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Mapas para búsqueda rápida
    const orgByRuc = useMemo(() => {
        if (!configData?.organizations) return new Map();
        return new Map(configData.organizations.map(org => [org.ruc, org]));
    }, [configData?.organizations]);

    const handleCellChange = useCallback((rowId: string, field: string, value: any) => {
        onCellChange(rowId, field, value);
    }, [onCellChange]);

    const handleOrgChange = useCallback((userId: string, orgIndex: number, newRuc: string) => {
        const user = users.find(u => u.id === userId);
        if (!user) return;

        const orgs = [...(user.organizaciones || [])];
        while (orgs.length <= orgIndex) {
            orgs.push({ ruc: '', supervisor_email: '' });
        }
        
        // Cambiar RUC y limpiar supervisor (porque cambió la org)
        orgs[orgIndex] = { ruc: newRuc, supervisor_email: '' };
        
        onCellChange(userId, 'organizaciones', orgs);
    }, [users, onCellChange]);

    const handleSupervisorChange = useCallback((userId: string, orgIndex: number, supervisorEmail: string) => {
        const user = users.find(u => u.id === userId);
        if (!user) return;

        const orgs = [...(user.organizaciones || [])];
        while (orgs.length <= orgIndex) {
            orgs.push({ ruc: '', supervisor_email: '' });
        }

        orgs[orgIndex] = { ...orgs[orgIndex], supervisor_email: supervisorEmail };

        onCellChange(userId, 'organizaciones', orgs);
    }, [users, onCellChange]);

    // RP1-C: rol(es)/fecha de ingreso/saldo de vacaciones por organización.
    // Usan spread (no reemplazan el objeto) para no perder tenant_id/ruc/etc.
    const handleOrgRolesChange = useCallback((userId: string, orgIndex: number, roles: string[]) => {
        const user = users.find(u => u.id === userId);
        if (!user) return;

        const orgs = [...(user.organizaciones || [])];
        while (orgs.length <= orgIndex) {
            orgs.push({ ruc: '', supervisor_email: '' });
        }

        orgs[orgIndex] = { ...orgs[orgIndex], roles };

        onCellChange(userId, 'organizaciones', orgs);
    }, [users, onCellChange]);

    const handleOrgFieldChange = useCallback((
        userId: string,
        orgIndex: number,
        field: 'hire_date' | 'vacation_balance_initial',
        value: string
    ) => {
        const user = users.find(u => u.id === userId);
        if (!user) return;

        const orgs = [...(user.organizaciones || [])];
        while (orgs.length <= orgIndex) {
            orgs.push({ ruc: '', supervisor_email: '' });
        }

        orgs[orgIndex] = { ...orgs[orgIndex], [field]: value };

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
                    onChange={(e) => {
                        const inputValue = e.target.value;
                        // Validar número de documento según tipo
                        if (field === 'numero_documento') {
                            const docType = user.tipo_documento;
                            // Para DNI y RUC solo permitir números
                            if (docType === 'dni' || docType === 'ruc') {
                                if (inputValue === '' || /^\d+$/.test(inputValue)) {
                                    handleCellChange(user.id, field as string, inputValue);
                                }
                            } else {
                                // Para CE y pasaporte permitir alfanuméricos
                                handleCellChange(user.id, field as string, inputValue);
                            }
                        } else {
                            handleCellChange(user.id, field as string, inputValue);
                        }
                    }}
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

    // Renderizar select de organización
    const renderOrgSelect = (user: EditableUser, orgIndex: number) => {
        const org = user.organizaciones?.[orgIndex];
        const currentRuc = org?.ruc || '';
        
        return (
            <select
                value={currentRuc}
                onChange={(e) => handleOrgChange(user.id, orgIndex, e.target.value)}
                className={cn(
                    "w-full h-7 px-1 text-xs border rounded bg-white hover:border-blue-400 focus:border-blue-500 outline-none",
                    currentRuc ? "border-gray-200" : "border-orange-300"
                )}
            >
                <option value="">-- Seleccionar --</option>
                {configData?.organizations.map(org => (
                    <option key={org.ruc} value={org.ruc}>
                        {org.name} ({org.ruc})
                    </option>
                ))}
            </select>
        );
    };

    // Renderizar select de supervisor (filtrado por org seleccionada)
    const renderSupervisorSelect = (user: EditableUser, orgIndex: number) => {
        const org = user.organizaciones?.[orgIndex];
        const currentRuc = org?.ruc || '';
        const currentSupervisor = org?.supervisor_email || '';
        
        // Buscar org por RUC para obtener sus supervisores
        // Normalizar RUC (trim y comparar como string)
        const normalizedRuc = String(currentRuc).trim();
        const selectedOrg = configData?.organizations.find(o => String(o.ruc).trim() === normalizedRuc);
        
        // Debug primera fila
        if (user.row_number === 2 && !selectedOrg && currentRuc) {
            console.log('DEBUG SUPERVISOR:', {
                currentRuc,
                normalizedRuc,
                configOrgs: configData?.organizations?.map(o => ({ ruc: o.ruc, id: o.id })),
                selectedOrg,
            });
        }
        
        // supervisors_by_org tiene keys como strings ("1", "2", etc.)
        let supervisors: Array<{ id: number; email: string; full_name: string }> = [];
        if (selectedOrg && configData?.supervisors_by_org) {
            // Usar String() para la key porque viene como "1", "2", etc.
            const orgKey = String(selectedOrg.id);
            const orgSupervisors = configData.supervisors_by_org[orgKey as any];
            if (Array.isArray(orgSupervisors)) {
                supervisors = orgSupervisors;
            } else if (orgSupervisors && typeof orgSupervisors === 'object') {
                supervisors = Object.values(orgSupervisors);
            }
        }
        
        // Verificar si el supervisor actual existe en la lista
        const supervisorExists = supervisors.some(s => s.email === currentSupervisor);
        
        if (!currentRuc) {
            return (
                <div className="flex flex-col">
                    <span className="text-xs text-gray-400 italic">
                        Selecciona org primero
                    </span>
                    {currentSupervisor && (
                        <span className="text-xs text-orange-500" title="Valor del Excel">
                            Excel: {currentSupervisor}
                        </span>
                    )}
                </div>
            );
        }
        
        return (
            <div className="flex flex-col gap-0.5">
                <select
                    value={supervisorExists ? currentSupervisor : ''}
                    onChange={(e) => handleSupervisorChange(user.id, orgIndex, e.target.value)}
                    className={cn(
                        "w-full h-7 px-1 text-xs border rounded bg-white hover:border-blue-400 focus:border-blue-500 outline-none",
                        !supervisorExists && currentSupervisor ? "border-orange-400" : "border-gray-200"
                    )}
                >
                    <option value="">-- Sin supervisor --</option>
                    {supervisors.map(sup => (
                        <option key={sup.id} value={sup.email}>
                            {sup.full_name} ({sup.email})
                        </option>
                    ))}
                </select>
                {!supervisorExists && currentSupervisor && (
                    <span className="text-xs text-orange-500" title="Email no encontrado en supervisores de esta org">
                        ⚠️ {currentSupervisor}
                    </span>
                )}
            </div>
        );
    };

    // RP1-C: rol(es) operativos para esta organización (vacío = usa el rol
    // general de la fila para esa empresa)
    const renderOrgRolesSelect = (user: EditableUser, orgIndex: number) => {
        const org = user.organizaciones?.[orgIndex];
        const currentRoles = org?.roles || [];
        const availableRoles = configData?.available_roles || [];

        return (
            <select
                multiple
                value={currentRoles}
                onChange={(e) => {
                    const selected = Array.from(e.target.selectedOptions).map(o => o.value);
                    handleOrgRolesChange(user.id, orgIndex, selected);
                }}
                className="w-full h-14 px-1 text-xs border rounded bg-white hover:border-blue-400 focus:border-blue-500 outline-none border-gray-200"
                title="Vacío = usa el rol general de la fila para esta empresa"
            >
                {availableRoles.map(r => (
                    <option key={r.id} value={r.name}>{r.display_name}</option>
                ))}
            </select>
        );
    };

    // RP1-C: fecha de ingreso a esta organización
    const renderOrgHireDateInput = (user: EditableUser, orgIndex: number) => {
        const org = user.organizaciones?.[orgIndex];

        return (
            <input
                type="date"
                value={org?.hire_date || ''}
                onChange={(e) => handleOrgFieldChange(user.id, orgIndex, 'hire_date', e.target.value)}
                className="w-full h-7 px-1 text-xs border rounded bg-white hover:border-blue-400 focus:border-blue-500 outline-none border-gray-200"
            />
        );
    };

    // RP1-C: saldo inicial de vacaciones (días) en esta organización
    const renderOrgVacationBalanceInput = (user: EditableUser, orgIndex: number) => {
        const org = user.organizaciones?.[orgIndex];

        return (
            <input
                type="number"
                min="0"
                step="0.5"
                placeholder="Días"
                value={org?.vacation_balance_initial ?? ''}
                onChange={(e) => handleOrgFieldChange(user.id, orgIndex, 'vacation_balance_initial', e.target.value)}
                className="w-full h-7 px-1 text-xs border rounded bg-white hover:border-blue-400 focus:border-blue-500 outline-none border-gray-200"
            />
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
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-44 bg-blue-50">Organización</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-48 bg-blue-50">Supervisor</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-32 bg-blue-50">Roles empresa</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-32 bg-blue-50">Fecha ingreso</th>
                        <th className="px-2 py-2 text-left font-semibold text-gray-700 border-b border-r min-w-28 bg-blue-50">Saldo vacac.</th>
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
                                {/* Selects de organización */}
                                <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                    {renderOrgSelect(user, 0)}
                                </td>
                                <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                    {renderSupervisorSelect(user, 0)}
                                </td>
                                <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                    {renderOrgRolesSelect(user, 0)}
                                </td>
                                <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                    {renderOrgHireDateInput(user, 0)}
                                </td>
                                <td className="px-1 py-1 border-b border-r bg-blue-50/20">
                                    {renderOrgVacationBalanceInput(user, 0)}
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
