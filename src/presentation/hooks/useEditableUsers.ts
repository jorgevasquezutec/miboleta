import { useState, useCallback, useMemo } from 'react';
import { v4 as uuidv4 } from 'uuid';
import type { EditableUser, ValidationError, ValidationWarning } from '@/domain/types/bulkUserUpload.types';

interface UseEditableUsersReturn {
    users: EditableUser[];
    setUsers: React.Dispatch<React.SetStateAction<EditableUser[]>>;
    loadUsers: (data: any[], errors: ValidationError[], warnings?: ValidationWarning[]) => void;
    updateUser: (id: string, field: keyof EditableUser, value: any) => void;
    updateOrganization: (userId: string, orgIndex: number, field: string, value: string) => void;
    addNewRow: () => void;
    deleteRow: (id: string) => void;
    validateUser: (user: EditableUser) => { errors: Record<string, string>; warnings: Record<string, string> };
    isValid: boolean;
    errorCount: number;
    warningCount: number;
    validCount: number;
}

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Función helper para convertir data a EditableUser
function convertToEditableUsers(
    data: any[],
    errors: ValidationError[],
    warnings: ValidationWarning[]
): EditableUser[] {
    return data.map((user, index) => {
        const userId = uuidv4();
        
        // Buscar errores y warnings para esta fila
        const rowErrors: Record<string, string> = {};
        const rowWarnings: Record<string, string> = {};
        
        errors
            .filter(err => err.row === user.row_number)
            .forEach(err => {
                rowErrors[err.field] = err.message;
            });
        
        warnings
            .filter(warn => warn.row === user.row_number)
            .forEach(warn => {
                rowWarnings[warn.field] = warn.message;
            });
        
        return {
            id: userId,
            row_number: user.row_number || index + 2, // +2 por header Excel
            nombre: user.nombre || '',
            apellido: user.apellido || '',
            email: user.email || '',
            tipo_documento: user.tipo_documento || 'dni',
            numero_documento: user.numero_documento || '',
            rol: user.rol || 'client',
            estado: user.estado || 'active',
            telefono: user.telefono || '',
            organizaciones: user.organizaciones || [],
            _errors: rowErrors,
            _warnings: rowWarnings,
            _isValid: Object.keys(rowErrors).length === 0,
            _isNew: false,
            _isModified: false,
            _originalData: { ...user },
        };
    });
}

