import { useState, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { FileText, Download, CheckCircle, Clock, Calendar, Bell, Search, Loader2, AlertCircle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/presentation/components/ui/select";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { useDocumentsStore } from "@/presentation/stores";
import { Document } from "@/core/domain/entities/Document";
import { getDocumentStatusBadge, formatDate } from "@/presentation/utils";

interface EmployeeDashboardViewProps {
  onViewDocument?: (id: number) => void;
}

export function EmployeeDashboardView({ onViewDocument }: EmployeeDashboardViewProps) {
  useDocumentTitle('Mis Documentos');
  const navigate = useNavigate();
  const {
    documents,
    documentTypes,
    fetchDocuments,
    fetchDocumentTypes,
    isLoading,
    error,
    total,
    totalPages,
  } = useDocumentsStore();

  // URL-synced filters
  const { filters, setFilters } = useUrlFilters({
    defaultValues: {
      search: '',
      status: 'all',
      type: '',
      page: 1,
      per_page: 10,
    }
  });

  // Local state for search input (debounce)
  const [searchInput, setSearchInput] = useState(filters.search);
  const debounceTimer = useRef<NodeJS.Timeout | null>(null);

  // Debounce search
  useEffect(() => {
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }
    debounceTimer.current = setTimeout(() => {
      if (searchInput !== filters.search) {
        setFilters({ search: searchInput, page: 1 });
      }
    }, 500);
    return () => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
    };
  }, [searchInput, filters.search, setFilters]);

  // Sync search input with URL on mount
  useEffect(() => {
    setSearchInput(filters.search);
  }, []);

  useEffect(() => {
    fetchDocumentTypes();
  }, [fetchDocumentTypes]);

  // Fetch documents when filters change
  // ✅ MIGRATED: Now reacts automatically to tenant filter changes
  useTenantAwareEffect(() => {
    fetchDocuments({
      page: filters.page,
      perPage: filters.per_page,
      search: filters.search || undefined,
      status: filters.status !== "all" ? (filters.status as Document['status']) : undefined,
      docTypeId: filters.type ? parseInt(filters.type) : undefined,
      myDocuments: true, // Always show only my documents
    });
  }, [filters.page, filters.per_page, filters.search, filters.status, filters.type, fetchDocuments]);

  const handleSearch = () => {
    setFilters({ page: 1 });
  };

  const handleStatusChange = (value: string) => {
    setFilters({ status: value, page: 1 });
  };

  const handleTypeChange = (value: string) => {
    setFilters({ type: value === 'all' ? '' : value, page: 1 });
  };

  const handleViewDocument = (id: number) => {
    if (onViewDocument) {
      onViewDocument(id);
    } else {
      navigate(`/viewer?id=${id}`);
    }
  };

  const handleDownload = (id: number) => {
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
    window.open(`${baseUrl}/documents/${id}/download`, '_blank');
  };

  const handlePageChange = (page: number) => {
    setFilters({ page });
  };

  const handlePerPageChange = (newPerPage: number) => {
    setFilters({ per_page: newPerPage, page: 1 });
  };

  // Estadísticas
  const pendingCount = documents.filter(d => d.status === "pending").length;
  const signedCount = documents.filter(d => d.status === "signed").length;

  if (error) {
    return (
      <div className="flex items-center justify-center h-96">
        <Card className="max-w-md">
          <CardContent className="p-6 text-center">
            <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h3 className="mb-2">Error al cargar documentos</h3>
            <p className="text-[#64748B]">{error}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-4 sm:space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl sm:text-2xl font-bold">Mis Documentos</h1>
        <p className="text-[#64748B] text-sm sm:text-base">
          Gestiona y visualiza todos tus documentos laborales
        </p>
      </div>

      {/* Alerts for Pending Documents */}
      {pendingCount > 0 && (
        <Card className="border-[#F59E0B] bg-orange-50">
          <CardContent className="p-6">
            <div className="flex items-start gap-4">
              <div className="w-10 h-10 bg-[#F59E0B] rounded-full flex items-center justify-center flex-shrink-0">
                <Bell className="w-5 h-5 text-white" />
              </div>
              <div className="flex-1">
                <h3 className="text-[#1E40AF] mb-2 font-semibold">Documentos Pendientes de Firma</h3>
                <p className="text-[#64748B] mb-3">
                  Tienes {pendingCount} documento(s) pendiente(s) que requieren tu firma.
                </p>
                <Button
                  size="sm"
                  className="bg-[#F59E0B] hover:bg-[#D97706] text-white"
                  onClick={() => handleStatusChange("pending")}
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
                <h2 className="text-2xl font-bold">{total}</h2>
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
                <h2 className="text-2xl font-bold">{signedCount}</h2>
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
                <h2 className="text-2xl font-bold">{pendingCount}</h2>
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
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
              />
            </div>
            <Select value={filters.status} onValueChange={handleStatusChange}>
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
            <Select value={filters.type || 'all'} onValueChange={handleTypeChange}>
              <SelectTrigger className="w-full md:w-40 h-11">
                <SelectValue placeholder="Tipo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los tipos</SelectItem>
                {documentTypes.map((type) => (
                  <SelectItem key={type.id} value={type.id.toString()}>
                    {type.displayName}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button onClick={handleSearch} size="sm" className="h-11 w-full md:w-auto shrink-0">
              <Search className="w-4 h-4" />
              <span className="ml-2 md:hidden">Buscar</span>
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Documents List */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Mis Documentos</CardTitle>
              <p className="text-sm text-[#64748B] mt-1">
                {documents.length === 0
                  ? "No se encontraron documentos"
                  : `Mostrando ${documents.length} de ${total} documentos`}
              </p>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="py-12 text-center">
              <Loader2 className="w-8 h-8 animate-spin text-[#2563EB] mx-auto mb-4" />
              <p className="text-[#64748B]">Cargando documentos...</p>
            </div>
          ) : documents.length === 0 ? (
            <div className="py-12 text-center">
              <FileText className="w-16 h-16 text-[#64748B] mx-auto mb-4" />
              <p className="text-[#64748B] mb-2">No se encontraron documentos</p>
              <p className="text-sm text-[#64748B]">
                Intenta ajustar los filtros de búsqueda
              </p>
            </div>
          ) : (
            <>
              <div className="space-y-3 sm:space-y-4">
                {documents.map((doc) => (
                  <Card key={doc.id} className="hover:shadow-md transition-shadow duration-200">
                    <CardContent className="p-3 sm:p-4">
                      <div className="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                        {/* Icon */}
                        <div className="hidden sm:flex w-12 h-12 bg-[#F1F5F9] rounded-lg items-center justify-center flex-shrink-0">
                          <FileText className="w-6 h-6 text-[#2563EB]" />
                        </div>

                        {/* Content */}
                        <div className="flex-1 min-w-0">
                          <div className="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
                            <h3 className="font-semibold text-sm sm:text-base truncate">
                              {doc.documentType?.displayName || "Documento"}
                            </h3>
                            {getDocumentStatusBadge(doc.status)}
                          </div>
                          <div className="flex flex-wrap items-center gap-2 sm:gap-4 text-xs sm:text-sm text-[#64748B]">
                            <span className="flex items-center gap-1">
                              <Calendar className="w-3 h-3 sm:w-4 sm:h-4" />
                              {doc.period}
                            </span>
                            <span>{formatDate(doc.createdAt)}</span>
                          </div>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-2 flex-shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleViewDocument(doc.id)}
                            className="flex-1 sm:flex-initial"
                          >
                            Ver
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => handleDownload(doc.id)}
                          >
                            <Download className="w-4 h-4" />
                          </Button>
                          {doc.status === "pending" && (doc.requiresSignature || doc.documentType?.requiresSignature) && (
                            <Button
                              size="sm"
                              className="bg-[#2563EB] hover:bg-[#1E40AF] flex-1 sm:flex-initial"
                              onClick={() => handleViewDocument(doc.id)}
                            >
                              Firmar
                            </Button>
                          )}
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              {/* Pagination */}
              <PaginationControls
                currentPage={filters.page}
                totalPages={totalPages || 1}
                total={total}
                perPage={filters.per_page}
                onPageChange={handlePageChange}
                onPerPageChange={handlePerPageChange}
                disabled={isLoading}
                className="mt-6 pt-6 border-t"
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

export default EmployeeDashboardView;
