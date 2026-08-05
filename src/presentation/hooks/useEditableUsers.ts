import { useState, useCallback, useMemo } from 'react';
import { v4 as uuidv4 } from 'uuid';
import type { EditableUser, EditableOrganization, ValidationError, ValidationWarning } from '@/domain/types/bulkUserUpload.types';
import { BULK_UPLOAD_ORG_ROLES } from '@/shared/constants';

interface UseEditableUsersReturn {
    users: EditableUser[];
    setUsers: React.Dispatch<React.SetStateAction<EditableUser[]>>;
    loadUsers: (data: any[], errors: ValidationError[], warnings?: ValidationWarning[]) => void;
    updateUser: (id: string, field: keyof EditableUser, value: any) => void;
    updateOrganization: (userId: string, orgIndex: number, field: string, value: string) => void;
    // P2: helpers de organizaciones a nivel de fila (múltiples empresas por
    // usuario). UserDataGrid usa su propio patrón local + onCellChange para
    // las mutaciones de UI (igual que el resto de handlers de organización),
    // así que estos helpers existen principalmente para poder testear la
    // lógica de agregar/quitar organizaciones de forma aislada.
    addOrganization: (userId: string, org?: Partial<EditableOrganization>) => void;
    removeOrganization: (userId: string, orgIndex: number) => void;
    addNewRow: () => void;
    deleteRow: (id: string) => void;
    validateUser: (user: EditableUser) => { errors: Record<string, string>; warnings: Record<string, string> };
    isValid: boolean;
    errorCount: number;
    warningCount: number;
    validCount: number;
}

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const ALLOWED_ORG_ROLES: readonly string[] = BULK_UPLOAD_ORG_ROLES;

// Longitud fija por tipo de documento, para reponer ceros a la izquierda
// (Obs 4). Espeja backend: App\Support\DocumentNumber::PAD_LENGTHS. passport
// no tiene longitud fija y no se rellena.
const DOCUMENT_PAD_LENGTHS: Record<string, number> = {
    dni: 8,
    ruc: 11,
    ce: 12,
};

