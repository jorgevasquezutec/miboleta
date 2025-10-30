import { useState, useMemo } from "react";
import { FileText, Download, CheckCircle, Clock, Calendar, Bell, Search } from "lucide-react";
import { DocumentCard } from "../DocumentCard";
import { Card, CardContent, CardHeader, CardTitle } from "../ui/card";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Badge } from "../ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select";
import { useTablePagination } from "../../hooks/useTablePagination";

interface EmployeeDashboardViewProps {
  onViewDocument: (id: string) => void;
}

type DocumentStatus = "signed" | "pending" | "expired";
type DocumentCategory = "payslip" | "contract" | "certificate";

// Generar más documentos mock para demostrar la paginación
const generateMockDocuments = () => {
  const baseDocuments = [
    { title: "Boleta de Pago", category: "payslip" as DocumentCategory },
    { title: "Contrato de Trabajo", category: "contract" as DocumentCategory },
    { title: "Certificado Laboral", category: "certificate" as DocumentCategory },
    { title: "Anexo de Contrato", category: "contract" as DocumentCategory },
    { title: "Certificado de Capacitación", category: "certificate" as DocumentCategory },
  ];

  const statuses: DocumentStatus[] = ["signed", "pending", "expired"];
  const months = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

  const documents = [];
  for (let i = 0; i < 45; i++) {
    const baseDoc = baseDocuments[i % baseDocuments.length];
    const status = statuses[i % statuses.length];
    const month = months[i % months.length];

    documents.push({
      id: `doc-${i + 1}`,
      title: `${baseDoc.title} - ${month} 2025`,
      category: baseDoc.category,
      status,
      date: `${(i % 28) + 1} ${month.slice(0, 3)} 2025`,
    });
  }

  return documents;
};

const mockDocuments = generateMockDocuments();