export function useEditableUsers(): UseEditableUsersReturn {
    
    const [users, setUsers] = useState<EditableUser[]>([]);

    // Función para cargar usuarios (llamar manualmente después de validar)
    const loadUsers = useCallback((data: any[], errors: ValidationError[], warnings: ValidationWarning[] = []) => {
        const converted = convertToEditableUsers(data, errors, warnings);
        setUsers(converted);
    }, []);

    const validateField = useCallback((field: keyof EditableUser, value: any, user: EditableUser): string | null => {
        switch (field) {
            case 'nombre':
                if (!value || value.trim() === '') return 'Nombre es requerido';
                if (value.length < 2) return 'Nombre muy corto (mínimo 2 caracteres)';
                if (value.length > 100) return 'Nombre muy largo (máximo 100 caracteres)';
                return null;
            
            case 'apellido':
                if (!value || value.trim() === '') return 'Apellido es requerido';
                if (value.length < 2) return 'Apellido muy corto (mínimo 2 caracteres)';
                if (value.length > 100) return 'Apellido muy largo (máximo 100 caracteres)';
                return null;
            
            case 'email':
                if (!value) return 'Email es requerido';
                if (!emailRegex.test(value)) return 'Email inválido';
                return null;
            
            case 'tipo_documento':
                if (!['dni', 'ce', 'passport', 'ruc'].includes(value)) {
                    return 'Tipo de documento inválido';
                }
                return null;
            
            case 'numero_documento':
                if (!value || value.trim() === '') return 'Número de documento es requerido';
                // La validación de formato se hace en validateUser porque necesita tipo_documento
                return null;
            
            case 'rol':
                if (!['client', 'root', 'admin'].includes(value)) {
                    return 'Rol inválido (client, root o admin)';
                }
                return null;
            
            case 'estado':
                if (!['active', 'inactive'].includes(value)) {
                    return 'Estado inválido (active o inactive)';
                }
                return null;
            
            case 'telefono':
                // No validamos formato de teléfono - es opcional y puede tener varios formatos
                return null;
            
            default:
                return null;
        }
    }, []);

    const validateUser = useCallback((user: EditableUser) => {
        const errors: Record<string, string> = {};
        const warnings: Record<string, string> = {};
        
        // Validar campos requeridos
        const requiredFields: (keyof EditableUser)[] = [
            'nombre', 'apellido', 'email', 'tipo_documento', 'numero_documento', 'rol', 'estado'
        ];
        
        requiredFields.forEach(field => {
            const error = validateField(field, user[field], user);
            if (error) {
                errors[field] = error;
            }
        });
        
        // Validar teléfono si existe
        if (user.telefono) {
            const error = validateField('telefono', user.telefono, user);
            if (error) {
                errors['telefono'] = error;
            }
        }
        
        // Validar formato de documento según tipo
        if (user.tipo_documento && user.numero_documento) {
            const doc = user.numero_documento.trim();
            switch (user.tipo_documento.toUpperCase()) {
                case 'DNI':
                    if (!/^\d{8}$/.test(doc)) {
                        errors['numero_documento'] = 'DNI debe tener exactamente 8 dígitos numéricos';
                    }
                    break;
                case 'RUC':
                    if (!/^\d{11}$/.test(doc)) {
                        errors['numero_documento'] = 'RUC debe tener exactamente 11 dígitos numéricos';
                    }
                    break;
                case 'CE':
                    if (!/^\d{12}$/.test(doc)) {
                        errors['numero_documento'] = 'CE debe tener exactamente 12 dígitos numéricos';
                    }
                    break;
                case 'PASAPORTE':
                    if (!/^[A-Za-z0-9]{6,20}$/.test(doc)) {
                        errors['numero_documento'] = 'Pasaporte debe tener entre 6 y 20 caracteres alfanuméricos';
                    }
                    break;
            }
        }
        
        // Validar organizaciones (solo validar formato si existen, no son requeridas)
        if (user.organizaciones && user.organizaciones.length > 0) {
            user.organizaciones.forEach((org, idx) => {
                if (org.supervisor_email && !emailRegex.test(org.supervisor_email)) {
                    errors[`organizaciones.${idx}.supervisor_email`] = `Email supervisor inválido en organización ${idx + 1}`;
                }
            });
        }
        
        return { errors, warnings };
    }, [validateField]);

    const updateUser = useCallback((id: string, field: keyof EditableUser, value: any) => {
        setUsers(prev => prev.map(user => {
            if (user.id !== id) return user;
            
            const updatedUser = { ...user, [field]: value, _isModified: true };
            const validation = validateUser(updatedUser);
            
            // Preservar errores originales que NO son del campo que se está editando
            // Si el campo es 'organizaciones', eliminar todos los errores de organizaciones.*
            const preservedErrors: Record<string, string> = {};
            
            // Campos que al editarse deben limpiar errores de duplicados
            const duplicateRelatedFields = ['email', 'numero_documento', 'rol', 'nombre', 'apellido', 'tipo_documento', 'estado'];
            const isDuplicateRelated = duplicateRelatedFields.includes(field as string);
            
            Object.entries(user._errors || {}).forEach(([key, msg]) => {
                // Si estamos editando 'organizaciones', eliminar cualquier error que empiece con 'organizaciones'
                if (field === 'organizaciones') {
                    if (!key.startsWith('organizaciones')) {
                        preservedErrors[key] = msg;
                    }
                } else if (isDuplicateRelated && (msg.toLowerCase().includes('duplicado') || msg.toLowerCase().includes('no coincide'))) {
                    // Si editamos un campo relacionado con duplicados, eliminar errores de duplicados
                    // No preservar este error
                } else {
                    // Para otros campos, solo eliminar el error del campo específico
                    if (key !== field) {
                        preservedErrors[key] = msg;
                    }
                }
            });
            
            // Combinar errores preservados con los nuevos de validación
            // Los errores de validación tienen prioridad (sobrescriben)
            const combinedErrors = { ...preservedErrors, ...validation.errors };
            
            return {
                ...updatedUser,
                _errors: combinedErrors,
                _warnings: { ...user._warnings, ...validation.warnings },
                _isValid: Object.keys(combinedErrors).length === 0,
            };
        }));
    }, [validateUser]);

    const updateOrganization = useCallback((userId: string, orgIndex: number, field: string, value: string) => {
        setUsers(prev => prev.map(user => {
            if (user.id !== userId) return user;
            
            const updatedOrgs = [...user.organizaciones];
            updatedOrgs[orgIndex] = { ...updatedOrgs[orgIndex], [field]: value };
            
            const updatedUser = { ...user, organizaciones: updatedOrgs, _isModified: true };
            const validation = validateUser(updatedUser);
            
            // Preservar errores que NO son del campo específico que se está editando
            // Los errores de org tienen formato: "organizaciones.0.ruc", "organizaciones.0.supervisor_email"
            const fieldKey = `organizaciones.${orgIndex}.${field}`;
            const preservedErrors: Record<string, string> = {};
            
            // Copiar todos los errores EXCEPTO el del campo específico
            Object.entries(user._errors || {}).forEach(([key, msg]) => {
                if (key !== fieldKey) {
                    preservedErrors[key] = msg;
                }
            });
            
            // Combinar: errores preservados + errores de validación
            const combinedErrors = { ...preservedErrors, ...validation.errors };
            
            return {
                ...updatedUser,
                _errors: combinedErrors,
                _warnings: { ...user._warnings, ...validation.warnings },
                _isValid: Object.keys(combinedErrors).length === 0,
            };
        }));
    }, [validateUser]);

    const addNewRow = useCallback(() => {
        const newUser: EditableUser = {
            id: uuidv4(),
            row_number: users.length + 2, // +2 por header Excel
            nombre: '',
            apellido: '',
            email: '',
            tipo_documento: 'dni',
            numero_documento: '',
            rol: 'client',
            estado: 'active',
            telefono: '',
            organizaciones: [],
            _errors: {
                nombre: 'Nombre es requerido',
                apellido: 'Apellido es requerido',
                email: 'Email es requerido',
                numero_documento: 'Número de documento es requerido',
            },
            _warnings: { organizaciones: 'Usuario sin organizaciones asignadas' },
            _isValid: false,
            _isNew: true,
            _isModified: false,
        };
        
        setUsers(prev => [...prev, newUser]);
    }, [users.length]);

    const deleteRow = useCallback((id: string) => {
        setUsers(prev => prev.filter(user => user.id !== id));
    }, []);

    const stats = useMemo(() => {
        const errorCount = users.filter(u => !u._isValid).length;
        const warningCount = users.reduce((acc, u) => acc + Object.keys(u._warnings).length, 0);
        const validCount = users.filter(u => u._isValid).length;
        
        return { errorCount, warningCount, validCount };
    }, [users]);

    const isValid = stats.errorCount === 0 && users.length > 0;

    return {
        users,
        setUsers,
        loadUsers,
        updateUser,
        updateOrganization,
        addNewRow,
        deleteRow,
        validateUser,
        isValid,
        errorCount: stats.errorCount,
        warningCount: stats.warningCount,
        validCount: stats.validCount,
    };
}
