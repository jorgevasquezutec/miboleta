import { FileText, Download, CheckCircle, Clock, Calendar, Bell } from "lucide-react";
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

interface EmployeeDashboardViewProps {
  onViewDocument: (id: string) => void;
}

const mockDocuments = [
  {
    id: "1",
    title: "Boleta de Pago - Junio 2025",
    category: "payslip" as const,
    status: "signed" as const,
    date: "15 Jun 2025",
  },
  {
    id: "2",
    title: "Contrato de Trabajo - 2025",
    category: "contract" as const,
    status: "pending" as const,
    date: "10 Jun 2025",
  },
  {
    id: "3",
    title: "Certificado Laboral",
    category: "certificate" as const,
    status: "signed" as const,
    date: "05 Jun 2025",
  },
  {
    id: "4",
    title: "Boleta de Pago - Mayo 2025",
    category: "payslip" as const,
    status: "signed" as const,
    date: "15 May 2025",
  },
  {
    id: "5",
    title: "Anexo de Contrato",
    category: "contract" as const,
    status: "pending" as const,
    date: "08 Jun 2025",
  },
  {
    id: "6",
    title: "Certificado de Capacitación",
    category: "certificate" as const,
    status: "expired" as const,
    date: "01 Jun 2025",
  },
];

export function EmployeeDashboardView({ onViewDocument }: EmployeeDashboardViewProps) {
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
            <div className="flex-1">
              <Input
                placeholder="Buscar documentos..."
                className="h-11"
              />
            </div>
            <Select defaultValue="all">
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
            <Select defaultValue="all">
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
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2>Todos los Documentos</h2>
          <Button variant="outline" className="gap-2">
            <Download className="w-4 h-4" />
            Descargar Todos
          </Button>
        </div>
        <div className="grid grid-cols-1 gap-4">
          {mockDocuments.map((doc) => (
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
      </div>

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
