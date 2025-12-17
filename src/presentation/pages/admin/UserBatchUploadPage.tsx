import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/presentation/components/ui/card';
import { Upload, Download, FileText, Loader2, X, ArrowLeft, CheckCircle2, AlertCircle, AlertTriangle } from 'lucide-react';
import { TemplateConfigModal } from '@/presentation/components/bulkUpload/TemplateConfigModal';
import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';
import type { BulkUploadConfigData, TemplateConfig } from '@/domain/types/bulkUserUpload.types';
import { toast } from 'sonner';

export function UserBatchUploadPage() {
    const navigate = useNavigate();
    const [file, setFile] = useState<File | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [configData, setConfigData] = useState<BulkUploadConfigData | null>(null);
    const [isValidating, setIsValidating] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    // Siempre enviar emails porque siempre se genera clave dinámica nueva
    const [sendEmails] = useState(true);
    // const [updateExisting, setUpdateExisting] = useState(true); // TODO: Implementar en futuro
    const [isDragging, setIsDragging] = useState(false);

    // Estados para preview
    const [showPreview, setShowPreview] = useState(false);
    const [previewData, setPreviewData] = useState<any[]>([]);
    const [validationErrors, setValidationErrors] = useState<any[]>([]);
    const [validationWarnings, setValidationWarnings] = useState<any[]>([]);
    const [validationSummary, setValidationSummary] = useState<any>(null);

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
        setShowPreview(false); // Reset preview
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
        setShowPreview(false); // Reset preview
        toast.success('Archivo seleccionado correctamente');
    };

    const handleRemoveFile = () => {
        setFile(null);
        setShowPreview(false);
        setPreviewData([]);
        setValidationErrors([]);
        setValidationWarnings([]);
        setValidationSummary(null);
    };

    const handleValidate = async () => {
        if (!file) return;

        setIsValidating(true);
        try {
            const result = await bulkUserUploadService.validateFile(file);

            setPreviewData(result.data);
            setValidationErrors(result.errors);
            setValidationWarnings(result.warnings);
            setValidationSummary(result.summary);
            setShowPreview(true);

            if (result.summary.errors > 0) {
                toast.error(`Se encontraron ${result.summary.errors} errores en el archivo`);
            } else if (result.summary.warnings > 0) {
                toast.warning(`El archivo tiene ${result.summary.warnings} advertencias`);
            } else {
                toast.success(`Archivo validado: ${result.summary.valid} usuarios listos para cargar`);
            }
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || 'Error al validar archivo';
            toast.error(errorMessage);
        } finally {
            setIsValidating(false);
        }
    };

    const handleConfirmUpload = async () => {
        if (!file) return;

        setIsUploading(true);
        try {
            const result = await bulkUserUploadService.uploadFile(file, {
                send_welcome_emails: sendEmails,
                update_existing: false,
            });

            toast.success(`Carga iniciada: ${result.batch.total_rows} usuarios`);

            // Navegar a página de detalle
            navigate(`/users/batch/${result.batch.uuid}`);
        } catch (error: any) {
            const errorMessage = error.response?.data?.message || 'Error al iniciar la carga';
            toast.error(errorMessage);
        } finally {
            setIsUploading(false);
        }
    };

    const handleBack = () => {
        navigate('/users/batch');
    };

    return (
        <div className="space-y-6 max-w-6xl mx-auto">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" onClick={handleBack}>
                    <ArrowLeft className="h-4 w-4" />
                </Button>
                <div>
                    <h1 className="text-3xl font-bold">Nueva Carga Masiva de Usuarios</h1>
                    <p className="text-gray-600 mt-1">
                        Sube un archivo Excel con los datos de usuarios a crear
                    </p>
                </div>
            </div>

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
                        Paso 2: Subir y Validar Archivo
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Drop Zone */}
                    {!showPreview && (
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
                                        ✓ Archivo listo. Click en "Validar Archivo" para continuar.
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
                    )}

                    {/* Validate Button */}
                    {file && !showPreview && (
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
                                    Validar Archivo
                                </>
                            )}
                        </Button>
                    )}

                    {/* Preview Section */}
                    {showPreview && validationSummary && (
                        <div className="space-y-4">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-4 gap-4">
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p className="text-sm text-blue-600 font-medium">Total Filas</p>
                                    <p className="text-2xl font-bold text-blue-900">{validationSummary.total}</p>
                                </div>
                                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <p className="text-sm text-green-600 font-medium">Válidos</p>
                                    <p className="text-2xl font-bold text-green-900">{validationSummary.valid}</p>
                                </div>
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <p className="text-sm text-red-600 font-medium">Errores</p>
                                    <p className="text-2xl font-bold text-red-900">{validationSummary.errors}</p>
                                </div>
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <p className="text-sm text-yellow-600 font-medium">Advertencias</p>
                                    <p className="text-2xl font-bold text-yellow-900">{validationSummary.warnings}</p>
                                </div>
                            </div>

                            {/* Errors */}
                            {validationErrors.length > 0 && (
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-start gap-2">
                                        <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="font-semibold text-red-900 mb-2">
                                                Errores encontrados ({validationErrors.length})
                                            </h3>
                                            <div className="space-y-1 max-h-40 overflow-y-auto">
                                                {validationErrors.slice(0, 10).map((error, idx) => (
                                                    <p key={idx} className="text-sm text-red-700">
                                                        Fila {error.row}: {error.field} - {error.message}
                                                    </p>
                                                ))}
                                                {validationErrors.length > 10 && (
                                                    <p className="text-sm text-red-600 italic">
                                                        ... y {validationErrors.length - 10} errores más
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Warnings */}
                            {validationWarnings.length > 0 && (
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div className="flex items-start gap-2">
                                        <AlertTriangle className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="font-semibold text-yellow-900 mb-2">
                                                Advertencias ({validationWarnings.length})
                                            </h3>
                                            <div className="space-y-1 max-h-32 overflow-y-auto">
                                                {validationWarnings.slice(0, 5).map((warning, idx) => (
                                                    <p key={idx} className="text-sm text-yellow-700">
                                                        Fila {warning.row}: {warning.field} - {warning.message}
                                                    </p>
                                                ))}
                                                {validationWarnings.length > 5 && (
                                                    <p className="text-sm text-yellow-600 italic">
                                                        ... y {validationWarnings.length - 5} advertencias más
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Preview Table */}
                            {previewData.length > 0 && (
                                <div className="border rounded-lg overflow-hidden">
                                    <div className="bg-gray-50 px-4 py-3 border-b">
                                        <h3 className="font-semibold">Preview de Usuarios</h3>
                                        <p className="text-sm text-gray-600">Mostrando primeros 10 registros</p>
                                    </div>
                                    <div className="overflow-x-auto max-h-96">
                                        <table className="w-full text-sm">
                                            <thead className="bg-gray-100 sticky top-0">
                                                <tr>
                                                    <th className="px-4 py-2 text-left">#</th>
                                                    <th className="px-4 py-2 text-left">Nombre</th>
                                                    <th className="px-4 py-2 text-left">Email</th>
                                                    <th className="px-4 py-2 text-left">Rol</th>
                                                    <th className="px-4 py-2 text-left">Documento</th>
                                                    <th className="px-4 py-2 text-left">Orgs</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {previewData.slice(0, 10).map((user, idx) => (
                                                    <tr key={idx} className="border-b hover:bg-gray-50">
                                                        <td className="px-4 py-2 text-gray-600">{user.row_number}</td>
                                                        <td className="px-4 py-2 font-medium">
                                                            {user.nombre} {user.apellido}
                                                        </td>
                                                        <td className="px-4 py-2 text-gray-600">{user.email}</td>
                                                        <td className="px-4 py-2">
                                                            <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                                                {user.rol}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-2 text-gray-600 text-xs">
                                                            {user.tipo_documento}: {user.numero_documento}
                                                        </td>
                                                        <td className="px-4 py-2 text-gray-600 text-xs">
                                                            {user.organizaciones?.length || 0} org(s)
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Actions */}
                            <div className="flex gap-3">
                                <Button
                                    variant="outline"
                                    onClick={handleRemoveFile}
                                    className="flex-1"
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Cancelar y Subir Otro
                                </Button>
                                <Button
                                    onClick={handleConfirmUpload}
                                    disabled={validationSummary.errors > 0 || isUploading}
                                    className="flex-1"
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
                                            Confirmar y Procesar {validationSummary.valid} Usuarios
                                        </>
                                    )}
                                </Button>
                            </div>

                            {/* Info sobre comportamiento automático */}
                            <div className="space-y-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <p className="font-medium text-sm text-blue-900">ℹ️ Comportamiento automático:</p>
                                <ul className="text-sm text-blue-800 space-y-1 ml-4 list-disc">
                                    <li>Se generará una clave temporal aleatoria para cada usuario</li>
                                    <li>Se enviará un email de bienvenida con las instrucciones de acceso</li>
                                    <li>Los usuarios deberán cambiar su contraseña en el primer inicio de sesión</li>
                                </ul>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

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