// Repone el padding de ceros a la izquierda que Excel se come cuando la
// columna "Número de documento" queda en formato General (mismo bug que
// App\Support\DocumentNumber::normalize resuelve en el backend). Solo actúa
// sobre valores puramente numéricos más cortos que la longitud objetivo:
// nunca trunca ni toca un pasaporte alfanumérico.
function padDocumentNumber(tipoDocumento: string | undefined, valor: string): string {
    const trimmed = (valor || '').trim();
    if (!trimmed) return trimmed;

    const padLength = tipoDocumento ? DOCUMENT_PAD_LENGTHS[tipoDocumento.toLowerCase()] : undefined;
    if (padLength && /^\d+$/.test(trimmed) && trimmed.length < padLength) {
        return trimmed.padStart(padLength, '0');
    }

    return trimmed;
}

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

        const tipoDocumento = user.tipo_documento || 'dni';

        return {
            id: userId,
            row_number: user.row_number || index + 2, // +2 por header Excel
            nombre: user.nombre || '',
            apellido: user.apellido || '',
            email: user.email || '',
            tipo_documento: tipoDocumento,
            numero_documento: padDocumentNumber(tipoDocumento, user.numero_documento || ''),
            estado: user.estado || 'active',
            telefono: user.telefono || '',
            birth_date: user.birth_date || '',
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
            
            case 'estado':
                if (!['active', 'inactive'].includes(value)) {
                    return 'Estado inválido (active o inactive)';
                }
                return null;
            
            case 'telefono':
                // No validamos formato de teléfono - es opcional y puede tener varios formatos
                return null;

            case 'birth_date':
                // Opcional: solo se valida formato/rango si trae valor.
                if (!value) return null;
                if (isNaN(Date.parse(value))) return 'Fecha de nacimiento inválida';
                if (new Date(value) > new Date()) return 'Fecha de nacimiento inválida';
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
            'nombre', 'apellido', 'email', 'tipo_documento', 'numero_documento', 'estado'
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

        // Validar fecha de nacimiento si existe (opcional, no forma parte de requiredFields)
        if (user.birth_date) {
            const error = validateField('birth_date', user.birth_date, user);
            if (error) {
                errors['birth_date'] = error;
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
                    // Alineado con el backend (BulkUserUploadService::validateUserRow):
                    // exactamente 12 dígitos, no un rango ni alfanumérico.
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
        
        // Toda fila debe traer al menos una empresa: el rol del usuario vive en
        // la empresa (user_tenant_roles), así que un usuario sin empresa
        // quedaría sin ningún rol. El único rol global es 'root', que no se da
        // de alta por carga masiva.
        const orgsWithRuc = (user.organizaciones || []).filter(
            org => org && org.ruc && String(org.ruc).trim() !== ''
        );

        if (orgsWithRuc.length === 0) {
            errors['organizaciones'] = 'Debes asignar al menos una empresa';
        }

        // Validar formato de las organizaciones que sí tienen empresa. Las
        // filas sin RUC son filas en blanco del grid y no se validan (mismo
        // criterio que BulkUserUploadService::validateUserRow).
        if (user.organizaciones && user.organizaciones.length > 0) {
            user.organizaciones.forEach((org, idx) => {
                if (!org || !org.ruc || String(org.ruc).trim() === '') {
                    return;
                }

                if (org.supervisor_email && !emailRegex.test(org.supervisor_email)) {
                    errors[`organizaciones.${idx}.supervisor_email`] = `Email supervisor inválido en organización ${idx + 1}`;
                }

                // RP1-C: rol(es) por organización. Requerido: es el único lugar
                // donde se declara el rol del usuario.
                if (!org.roles || org.roles.length === 0) {
                    errors[`organizaciones.${idx}.roles`] = `Rol requerido en organización ${idx + 1}`;
                } else {
                    const invalidRoles = org.roles.filter(r => !ALLOWED_ORG_ROLES.includes(r));
                    if (invalidRoles.length > 0) {
                        errors[`organizaciones.${idx}.roles`] = `Rol inválido en organización ${idx + 1}: ${invalidRoles.join(', ')}`;
                    }
                }

                if (org.hire_date && isNaN(Date.parse(org.hire_date))) {
                    errors[`organizaciones.${idx}.hire_date`] = `Fecha de ingreso inválida en organización ${idx + 1}`;
                }

                if (org.vacation_balance_initial !== undefined && org.vacation_balance_initial !== null && org.vacation_balance_initial !== '') {
                    const balance = Number(org.vacation_balance_initial);
                    if (isNaN(balance) || balance < 0) {
                        errors[`organizaciones.${idx}.vacation_balance_initial`] = `Saldo de vacaciones inválido en organización ${idx + 1}`;
                    }
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
            const duplicateRelatedFields = ['email', 'numero_documento', 'nombre', 'apellido', 'tipo_documento', 'estado'];
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

    // P2: agrega una organización vacía (o con overrides) al final de la
    // lista de organizaciones de la fila.
    const addOrganization = useCallback((userId: string, org: Partial<EditableOrganization> = {}) => {
        setUsers(prev => prev.map(user => {
            if (user.id !== userId) return user;

            const updatedOrgs = [...(user.organizaciones || []), { ruc: '', supervisor_email: '', ...org }];
            const updatedUser = { ...user, organizaciones: updatedOrgs, _isModified: true };
            const validation = validateUser(updatedUser);

            return {
                ...updatedUser,
                _errors: validation.errors,
                _warnings: { ...user._warnings, ...validation.warnings },
                _isValid: Object.keys(validation.errors).length === 0,
            };
        }));
    }, [validateUser]);

    // P2: quita la organización en orgIndex. Los errores 'organizaciones.*'
    // existentes se descartan (los índices de las orgs restantes cambian) y
    // se recalculan desde cero contra el estado resultante.
    const removeOrganization = useCallback((userId: string, orgIndex: number) => {
        setUsers(prev => prev.map(user => {
            if (user.id !== userId) return user;

            const updatedOrgs = (user.organizaciones || []).filter((_, i) => i !== orgIndex);
            const updatedUser = { ...user, organizaciones: updatedOrgs, _isModified: true };
            const validation = validateUser(updatedUser);

            const preservedErrors: Record<string, string> = {};
            Object.entries(user._errors || {}).forEach(([key, msg]) => {
                if (!key.startsWith('organizaciones')) {
                    preservedErrors[key] = msg;
                }
            });
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
            estado: 'active',
            telefono: '',
            birth_date: '',
            organizaciones: [],
            _errors: {
                nombre: 'Nombre es requerido',
                apellido: 'Apellido es requerido',
                email: 'Email es requerido',
                numero_documento: 'Número de documento es requerido',
                organizaciones: 'Debes asignar al menos una empresa',
            },
            _warnings: {},
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
        addOrganization,
        removeOrganization,
        addNewRow,
        deleteRow,
        validateUser,
        isValid,
        errorCount: stats.errorCount,
        warningCount: stats.warningCount,
        validCount: stats.validCount,
    };
}
