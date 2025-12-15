import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { FileText, Search, Filter, Eye, Trash2, Download, Loader2 } from "lucide-react";
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
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { usePagination, useTableFilters } from "@/presentation/hooks";
import { useDocumentsStore } from "@/presentation/stores";
import { Document } from "@/core/domain/entities/Document";
import { useAuthStore } from "@/presentation/stores";
import { getDocumentStatusBadgeInline } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";

interface DocumentFilters {
    search?: string;
    status?: Document['status'] | 'all';
    docTypeId?: number;
}

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
    const [dateRange, setDateRange] = useState<{ from: Date; to: Date | undefined } | undefined>(undefined);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<number | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    // Use pagination hook
    const pagination = usePagination({
        initialPage: 1,
        initialPerPage: 5,
    });

    // Use filters hook
    const { filters, setFilter, resetFilters: resetFiltersState } = useTableFilters<DocumentFilters>({
        initialFilters: {
            search: '',
            status: 'all',
            docTypeId: undefined,
        },
    });

    // Sync pagination state with store
    useEffect(() => {
        pagination.setTotal(total);
        pagination.setTotalPages(totalPages);
    }, [total, totalPages]);

    useEffect(() => {
        const fetchTypes = async () => {
            await fetchDocumentTypes();
        };
        fetchTypes();
    }, [fetchDocumentTypes]);

    // Fetch documents when pagination or filters change
    useEffect(() => {
        fetchDocuments({
            page: pagination.currentPage,
            perPage: pagination.perPage,
            search: filters.search || undefined,
            status: filters.status !== "all" ? (filters.status as Document['status']) : undefined,
            docTypeId: filters.docTypeId || undefined,
            dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
            dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
        });
    }, [pagination.currentPage, pagination.perPage, filters, dateRange, fetchDocuments]);

    const handleSearch = () => {
        pagination.setPage(1); // Reset to page 1 when searching
    };

    const handleResetFilters = () => {
        resetFiltersState();
        setDateRange(undefined);
        pagination.setPage(1);
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
                    page: pagination.currentPage,
                    perPage: pagination.perPage,
                    search: filters.search || undefined,
                    status: filters.status !== "all" ? (filters.status as Document['status']) : undefined,
                    docTypeId: filters.docTypeId || undefined,
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

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportDocuments({
                status: filters.status !== 'all' ? filters.status : undefined,
                document_type: filters.docTypeId?.toString(),
                start_date: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
                end_date: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
            });
            const filename = `documentos_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            toast.error('Error al exportar documentos');
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="flex items-center gap-2">
                        <FileText className="w-6 h-6 text-[#2563EB]" />
                        Buscador de Documentos
                    </h1>
                    <p className="text-[#64748B]">
                        Busca y gestiona todos los documentos del sistema
                    </p>
                </div>
                <Button
                    variant="outline"
                    onClick={handleExport}
                    disabled={isExporting}
                >
                    {isExporting ? (
                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    ) : (
                        <Download className="w-4 h-4 mr-2" />
                    )}
                    Exportar
                </Button>
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
                                    value={filters.search || ''}
                                    onChange={(e) => setFilter('search', e.target.value)}
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
                            <Select value={filters.docTypeId?.toString() || "all"} onValueChange={(value) => setFilter('docTypeId', value === "all" ? undefined : parseInt(value))}>
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
                            <Select value={filters.status || "all"} onValueChange={(value) => setFilter('status', value as Document['status'] | 'all')}>
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
                                initialDateFrom={dateRange?.from}
                                initialDateTo={dateRange?.to}
                                onUpdate={({ range }) => setDateRange(range)}
                                showCompare={false}
                                align="start"
                                compact
                            />
                        </div>
                    </div>

                    <div className="flex justify-end mt-4">
                        <Button
                            variant="outline"
                            onClick={handleResetFilters}
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
                            <PaginationControls
                                currentPage={pagination.currentPage}
                                totalPages={pagination.totalPages}
                                total={pagination.total}
                                perPage={pagination.perPage}
                                onPageChange={pagination.setPage}
                                onPerPageChange={pagination.setPerPage}
                                disabled={isLoading}
                                className="mt-4 pt-4 border-t"
                            />
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
