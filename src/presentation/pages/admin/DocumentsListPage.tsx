import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { FileText, Search, Filter, Eye, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { DateRange } from "react-day-picker";
import { format } from "date-fns";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Input } from "@/presentation/components/ui/input";
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
import { DateRangePicker } from "@/presentation/components/ui/date-range-picker";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { useDocumentsStore } from "@/presentation/stores";
import { Document } from "@/core/domain/entities/Document";
import { useAuthStore } from "@/presentation/stores";
import { getDocumentStatusBadgeInline } from "@/presentation/utils";

export function DocumentsListPage() {
    const navigate = useNavigate();
    const { user } = useAuthStore();
    const {
        documents,
        fetchDocuments,
        fetchDocumentTypes,
        deleteDocument,
        isLoading,
        total,
        totalPages,
    } = useDocumentsStore();

    const [documentTypes, setDocumentTypes] = useState<any[]>([]);
    const [searchTerm, setSearchTerm] = useState("");
    const [statusFilter, setStatusFilter] = useState<string>("all");
    const [typeFilter, setTypeFilter] = useState<string>("all");
    const [dateRange, setDateRange] = useState<DateRange | undefined>(undefined);
    const [currentPage, setCurrentPage] = useState(1);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<number | null>(null);

    useEffect(() => {
        const fetchTypes = async () => {
            await fetchDocumentTypes();
            // Document types are now in the store
        };
        fetchTypes();
    }, [fetchDocumentTypes]);

    useEffect(() => {
        fetchDocuments({
            page: currentPage,
            perPage: 20,
            search: searchTerm || undefined,
            status: statusFilter !== "all" ? (statusFilter as Document['status']) : undefined,
            docTypeId: typeFilter !== "all" ? parseInt(typeFilter) : undefined,
            dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
            dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
        });
    }, [currentPage, statusFilter, typeFilter, dateRange, fetchDocuments]);

    const handleSearch = () => {
        setCurrentPage(1); // Reset to page 1 when searching
        // Trigger fetch by updating currentPage or directly calling
        fetchDocuments({
            page: 1,
            perPage: 20,
            search: searchTerm || undefined,
            status: statusFilter !== "all" ? (statusFilter as Document['status']) : undefined,
            docTypeId: typeFilter !== "all" ? parseInt(typeFilter) : undefined,
            dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
            dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
        });
    };

    const handleDeleteClick = (id: number) => {
        setDocumentToDelete(id);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = async () => {
        if (documentToDelete) {
            try {
                await deleteDocument(documentToDelete);
                // Refetch current page
                fetchDocuments({
                    page: currentPage,
                    perPage: 20,
                    search: searchTerm || undefined,
                    status: statusFilter !== "all" ? (statusFilter as Document['status']) : undefined,
                    docTypeId: typeFilter !== "all" ? parseInt(typeFilter) : undefined,
                    dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
                    dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
                });
            } catch (error) {
                console.error("Error deleting document:", error);
            } finally {
                setDeleteDialogOpen(false);
                setDocumentToDelete(null);
            }
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <h1 className="flex items-center gap-2">
                    <FileText className="w-6 h-6 text-[#2563EB]" />
                    Buscador de Documentos
                </h1>
                <p className="text-[#64748B]">
                    Busca y gestiona todos los documentos del sistema
                </p>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Search */}
                        <div className="md:col-span-2">
                            <label className="text-sm font-medium mb-2 block">Usuario</label>
                            <div className="flex gap-2">
                                <Input
                                    placeholder="Busca por Nombre, Apellido, Documento o identidad"
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                                />
                                <Button onClick={handleSearch} disabled={isLoading}>
                                    <Search className="w-4 h-4" />
                                </Button>
                            </div>
                        </div>

                        {/* Document Type Filter */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Tipo de documento</label>
                            <Select value={typeFilter} onValueChange={setTypeFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {documentTypes.map((type) => (
                                        <SelectItem key={type.id} value={type.id.toString()}>
                                            {type.displayName}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Status Filter */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Estado</label>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="pending">Pendiente Firma</SelectItem>
                                    <SelectItem value="signed">Firmado</SelectItem>
                                    <SelectItem value="active">Disponible</SelectItem>
                                    <SelectItem value="orphan">Huérfano</SelectItem>
                                    <SelectItem value="expired">Expirado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Date Range Picker - 2 columns */}
                        <div className="md:col-span-2">
                            <label className="text-sm font-medium mb-2 block">Rango de fechas</label>
                            <DateRangePicker
                                dateRange={dateRange}
                                onDateRangeChange={setDateRange}
                            />
                        </div>
                    </div>

                    <div className="flex justify-end mt-4">
                        <Button
                            variant="outline"
                            onClick={() => {
                                setSearchTerm("");
                                setStatusFilter("all");
                                setTypeFilter("all");
                                setDateRange(undefined);
                            }}
                        >
                            <Filter className="w-4 h-4 mr-2" />
                            Limpiar filtros
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Results */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        Resultados {total > 0 && `(${total} documentos)`}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading && documents.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            Cargando documentos...
                        </div>
                    ) : documents.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            <FileText className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <p>No se encontraron documentos</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Apellido y Nombre / DNI</TableHead>
                                        <TableHead>Tipo de documento / Período</TableHead>
                                        <TableHead>Fecha de subida</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.map((doc) => (
                                        <TableRow key={doc.id}>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">
                                                        {doc.user?.name} {doc.user?.lastName}
                                                    </div>
                                                    <div className="text-sm text-[#64748B]">
                                                        {doc.user?.documentText || doc.employeeDocumentNumber}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">
                                                        {doc.documentType?.displayName || "-"}
                                                    </div>
                                                    <div className="text-sm text-[#64748B]">{doc.period}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-[#64748B]">
                                                {new Date(doc.createdAt).toLocaleString('es-PE', {
                                                    day: '2-digit',
                                                    month: '2-digit',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                })}
                                            </TableCell>
                                            <TableCell>{getDocumentStatusBadgeInline(doc.status)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => navigate(`/viewer?id=${doc.id}`)}
                                                        title="Ver documento"
                                                    >
                                                        <Eye className="w-4 h-4 text-[#2563EB]" />
                                                    </Button>
                                                    {user?.role === "admin" && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleDeleteClick(doc.id)}
                                                            title="Eliminar documento"
                                                        >
                                                            <Trash2 className="w-4 h-4 text-red-600" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <div className="text-sm text-[#64748B]">
                                    Mostrando página {currentPage} de {totalPages || 1} ({total || 0} documentos total)
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage <= 1}
                                        onClick={() => setCurrentPage(currentPage - 1)}
                                    >
                                        <ChevronLeft className="w-4 h-4" />
                                    </Button>
                                    <span className="px-3 py-1 text-sm">{currentPage}</span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage >= (totalPages || 1)}
                                        onClick={() => setCurrentPage(currentPage + 1)}
                                    >
                                        <ChevronRight className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                onConfirm={handleDeleteConfirm}
                title="Eliminar Documento"
                description="¿Estás seguro de que deseas eliminar este documento? Esta acción no se puede deshacer."
                confirmText="Eliminar"
                cancelText="Cancelar"
            />
        </div>
    );
}

export default DocumentsListPage;
