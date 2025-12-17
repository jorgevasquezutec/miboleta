import { useMemo } from 'react';
import type { EditableUser } from '@/domain/types/bulkUserUpload.types';
import { AlertCircle, AlertTriangle, CheckCircle2, ChevronRight } from 'lucide-react';
import { ScrollArea } from '@/presentation/components/ui/scroll-area';
import { Badge } from '@/presentation/components/ui/badge';

interface ValidationPanelProps {
    users: EditableUser[];
    onNavigateToError?: (userId: string, field: string) => void;
}

interface ErrorItem {
    userId: string;
    row: number;
    field: string;
    message: string;
    type: 'error' | 'warning';
}

export function ValidationPanel({ users, onNavigateToError }: ValidationPanelProps) {
    
    const { errors, warnings, validCount } = useMemo(() => {
        const errors: ErrorItem[] = [];
        const warnings: ErrorItem[] = [];
        let validCount = 0;
        
        users.forEach(user => {
            if (user._isValid) {
                validCount++;
            }
            
            Object.entries(user._errors).forEach(([field, message]) => {
                errors.push({
                    userId: user.id,
                    row: user.row_number,
                    field,
                    message,
                    type: 'error',
                });
            });
            
            Object.entries(user._warnings).forEach(([field, message]) => {
                warnings.push({
                    userId: user.id,
                    row: user.row_number,
                    field,
                    message,
                    type: 'warning',
                });
            });
        });
        
        return { errors, warnings, validCount };
    }, [users]);

    const getFieldLabel = (field: string): string => {
        const labels: Record<string, string> = {
            nombre: 'Nombre',
            apellido: 'Apellido',
            email: 'Email',
            tipo_documento: 'Tipo Documento',
            numero_documento: 'Nro. Documento',
            rol: 'Rol',
            estado: 'Estado',
            telefono: 'Teléfono',
            organizaciones: 'Organizaciones',
        };
        
        // Manejar campos anidados como "organizaciones.0.ruc"
        if (field.startsWith('organizaciones.')) {
            return 'Organizaciones';
        }
        
        return labels[field] || field;
    };

    return (
        <div className="w-80 border-l bg-gray-50 flex flex-col h-full">
            {/* Header */}
            <div className="p-4 border-b bg-white">
                <h3 className="font-semibold text-lg mb-3">Validación</h3>
                
                <div className="space-y-2">
                    <div className="flex items-center justify-between p-2 bg-green-50 rounded">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                            <span className="text-sm font-medium text-green-900">Válidos</span>
                        </div>
                        <Badge variant="default" className="bg-green-600">
                            {validCount}
                        </Badge>
                    </div>
                    
                    <div className="flex items-center justify-between p-2 bg-red-50 rounded">
                        <div className="flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 text-red-600" />
                            <span className="text-sm font-medium text-red-900">Errores</span>
                        </div>
                        <Badge variant="destructive">
                            {errors.length}
                        </Badge>
                    </div>
                    
                    <div className="flex items-center justify-between p-2 bg-yellow-50 rounded">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-4 w-4 text-yellow-600" />
                            <span className="text-sm font-medium text-yellow-900">Advertencias</span>
                        </div>
                        <Badge variant="secondary" className="bg-yellow-600 text-white">
                            {warnings.length}
                        </Badge>
                    </div>
                </div>
            </div>

            {/* Lista de Errores */}
            <ScrollArea className="flex-1">
                <div className="p-4 space-y-4">
                    {/* Errores */}
                    {errors.length > 0 && (
                        <div>
                            <h4 className="font-medium text-sm text-red-900 mb-2 flex items-center gap-2">
                                <AlertCircle className="h-4 w-4" />
                                Errores Críticos
                            </h4>
                            <div className="space-y-2">
                                {errors.map((error, idx) => (
                                    <div
                                        key={`error-${idx}`}
                                        className="p-3 bg-white border border-red-200 rounded-lg cursor-pointer hover:border-red-400 hover:shadow-sm transition-all"
                                        onClick={() => onNavigateToError?.(error.userId, error.field)}
                                    >
                                        <div className="flex items-start justify-between gap-2 mb-1">
                                            <span className="text-xs font-semibold text-red-700">
                                                Fila {error.row}
                                            </span>
                                            <ChevronRight className="h-3 w-3 text-red-400 flex-shrink-0 mt-0.5" />
                                        </div>
                                        <div className="text-xs font-medium text-gray-700 mb-1">
                                            {getFieldLabel(error.field)}
                                        </div>
                                        <div className="text-xs text-gray-600">
                                            {error.message}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    
                    {/* Advertencias */}
                    {warnings.length > 0 && (
                        <div>
                            <h4 className="font-medium text-sm text-yellow-900 mb-2 flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4" />
                                Advertencias
                            </h4>
                            <div className="space-y-2">
                                {warnings.map((warning, idx) => (
                                    <div
                                        key={`warning-${idx}`}
                                        className="p-3 bg-white border border-yellow-200 rounded-lg cursor-pointer hover:border-yellow-400 hover:shadow-sm transition-all"
                                        onClick={() => onNavigateToError?.(warning.userId, warning.field)}
                                    >
                                        <div className="flex items-start justify-between gap-2 mb-1">
                                            <span className="text-xs font-semibold text-yellow-700">
                                                Fila {warning.row}
                                            </span>
                                            <ChevronRight className="h-3 w-3 text-yellow-400 flex-shrink-0 mt-0.5" />
                                        </div>
                                        <div className="text-xs font-medium text-gray-700 mb-1">
                                            {getFieldLabel(warning.field)}
                                        </div>
                                        <div className="text-xs text-gray-600">
                                            {warning.message}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    
                    {/* Sin errores ni advertencias */}
                    {errors.length === 0 && warnings.length === 0 && (
                        <div className="text-center py-8">
                            <CheckCircle2 className="h-12 w-12 text-green-500 mx-auto mb-3" />
                            <p className="text-sm font-medium text-gray-700">
                                ¡Todo perfecto!
                            </p>
                            <p className="text-xs text-gray-500 mt-1">
                                No hay errores ni advertencias
                            </p>
                        </div>
                    )}
                </div>
            </ScrollArea>
        </div>
    );
}