export function EmployeeDashboardView({ onViewDocument }: EmployeeDashboardViewProps) {
  // Estado para búsqueda y filtros
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState<DocumentStatus | "all">("all");
  const [categoryFilter, setCategoryFilter] = useState<DocumentCategory | "all">("all");

  // Filtrar documentos basados en búsqueda y filtros
  const filteredDocuments = useMemo(() => {
    return mockDocuments.filter((doc) => {
      // Filtro de búsqueda
      const matchesSearch = doc.title.toLowerCase().includes(searchTerm.toLowerCase());

      // Filtro de estado
      const matchesStatus = statusFilter === "all" || doc.status === statusFilter;

      // Filtro de categoría
      const matchesCategory = categoryFilter === "all" || doc.category === categoryFilter;

      return matchesSearch && matchesStatus && matchesCategory;
    });
  }, [searchTerm, statusFilter, categoryFilter]);

  // Hook de paginación
  const {
    paginatedData,
    currentPage,
    pageSize,
    totalPages,
    totalItems,
    setCurrentPage,
    setPageSize,
  } = useTablePagination({
    data: filteredDocuments,
    initialPageSize: 6,
    mode: "client",
  });

  // Estadísticas basadas en todos los documentos (no filtrados)
  const pendingDocs = mockDocuments.filter((d) => d.status === "pending");
  const signedDocs = mockDocuments.filter((d) => d.status === "signed");

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1>Mis Documentos</h1>
        <p className="text-[#64748B]">
          Gestiona y visualiza todos tus documentos laborales
        </p>
      </div>

      {/* Alerts for Pending Documents */}
      {pendingDocs.length > 0 && (
        <Card className="border-[#F59E0B] bg-orange-50">
          <CardContent className="p-6">
            <div className="flex items-start gap-4">
              <div className="w-10 h-10 bg-[#F59E0B] rounded-full flex items-center justify-center flex-shrink-0">
                <Bell className="w-5 h-5 text-white" />
              </div>
              <div className="flex-1">
                <h3 className="text-[#1E40AF] mb-2">Documentos Pendientes de Firma</h3>
                <p className="text-[#64748B] mb-3">
                  Tienes {pendingDocs.length} documento(s) pendiente(s) que requieren tu firma.
                </p>
                <Button
                  size="sm"
                  className="bg-[#F59E0B] hover:bg-[#D97706] text-white"
                >
                  Ver Documentos Pendientes
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Stats Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Total Documentos</p>
                <h2>{mockDocuments.length}</h2>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <FileText className="w-6 h-6 text-[#2563EB]" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Firmados</p>
                <h2>{signedDocs.length}</h2>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <CheckCircle className="w-6 h-6 text-[#10B981]" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Pendientes</p>
                <h2>{pendingDocs.length}</h2>
              </div>
              <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <Clock className="w-6 h-6 text-[#F59E0B]" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters and Search */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" />
              <Input
                placeholder="Buscar documentos..."
                className="h-11 pl-10"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <Select value={statusFilter} onValueChange={(value: string) => setStatusFilter(value as DocumentStatus | "all")}>
              <SelectTrigger className="w-full md:w-48 h-11">
                <SelectValue placeholder="Estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los estados</SelectItem>
                <SelectItem value="pending">Pendientes</SelectItem>
                <SelectItem value="signed">Firmados</SelectItem>
                <SelectItem value="expired">Vencidos</SelectItem>
              </SelectContent>
            </Select>
            <Select value={categoryFilter} onValueChange={(value: string) => setCategoryFilter(value as DocumentCategory | "all")}>
              <SelectTrigger className="w-full md:w-48 h-11">
                <SelectValue placeholder="Categoría" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas las categorías</SelectItem>
                <SelectItem value="payslip">Boletas de Pago</SelectItem>
                <SelectItem value="contract">Contratos</SelectItem>
                <SelectItem value="certificate">Certificados</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Documents Grid */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Todos los Documentos</CardTitle>
              <p className="text-sm text-[#64748B] mt-1">
                {totalItems === 0 ? (
                  "No se encontraron documentos"
                ) : (
                  `Mostrando ${paginatedData.length} de ${totalItems} documento${totalItems !== 1 ? "s" : ""}`
                )}
              </p>
            </div>
            <Button variant="outline" className="gap-2">
              <Download className="w-4 h-4" />
              Descargar Todos
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {totalItems === 0 ? (
            <div className="py-12 text-center">
              <FileText className="w-16 h-16 text-[#64748B] mx-auto mb-4" />
              <p className="text-[#64748B] mb-2">No se encontraron documentos</p>
              <p className="text-sm text-[#64748B]">
                Intenta ajustar los filtros de búsqueda
              </p>
            </div>
          ) : (
            <>
              <div className="grid grid-cols-1 gap-4">
                {paginatedData.map((doc) => (
                  <DocumentCard
                    key={doc.id}
                    {...doc}
                    onView={() => onViewDocument(doc.id)}
                    onDownload={() => console.log("Download", doc.id)}
                    onSign={
                      doc.status === "pending"
                        ? () => onViewDocument(doc.id)
                        : undefined
                    }
                  />
                ))}
              </div>

              {/* Paginación */}
              {totalPages > 1 && (
                <div className="mt-6 pt-6 border-t">
                  <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    {/* Selector de tamaño de página */}
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-[#64748B]">Documentos por página:</span>
                      <Select
                        value={pageSize.toString()}
                        onValueChange={(value: string) => setPageSize(parseInt(value))}
                      >
                        <SelectTrigger className="h-9 w-[70px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="6">6</SelectItem>
                          <SelectItem value="12">12</SelectItem>
                          <SelectItem value="24">24</SelectItem>
                          <SelectItem value="48">48</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    {/* Controles de paginación */}
                    <div className="flex items-center gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(1)}
                        disabled={currentPage === 1}
                      >
                        Primera
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(currentPage - 1)}
                        disabled={currentPage === 1}
                      >
                        Anterior
                      </Button>
                      <span className="text-sm text-[#64748B] px-2">
                        Página {currentPage} de {totalPages}
                      </span>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(currentPage + 1)}
                        disabled={currentPage === totalPages}
                      >
                        Siguiente
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(totalPages)}
                        disabled={currentPage === totalPages}
                      >
                        Última
                      </Button>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      {/* Recent Activity Timeline */}
      <Card>
        <CardHeader>
          <CardTitle>Actividad Reciente</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex gap-4">
              <div className="flex flex-col items-center">
                <div className="w-8 h-8 bg-[#10B981] rounded-full flex items-center justify-center">
                  <CheckCircle className="w-4 h-4 text-white" />
                </div>
                <div className="w-0.5 h-12 bg-[#F1F5F9]"></div>
              </div>
              <div className="flex-1 pb-4">
                <p>Firmaste el documento "Boleta de Pago - Junio 2025"</p>
                <p className="text-[#64748B]">Hace 2 días</p>
              </div>
            </div>

            <div className="flex gap-4">
              <div className="flex flex-col items-center">
                <div className="w-8 h-8 bg-[#3B82F6] rounded-full flex items-center justify-center">
                  <Download className="w-4 h-4 text-white" />
                </div>
                <div className="w-0.5 h-12 bg-[#F1F5F9]"></div>
              </div>
              <div className="flex-1 pb-4">
                <p>Descargaste "Certificado Laboral"</p>
                <p className="text-[#64748B]">Hace 5 días</p>
              </div>
            </div>

            <div className="flex gap-4">
              <div className="flex flex-col items-center">
                <div className="w-8 h-8 bg-[#10B981] rounded-full flex items-center justify-center">
                  <CheckCircle className="w-4 h-4 text-white" />
                </div>
              </div>
              <div className="flex-1">
                <p>Firmaste "Boleta de Pago - Mayo 2025"</p>
                <p className="text-[#64748B]">Hace 1 mes</p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
