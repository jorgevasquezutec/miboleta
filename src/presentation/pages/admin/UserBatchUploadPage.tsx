import { useState, useEffect, useCallback } from 'react';
import { useDocumentTitle } from '@/presentation/hooks';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/presentation/components/ui/card';
import { Upload, Download, FileText, Loader2, X, ArrowLeft, CheckCircle2, AlertCircle, AlertTriangle } from 'lucide-react';
import { TemplateConfigModal } from '@/presentation/components/bulkUpload/TemplateConfigModal';
import { UserDataGrid } from '@/presentation/components/bulkUpload/UserDataGrid';
import { useEditableUsers } from '@/presentation/hooks/useEditableUsers';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import type { BulkUploadConfigData, TemplateConfig } from '@/domain/types/bulkUserUpload.types';
import { toast } from 'sonner';

export function UserBatchUploadPage() {
    useDocumentTitle('Nueva Carga de Usuarios');
    const navigate = useNavigate();
    const [file, setFile] = useState<File | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [configData, setConfigData] = useState<BulkUploadConfigData | null>(null);
    const [isValidating, setIsValidating] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [sendEmails] = useState(true);
    const [isDragging, setIsDragging] = useState(false);

    // Estados para el editor
    const [showEditor, setShowEditor] = useState(false);

    // Hook de usuarios editables (sin parámetros iniciales)
    const {
        users,
        loadUsers,
        updateUser,
        deleteRow,
        errorCount,
        warningCount,
        validCount,
    } = useEditableUsers();

    useEffect(() => {
        bulkUserUploadService.getConfig().then(setConfigData);
    }, []);

    const handleDownloadTemplate = async (config: TemplateConfig) => {
        try {
            await bulkUserUploadService.downloadTemplate(config);
            toast.success('Template descargado exitosamente');
            setIsModalOpen(false);
        } catch (error) {
            toast.error('Error al descargar template');
        }
    };

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);

        const droppedFile = e.dataTransfer.files[0];
        if (!droppedFile) return;

        if (!droppedFile.name.endsWith('.xlsx') && !droppedFile.name.endsWith('.xls')) {
            toast.error('Por favor sube un archivo Excel (.xlsx)');
            return;
        }

        setFile(droppedFile);
        setShowEditor(false);
        toast.success('Archivo cargado correctamente');
    }, []);

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (!selectedFile) return;

        if (!selectedFile.name.endsWith('.xlsx') && !selectedFile.name.endsWith('.xls')) {
            toast.error('Por favor selecciona un archivo Excel (.xlsx)');
            return;
        }

        setFile(selectedFile);
        setShowEditor(false);
        toast.success('Archivo seleccionado correctamente');
    };

    const handleRemoveFile = () => {
        setFile(null);
        setShowEditor(false);
    };

    const handleValidate = async () => {
        if (!file) return;

        setIsValidating(true);
        try {
            const result = await bulkUserUploadService.validateFile(file);

            // Cargar usuarios en el hook
            loadUsers(result.data, result.errors, result.warnings);
            setShowEditor(true);

            if (result.summary.errors > 0) {
                toast.warning(`Se encontraron ${result.summary.errors} errores - puedes corregirlos en el editor`);
            } else if (result.summary.warnings > 0) {
                toast.info(`Archivo validado con ${result.summary.warnings} advertencias`);
            } else {
                toast.success(`Archivo validado: ${result.summary.valid} usuarios listos`);
            }
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || 'Error al validar archivo';
            toast.error(errorMessage);
        } finally {
            setIsValidating(false);
        }
    };

    const handleCellChange = useCallback((rowId: string, field: string, value: any) => {
        updateUser(rowId, field as any, value);
    }, [updateUser]);

    const handleDeleteRow = useCallback((id: string) => {
        deleteRow(id);
    }, [deleteRow]);

    const handleConfirmUpload = async () => {
        // Validación local primero
        if (errorCount > 0) {
            toast.error('Corrige los errores antes de continuar');
            return;
        }

        // Limpiar y formatear usuarios
        const formattedUsers = users.map(user => {
            // Filtrar organizaciones vacías y asegurar que ruc/supervisor sean strings
            const cleanOrgs = (user.organizaciones || [])
                .filter(org => org && org.ruc && String(org.ruc).trim() !== '')
                .map(org => ({
                    ruc: String(org.ruc || '').trim(),
                    supervisor_email: String(org.supervisor_email || '').trim(),
                    // RP1-C: rol(es)/fecha de ingreso/saldo de vacaciones por
                    // organización. null (no '') cuando están vacíos para que
                    // las reglas 'date'/'numeric' del backend no fallen.
                    roles: Array.isArray(org.roles) ? org.roles.filter(Boolean) : [],
                    hire_date: org.hire_date && String(org.hire_date).trim() !== ''
                        ? String(org.hire_date).trim()
                        : null,
                    vacation_balance_initial: org.vacation_balance_initial !== undefined
                        && org.vacation_balance_initial !== null
                        && String(org.vacation_balance_initial).trim() !== ''
                        ? String(org.vacation_balance_initial).trim()
                        : null,
                }));

            return {
                nombre: user.nombre,
                apellido: user.apellido,
                email: user.email,
                tipo_documento: user.tipo_documento,
                numero_documento: user.numero_documento,
                rol: user.rol,
                estado: user.estado,
                telefono: user.telefono || '',
                organizaciones: cleanOrgs.length > 0 ? cleanOrgs : [],
                row_number: user.row_number,
            };
        });

        setIsUploading(true);
        try {
            // 1. Revalidar con el backend antes de procesar
            toast.info('Validando datos...');
            const validation = await bulkUserUploadService.validateData(formattedUsers);

            if (!validation.valid || validation.errors.length > 0) {
                // Hay errores del backend - recargar con los nuevos errores
                loadUsers(formattedUsers, validation.errors, validation.warnings);
                toast.error(`Se encontraron ${validation.errors.length} errores. Por favor corrígelos.`);
                setIsUploading(false);
                return;
            }

            // 2. Si pasó la validación, proceder con la carga
            const result = await bulkUserUploadService.uploadEditedData(formattedUsers, {
                send_welcome_emails: sendEmails,
                update_existing: false,
            });

            toast.success(`Carga iniciada: ${result.batch.total_rows} usuarios`);
            navigate(`/users/batch/${result.batch.id}`);
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || 'Error al procesar usuarios';
            toast.error(errorMessage);
        } finally {
            setIsUploading(false);
        }
    };

    const handleBack = () => {
        navigate('/users/batch');
    };

    return (
        <div className="space-y-6 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex items-center gap-3 sm:gap-4">
                <Button variant="outline" size="icon" onClick={handleBack} className="h-9 w-9 sm:h-10 sm:w-10 flex-shrink-0">
                    <ArrowLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold">Nueva Carga Masiva de Usuarios</h1>
                    <p className="text-gray-600 mt-1 text-sm sm:text-base">
                        Sube un archivo Excel con los datos de usuarios a crear
                    </p>
                </div>
            </div>

            {/* Si no está en modo editor, mostrar pasos 1 y 2 */}
            {!showEditor && (
                <>
                    {/* Template Download Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Download className="h-5 w-5" />
                                Paso 1: Descargar Template
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-gray-600">
                                Descarga el template Excel personalizado y llénalo con los datos de los usuarios.
                            </p>
                            <Button onClick={() => setIsModalOpen(true)} variant="outline">
                                <Download className="h-4 w-4 mr-2" />
                                Descargar Template Excel
                            </Button>
                        </CardContent>
                    </Card>

                    {/* File Upload Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="h-5 w-5" />
                                Paso 2: Subir Archivo
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Drop Zone */}
                            <div
                                onDrop={handleDrop}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                className={`
                                    border-2 border-dashed rounded-lg p-12 text-center transition-colors cursor-pointer
                                    ${isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'}
                                    ${file ? 'bg-green-50 border-green-300' : ''}
                                `}
                                onClick={() => document.getElementById('file-input')?.click()}
                            >
                                <input
                                    id="file-input"
                                    type="file"
                                    accept=".xlsx,.xls"
                                    onChange={handleFileSelect}
                                    className="hidden"
                                />

                                {file ? (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-center gap-3">
                                            <FileText className="h-12 w-12 text-green-600" />
                                            <div className="text-left">
                                                <p className="font-medium text-lg">{file.name}</p>
                                                <p className="text-sm text-gray-500">
                                                    {bulkUserUploadService.formatFileSize(file.size)}
                                                </p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleRemoveFile();
                                                }}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <p className="text-sm text-green-600">
                                            ✓ Archivo listo. Click en "Validar y Editar" para continuar.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <Upload className="mx-auto h-16 w-16 text-gray-400" />
                                        <div>
                                            <p className="text-lg font-medium">
                                                Arrastra tu archivo Excel aquí
                                            </p>
                                            <p className="text-sm text-gray-500 mt-1">
                                                o haz click para seleccionar
                                            </p>
                                        </div>
                                        <p className="text-xs text-gray-400">
                                            Formatos soportados: .xlsx, .xls (máx. 10MB)
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Validate Button */}
                            {file && (
                                <Button
                                    onClick={handleValidate}
                                    disabled={isValidating}
                                    className="w-full"
                                    size="lg"
                                >
                                    {isValidating ? (
                                        <>
                                            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                                            Validando Archivo...
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle2 className="mr-2 h-5 w-5" />
                                            Validar y Editar
                                        </>
                                    )}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}

            {/* Editor Mode - DataGrid inline */}
            {showEditor && (
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Editor de Usuarios
                            </CardTitle>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleRemoveFile}
                            >
                                <X className="h-4 w-4 mr-2" />
                                Subir Otro Archivo
                            </Button>
                        </div>

                        {/* Summary Stats */}
                        <div className="flex gap-4 mt-4">
                            <div className="flex items-center gap-2 text-sm">
                                <span className="font-medium text-gray-700">Total:</span>
                                <span className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded">{users.length}</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                <span className="text-green-700">{validCount} válidos</span>
                            </div>
                            {errorCount > 0 && (
                                <div className="flex items-center gap-2 text-sm">
                                    <AlertCircle className="h-4 w-4 text-red-600" />
                                    <span className="text-red-700">{errorCount} con errores</span>
                                </div>
                            )}
                            {warningCount > 0 && (
                                <div className="flex items-center gap-2 text-sm">
                                    <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                    <span className="text-yellow-700">{warningCount} advertencias</span>
                                </div>
                            )}
                        </div>

                        {/* Panel de errores */}
                        {errorCount > 0 && (
                            <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p className="font-medium text-sm text-red-900 mb-2">
                                    ⚠️ Errores encontrados ({errorCount} filas con problemas):
                                </p>
                                <div className="max-h-32 overflow-y-auto space-y-1">
                                    {users.filter(u => !u._isValid).slice(0, 10).map(user => (
                                        <div key={user.id} className="text-xs text-red-700">
                                            <span className="font-medium">Fila {user.row_number}</span>{' '}
                                            <span className="text-red-500">({user.rol})</span>:{' '}
                                            {Object.entries(user._errors).map(([field, msg]) => (
                                                <span key={field} className="mr-2">
                                                    {field}: {msg}
                                                </span>
                                            ))}
                                        </div>
                                    ))}
                                    {errorCount > 10 && (
                                        <p className="text-xs text-red-600 italic">
                                            ... y {errorCount - 10} filas más con errores (pasa el cursor sobre el ícono ⚠️ para ver detalle)
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardHeader>

                    <CardContent className="p-0">
                        {/* DataGrid */}
                        <div className="h-125 border-t">
                            <UserDataGrid
                                users={users}
                                configData={configData}
                                onCellChange={handleCellChange}
                                onDeleteRow={handleDeleteRow}
                            />
                        </div>

                        {/* Actions */}
                        <div className="p-4 border-t bg-gray-50 space-y-4">
                            {/* Info sobre comportamiento */}
                            <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <p className="font-medium text-sm text-blue-900">ℹ️ Comportamiento automático:</p>
                                <ul className="text-sm text-blue-800 mt-1 ml-4 list-disc">
                                    <li>Se generará una clave temporal aleatoria para cada usuario</li>
                                    <li>Se enviará un email de bienvenida con instrucciones de acceso</li>
                                </ul>
                            </div>

                            <Button
                                onClick={handleConfirmUpload}
                                disabled={errorCount > 0 || isUploading || users.length === 0}
                                className="w-full"
                                size="lg"
                            >
                                {isUploading ? (
                                    <>
                                        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                                        Procesando...
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle2 className="mr-2 h-5 w-5" />
                                        Procesar {validCount} Usuarios
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Template Config Modal */}
            <TemplateConfigModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onDownload={handleDownloadTemplate}
                configData={configData}
            />
        </div>
    );
}

export default UserBatchUploadPage;
