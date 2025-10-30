import { useState } from "react";
import { ArrowLeft, Users, FolderOpen, Save } from "lucide-react";
import { DocumentUploadZone } from "../DocumentUploadZone";
import { Button } from "../ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "../ui/card";
import { Label } from "../ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../ui/table";
import { Input } from "../ui/input";
import { Badge } from "../ui/badge";

interface DocumentUploadViewProps {
  onBack: () => void;
}

const mockEmployees = [
  { id: "1", name: "María García", email: "maria.garcia@empresa.com" },
  { id: "2", name: "Carlos Ruiz", email: "carlos.ruiz@empresa.com" },
  { id: "3", name: "Ana Martínez", email: "ana.martinez@empresa.com" },
  { id: "4", name: "Luis Torres", email: "luis.torres@empresa.com" },
  { id: "5", name: "Sofia López", email: "sofia.lopez@empresa.com" },
];

export function DocumentUploadView({ onBack }: DocumentUploadViewProps) {
  const [uploadedFiles, setUploadedFiles] = useState<any[]>([]);
  const [assignments, setAssignments] = useState<{ [key: string]: any }>({});

  const handleFilesSelected = (files: File[]) => {
    const newFiles = files.map((file, index) => ({
      id: `file-${Date.now()}-${index}`,
      name: file.name,
      size: formatFileSize(file.size),
      category: "",
      employee: "",
    }));
    setUploadedFiles((prev) => [...prev, ...newFiles]);
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  };

  const updateAssignment = (fileId: string, field: string, value: string) => {
    setAssignments((prev) => ({
      ...prev,
      [fileId]: {
        ...prev[fileId],
        [field]: value,
      },
    }));
  };

  const handleSaveAssignments = () => {
    console.log("Saving assignments:", assignments);
    // Here you would send the data to your backend
    alert("Documentos asignados exitosamente!");
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" onClick={onBack}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div className="flex-1">
          <h1>Cargar Documentos</h1>
          <p className="text-[#64748B]">
            Sube documentos y asígnalos a empleados
          </p>
        </div>
        {uploadedFiles.length > 0 && (
          <Button
            className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
            onClick={handleSaveAssignments}
          >
            <Save className="w-4 h-4" />
            Guardar y Asignar ({uploadedFiles.length})
          </Button>
        )}
      </div>

      {/* Upload Zone */}
      <Card>
        <CardHeader>
          <CardTitle>Paso 1: Cargar Archivos</CardTitle>
        </CardHeader>
        <CardContent>
          <DocumentUploadZone onFilesSelected={handleFilesSelected} />
        </CardContent>
      </Card>

      {/* Assignment Table */}
      {uploadedFiles.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Paso 2: Asignar Documentos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-center gap-4 p-4 bg-blue-50 rounded-lg">
                <Users className="w-5 h-5 text-[#2563EB]" />
                <div>
                  <p className="text-[#1E40AF]">
                    Asigna categorías y empleados a cada documento
                  </p>
                  <p className="text-[#64748B]">
                    Los empleados recibirán una notificación por email
                  </p>
                </div>
              </div>

              <div className="border rounded-lg overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Documento</TableHead>
                      <TableHead>Tamaño</TableHead>
                      <TableHead>Categoría</TableHead>
                      <TableHead>Asignar a</TableHead>
                      <TableHead>Estado</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {uploadedFiles.map((file) => (
                      <TableRow key={file.id}>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <FolderOpen className="w-4 h-4 text-[#2563EB]" />
                            <span className="truncate max-w-xs">{file.name}</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-[#64748B]">
                          {file.size}
                        </TableCell>
                        <TableCell>
                          <Select
                            value={assignments[file.id]?.category || ""}
                            onValueChange={(value) =>
                              updateAssignment(file.id, "category", value)
                            }
                          >
                            <SelectTrigger className="w-48">
                              <SelectValue placeholder="Seleccionar..." />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="payslip">
                                Boleta de Pago
                              </SelectItem>
                              <SelectItem value="contract">Contrato</SelectItem>
                              <SelectItem value="certificate">
                                Certificado
                              </SelectItem>
                              <SelectItem value="other">Otro</SelectItem>
                            </SelectContent>
                          </Select>
                        </TableCell>
                        <TableCell>
                          <Select
                            value={assignments[file.id]?.employee || ""}
                            onValueChange={(value) =>
                              updateAssignment(file.id, "employee", value)
                            }
                          >
                            <SelectTrigger className="w-56">
                              <SelectValue placeholder="Seleccionar empleado..." />
                            </SelectTrigger>
                            <SelectContent>
                              {mockEmployees.map((emp) => (
                                <SelectItem key={emp.id} value={emp.id}>
                                  {emp.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </TableCell>
                        <TableCell>
                          {assignments[file.id]?.category &&
                          assignments[file.id]?.employee ? (
                            <Badge className="bg-[#10B981] text-white">
                              Listo
                            </Badge>
                          ) : (
                            <Badge variant="secondary">Pendiente</Badge>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {/* Bulk Actions */}
              <Card className="bg-[#F1F5F9]">
                <CardContent className="p-4">
                  <div className="flex flex-wrap items-end gap-4">
                    <div className="flex-1 min-w-[200px]">
                      <Label>Asignación Masiva - Categoría</Label>
                      <Select>
                        <SelectTrigger className="mt-2 bg-white">
                          <SelectValue placeholder="Aplicar a todos..." />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="payslip">Boleta de Pago</SelectItem>
                          <SelectItem value="contract">Contrato</SelectItem>
                          <SelectItem value="certificate">
                            Certificado
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex-1 min-w-[200px]">
                      <Label>Asignación Masiva - Empleado</Label>
                      <Select>
                        <SelectTrigger className="mt-2 bg-white">
                          <SelectValue placeholder="Aplicar a todos..." />
                        </SelectTrigger>
                        <SelectContent>
                          {mockEmployees.map((emp) => (
                            <SelectItem key={emp.id} value={emp.id}>
                              {emp.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <Button variant="outline">Aplicar</Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
