import { useState, useEffect, useMemo } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useNavigate } from "react-router-dom";
import { ArrowLeft, Upload, FileArchive, CheckCircle, AlertTriangle, Loader2, Building2 } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Label } from "@/presentation/components/ui/label";
import { Checkbox } from "@/presentation/components/ui/checkbox";
import { Badge } from "@/presentation/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/presentation/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/presentation/components/ui/table";
import { MonthYearPicker } from "@/presentation/components/ui/month-year-picker";
import { useDocumentsStore, useAuthStore } from "@/presentation/stores";
import { formatFileSize } from "@/presentation/utils";
import { PageSize, pageSizeLabels } from "@/core/domain/entities";
import { toast } from "sonner";

const PAGE_SIZE_OPTIONS: PageSize[] = ["a10", "a4", "a5", "letter"];

interface DocumentUploadViewProps {
  onBack?: () => void;
}

export function DocumentUploadView({ onBack }: DocumentUploadViewProps) {
  useDocumentTitle('Carga Masiva de Documentos');
  const navigate = useNavigate();

  const handleBack = () => {
    if (onBack) {
      onBack();
    } else {
      navigate(-1);
    }
  };

  const {
    documentTypes,
    fetchDocumentTypes,
    typesLoading,
    zipPreview,
    previewZip,
    clearZipPreview,
    uploadBatch,
    isLoading,
    error,
    clearError,
  } = useDocumentsStore();

  const { user } = useAuthStore();

  // Get user's tenants
  //
  // useMemo porque userTenants es dependencia del efecto de carga inicial: sin
  // él, cuando user.tenants viene undefined (p. ej. root, que no tiene fila en
  // user_tenants) el `|| []` creaba un array NUEVO en cada render, el efecto lo
  // veía siempre distinto y llamaba a fetchDocumentTypes() en bucle.
  const userTenants = useMemo(() => user?.tenants || [], [user?.tenants]);
  const hasMultipleTenants = userTenants.length > 1;

  // Form state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [typeId, setTypeId] = useState<string>("");
  const [period, setPeriod] = useState<string>("");
  const [selectedTenantId, setSelectedTenantId] = useState<string>("");
  const [notifyEmployees, setNotifyEmployees] = useState(true);
  const [requiresSignature, setRequiresSignature] = useState(true);
  // Ítem 36: tamaño de página de las boletas del ZIP, para calibrar
  // correctamente la posición de la firma. 'a10' = formato estándar actual.
  const [pageSize, setPageSize] = useState<PageSize>("a10");
  const [uploadSuccess, setUploadSuccess] = useState<{ batchId: number } | null>(null);

  // Reset form and load document types on mount
  useEffect(() => {
    // Reset all form state when component mounts
    setSelectedFile(null);
    setTypeId("");
    setPeriod("");
    setNotifyEmployees(true);
    setRequiresSignature(true);
    setPageSize("a10");
    setUploadSuccess(null);
    clearZipPreview();
    clearError();

    // Auto-select tenant if user has only one
    if (userTenants.length === 1) {
      setSelectedTenantId(String(userTenants[0].id));
    } else if (userTenants.length > 1) {
      // Auto-select primary tenant for multi-tenant users
      const primaryTenant = userTenants.find(t => t.is_primary);
      if (primaryTenant) {
        setSelectedTenantId(String(primaryTenant.id));
      }
    }

    // Load document types
    fetchDocumentTypes();
  }, [fetchDocumentTypes, clearZipPreview, clearError, userTenants]);

  // Auto-check "Requiere firma digital" when the selected document type requires it
  useEffect(() => {
    if (typeId) {
      const selectedType = documentTypes.find((t) => t.id.toString() === typeId);
      if (selectedType?.requiresSignature) {
        setRequiresSignature(true);
      }
    }
  }, [typeId, documentTypes]);

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.name.toLowerCase().endsWith(".zip")) {
      toast.error("Solo se permiten archivos ZIP");
      return;
    }

    setSelectedFile(file);
    clearZipPreview();
    setUploadSuccess(null);

    try {
      await previewZip(file);
    } catch (err) {
      toast.error("Error al analizar el archivo ZIP");
    }
  };

  const handleDrop = async (e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (!file) return;

    if (!file.name.toLowerCase().endsWith(".zip")) {
      toast.error("Solo se permiten archivos ZIP");
      return;
    }

    setSelectedFile(file);
    clearZipPreview();
    setUploadSuccess(null);

    try {
      await previewZip(file);
    } catch (err) {
      toast.error("Error al analizar el archivo ZIP");
    }
  };

  const handleUpload = async () => {
    if (!selectedFile || !typeId || !period) {
      toast.error("Por favor completa todos los campos requeridos");
      return;
    }

    if (!selectedTenantId) {
      toast.error("Por favor selecciona una organización");
      return;
    }

    try {
      const result = await uploadBatch({
        file: selectedFile,
        typeId: parseInt(typeId),
        period,
        notifyEmployees,
        requiresSignature,
        tenantId: parseInt(selectedTenantId),
        pageSize,
      });

      setUploadSuccess(result);
      toast.success("Archivo enviado para procesamiento");
    } catch (err) {
      toast.error(error || "Error al subir el archivo");
    }
  };

  const resetForm = () => {
    setSelectedFile(null);
    setTypeId("");
    setPeriod("");
    setNotifyEmployees(true);
    setRequiresSignature(true);
    setPageSize("a10");
    setUploadSuccess(null);
    clearZipPreview();
    clearError();

    // Reset tenant selection (auto-select for single tenant)
    if (userTenants.length === 1) {
      setSelectedTenantId(String(userTenants[0].id));
    } else {
      const primaryTenant = userTenants.find(t => t.is_primary);
      if (primaryTenant) {
        setSelectedTenantId(String(primaryTenant.id));
      }
    }
  };



  const canUpload = selectedFile && typeId && period && selectedTenantId && zipPreview && zipPreview.validPdfs > 0;

  return (
    <div className="space-y-4 sm:space-y-6">
      {/* Header */}
      <div className="flex items-start gap-3 sm:gap-4">
        <Button variant="ghost" size="icon" onClick={handleBack} className="flex-shrink-0 mt-1">
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div className="flex-1 min-w-0">
          <h1 className="text-lg sm:text-xl font-bold">Carga Masiva de Documentos</h1>
          <p className="text-[#64748B] text-sm sm:text-base">
            Sube un archivo ZIP con los PDFs nombrados por número de documento (DNI)
          </p>
        </div>
      </div>

      {/* Success State */}
      {uploadSuccess && (
        <Card className="border-green-500 bg-green-50">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                <CheckCircle className="w-6 h-6 text-white" />
              </div>
              <div className="flex-1">
                <h3 className="text-green-700 font-semibold">¡Archivo enviado correctamente!</h3>
                <p className="text-green-600">
                  El procesamiento comenzará en breve. ID del lote: #{uploadSuccess.batchId}
                </p>
              </div>
              <Button onClick={resetForm} variant="outline">
                Cargar otro archivo
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {!uploadSuccess && (
        <>
          {/* Step 1: Upload Zone */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <span className={`w-6 h-6 ${selectedFile ? 'bg-green-500' : 'bg-[#2563EB]'} text-white rounded-full flex items-center justify-center text-sm`}>
                  {selectedFile ? '✓' : '1'}
                </span>
                Seleccionar Archivo ZIP
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div
                onDragOver={(e) => e.preventDefault()}
                onDrop={handleDrop}
                className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${selectedFile ? "border-green-500 bg-green-50" : "border-[#64748B] hover:border-[#2563EB]"
                  }`}
              >
                {isLoading && !zipPreview ? (
                  <div className="flex flex-col items-center">
                    <Loader2 className="w-12 h-12 text-[#2563EB] animate-spin mb-4" />
                    <p className="text-[#64748B]">Analizando archivo...</p>
                  </div>
                ) : selectedFile ? (
                  <div className="flex flex-col items-center">
                    <FileArchive className="w-12 h-12 text-green-600 mb-4" />
                    <p className="font-medium text-green-700">{selectedFile.name}</p>
                    <p className="text-[#64748B] text-sm">{formatFileSize(selectedFile.size)}</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="mt-2 text-red-600"
                      onClick={resetForm}
                    >
                      Cambiar archivo
                    </Button>
                  </div>
                ) : (
                  <label className="cursor-pointer flex flex-col items-center">
                    <Upload className="w-12 h-12 text-[#64748B] mb-4" />
                    <p className="text-[#64748B] mb-2">
                      Arrastra tu archivo ZIP aquí o{" "}
                      <span className="text-[#2563EB] font-medium">haz clic para seleccionar</span>
                    </p>
                    <p className="text-sm text-[#64748B]">
                      Los archivos PDF dentro deben estar nombrados con el número de documento del empleado
                    </p>
                    <input
                      type="file"
                      accept=".zip"
                      className="hidden"
                      onChange={handleFileSelect}
                    />
                  </label>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Step 2: ZIP Preview Results - Always visible */}
          <Card className={!zipPreview ? 'opacity-60' : ''}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <span className={`w-6 h-6 ${zipPreview ? 'bg-green-500' : 'bg-[#94A3B8]'} text-white rounded-full flex items-center justify-center text-sm`}>
                  {zipPreview ? '✓' : '2'}
                </span>
                Resultado del Análisis
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {zipPreview ? (
                <>
                  {/* Summary */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="p-4 bg-[#F1F5F9] rounded-lg text-center">
                      <p className="text-2xl font-bold text-[#1E40AF]">{zipPreview.totalFiles}</p>
                      <p className="text-sm text-[#64748B]">Total archivos</p>
                    </div>
                    <div className="p-4 bg-green-50 rounded-lg text-center">
                      <p className="text-2xl font-bold text-green-600">{zipPreview.validPdfs}</p>
                      <p className="text-sm text-[#64748B]">PDFs válidos</p>
                    </div>
                    <div className="p-4 bg-orange-50 rounded-lg text-center">
                      <p className="text-2xl font-bold text-orange-600">{zipPreview.invalidNames.length}</p>
                      <p className="text-sm text-[#64748B]">Nombres inválidos</p>
                    </div>
                    <div className="p-4 bg-red-50 rounded-lg text-center">
                      <p className="text-2xl font-bold text-red-600">{zipPreview.invalidFormats.length}</p>
                      <p className="text-sm text-[#64748B]">Formatos inválidos</p>
                    </div>
                  </div>

                  {/* Errors/Warnings */}
                  {(zipPreview.invalidNames.length > 0 || zipPreview.invalidFormats.length > 0) && (
                    <div className="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                      <div className="flex items-center gap-2 mb-2">
                        <AlertTriangle className="w-5 h-5 text-orange-600" />
                        <span className="font-medium text-orange-700">Archivos con problemas</span>
                      </div>
                      <ul className="text-sm text-orange-700 space-y-1 max-h-32 overflow-y-auto">
                        {zipPreview.invalidFormats.map((item, i) => (
                          <li key={`format-${i}`}>• {item.file}: {item.reason}</li>
                        ))}
                        {zipPreview.invalidNames.map((item, i) => (
                          <li key={`name-${i}`}>• {item.file}: {item.reason}</li>
                        ))}
                      </ul>
                    </div>
                  )}

                  {/* Valid files table */}
                  {zipPreview.files.length > 0 && (
                    <div className="border rounded-lg overflow-hidden max-h-64 overflow-y-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Archivo</TableHead>
                            <TableHead>Nro. Documento</TableHead>
                            <TableHead>Tamaño</TableHead>
                            <TableHead>Estado</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {zipPreview.files.slice(0, 20).map((file, i) => (
                            <TableRow key={i}>
                              <TableCell className="font-mono text-sm">{file.name}</TableCell>
                              <TableCell>{file.documentNumber || "-"}</TableCell>
                              <TableCell className="text-[#64748B]">{formatFileSize(file.size)}</TableCell>
                              <TableCell>
                                {file.valid ? (
                                  <Badge className="bg-green-500">Válido</Badge>
                                ) : (
                                  <Badge variant="destructive">{file.reason}</Badge>
                                )}
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                      {zipPreview.files.length > 20 && (
                        <div className="p-2 text-center text-sm text-[#64748B] bg-[#F1F5F9]">
                          Mostrando 20 de {zipPreview.files.length} archivos
                        </div>
                      )}
                    </div>
                  )}
                </>
              ) : (
                /* Empty state for Step 2 */
                <div className="p-8 text-center border-2 border-dashed border-[#E2E8F0] rounded-lg">
                  <FileArchive className="w-12 h-12 text-[#94A3B8] mx-auto mb-4" />
                  <p className="text-[#64748B]">
                    Sube un archivo ZIP para ver el análisis de los documentos aquí
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Step 3: Configuration - Always visible */}
          <Card className={!zipPreview || zipPreview.validPdfs === 0 ? 'opacity-60' : ''}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <span className={`w-6 h-6 ${zipPreview && zipPreview.validPdfs > 0 && typeId && period ? 'bg-green-500' : 'bg-[#94A3B8]'} text-white rounded-full flex items-center justify-center text-sm`}>
                  {zipPreview && zipPreview.validPdfs > 0 && typeId && period ? '✓' : '3'}
                </span>
                Configuración de Carga
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Tenant Selector - Only show if user has multiple tenants */}
              {hasMultipleTenants && (
                <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                  <div className="flex items-center gap-2 mb-3">
                    <Building2 className="h-5 w-5 text-blue-600" />
                    <Label className="text-sm font-semibold text-blue-900 mb-0">
                      Organización de destino *
                    </Label>
                  </div>
                  <Select
                    value={selectedTenantId}
                    onValueChange={setSelectedTenantId}
                    disabled={!zipPreview || zipPreview.validPdfs === 0}
                  >
                    <SelectTrigger className="bg-white">
                      <SelectValue placeholder="Selecciona la organización..." />
                    </SelectTrigger>
                    <SelectContent>
                      {userTenants.map((tenant) => (
                        <SelectItem key={tenant.id} value={String(tenant.id)}>
                          <div className="flex items-center gap-2">
                            <span>{tenant.name}</span>
                            {tenant.is_primary && (
                              <Badge className="bg-blue-600 text-white text-xs">
                                Principal
                              </Badge>
                            )}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-blue-700 mt-2">
                    Los documentos se asociarán a esta organización
                  </p>
                </div>
              )}

              {/* Current tenant indicator for single-tenant users */}
              {!hasMultipleTenants && userTenants.length === 1 && (
                <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-2">
                  <Building2 className="h-4 w-4 text-gray-600" />
                  <span className="text-sm text-gray-700">
                    Organización: <span className="font-semibold">{userTenants[0].name}</span>
                  </span>
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="docType">Tipo de Documento *</Label>
                  <Select value={typeId} onValueChange={setTypeId} disabled={!zipPreview || zipPreview.validPdfs === 0}>
                    <SelectTrigger id="docType">
                      <SelectValue placeholder="Seleccionar tipo..." />
                    </SelectTrigger>
                    <SelectContent>
                      {typesLoading ? (
                        <SelectItem value="loading" disabled>Cargando...</SelectItem>
                      ) : (
                        documentTypes.map((type) => (
                          <SelectItem key={type.id} value={type.id.toString()}>
                            {type.displayName}
                          </SelectItem>
                        ))
                      )}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="period">Período *</Label>
                  <MonthYearPicker
                    value={period}
                    onChange={setPeriod}
                    placeholder="Seleccionar período..."
                    disabled={!zipPreview || zipPreview.validPdfs === 0}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="pageSize">Tamaño de página de la boleta *</Label>
                  <Select
                    value={pageSize}
                    onValueChange={(value) => setPageSize(value as PageSize)}
                    disabled={!zipPreview || zipPreview.validPdfs === 0}
                  >
                    <SelectTrigger id="pageSize">
                      <SelectValue placeholder="Seleccionar tamaño..." />
                    </SelectTrigger>
                    <SelectContent>
                      {PAGE_SIZE_OPTIONS.map((size) => (
                        <SelectItem key={size} value={size}>
                          {pageSizeLabels[size]}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-[#64748B]">
                    Determina dónde se coloca la firma sobre el PDF. Si no estás seguro, deja "A10".
                  </p>
                </div>
              </div>

              <div className="space-y-4 p-4 bg-[#F1F5F9] rounded-lg">
                <div className="flex items-center gap-3">
                  <Checkbox
                    id="notify"
                    checked={notifyEmployees}
                    onCheckedChange={(checked: boolean | "indeterminate") => setNotifyEmployees(checked === true)}
                    disabled={!zipPreview || zipPreview.validPdfs === 0}
                  />
                  <label htmlFor="notify" className="cursor-pointer">
                    <p className="font-medium">Notificar a empleados</p>
                    <p className="text-sm text-[#64748B]">
                      Los empleados recibirán un email cuando sus documentos estén disponibles
                    </p>
                  </label>
                </div>

                <div className="flex items-center gap-3">
                  <Checkbox
                    id="signature"
                    checked={requiresSignature}
                    onCheckedChange={(checked: boolean | "indeterminate") => setRequiresSignature(checked === true)}
                    disabled={!zipPreview || zipPreview.validPdfs === 0}
                  />
                  <label htmlFor="signature" className="cursor-pointer">
                    <p className="font-medium">Requiere firma digital</p>
                    <p className="text-sm text-[#64748B]">
                      Los empleados deberán firmar el documento con verificación 2FA
                    </p>
                  </label>
                </div>
              </div>

              <div className="flex justify-end gap-4">
                <Button variant="outline" onClick={resetForm}>
                  Cancelar
                </Button>
                <Button
                  onClick={handleUpload}
                  disabled={!canUpload || isLoading}
                  className="bg-[#2563EB] hover:bg-[#1E40AF]"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Subiendo...
                    </>
                  ) : (
                    <>
                      <Upload className="w-4 h-4 mr-2" />
                      {zipPreview && zipPreview.validPdfs > 0
                        ? `Subir ${zipPreview.validPdfs} documentos`
                        : 'Subir documentos'}
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}

export default DocumentUploadView;
