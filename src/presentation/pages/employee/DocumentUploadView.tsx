import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { ArrowLeft, Upload, FileArchive, CheckCircle, AlertTriangle, Loader2 } from "lucide-react";
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
import { useDocumentsStore } from "@/presentation/stores";
import { formatFileSize } from "@/presentation/utils";
import { toast } from "sonner";

interface DocumentUploadViewProps {
  onBack?: () => void;
}

export function DocumentUploadView({ onBack }: DocumentUploadViewProps) {
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

  // Form state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [typeId, setTypeId] = useState<string>("");
  const [period, setPeriod] = useState<string>("");
  const [notifyEmployees, setNotifyEmployees] = useState(false);
  const [requiresSignature, setRequiresSignature] = useState(false);
  const [uploadSuccess, setUploadSuccess] = useState<{ batchId: number } | null>(null);

  // Reset form and load document types on mount
  useEffect(() => {
    // Reset all form state when component mounts
    setSelectedFile(null);
    setTypeId("");
    setPeriod("");
    setNotifyEmployees(false);
    setRequiresSignature(false);
    setUploadSuccess(null);
    clearZipPreview();
    clearError();

    // Load document types
    fetchDocumentTypes();
  }, [fetchDocumentTypes, clearZipPreview, clearError]);



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

    try {
      const result = await uploadBatch({
        file: selectedFile,
        typeId: parseInt(typeId),
        period,
        notifyEmployees,
        requiresSignature,
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
    setNotifyEmployees(false);
    setRequiresSignature(false);
    setUploadSuccess(null);
    clearZipPreview();
    clearError();
  };



  const canUpload = selectedFile && typeId && period && zipPreview && zipPreview.validPdfs > 0;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" onClick={handleBack}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div className="flex-1">
          <h1>Carga Masiva de Documentos</h1>
          <p className="text-[#64748B]">
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
