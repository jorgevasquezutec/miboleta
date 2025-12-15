import { useState, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { FileText, Search, Filter, Eye, Trash2, Download, Loader2 } from "lucide-react";
import { format, parse } from "date-fns";
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
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useUrlFilters } from "@/presentation/hooks";
import { useDocumentsStore } from "@/presentation/stores";
import { Document } from "@/core/domain/entities/Document";
import { useAuthStore } from "@/presentation/stores";
import { getDocumentStatusBadgeInline } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";

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

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            search: '',
            status: 'all',
            doc_type_id: '',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 10,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [documentTypes, setDocumentTypes] = useState<any[]>([]);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<number | null>(null);
    const [isExporting, setIsExporting] = useState(false);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

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
        const fetchTypes = async () => {
            await fetchDocumentTypes();
        };
        fetchTypes();
    }, [fetchDocumentTypes]);

    // Fetch documents when filters change
    useEffect(() => {
        fetchDocuments({
            page: filters.page,
            perPage: filters.per_page,
            search: filters.search || undefined,
            status: filters.status !== 'all' ? (filters.status as Document['status']) : undefined,
            docTypeId: filters.doc_type_id ? parseInt(filters.doc_type_id) : undefined,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
        });
    }, [filters.page, filters.per_page, filters.search, filters.status, filters.doc_type_id, filters.date_from, filters.date_to, fetchDocuments]);

    const handleSearch = () => {
        setFilters({ page: 1 });
    };

    const handleResetFilters = () => {
        setSearchInput('');
        resetFilters();
    };

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handleDocTypeChange = (value: string) => {
        setFilters({ doc_type_id: value === 'all' ? '' : value, page: 1 });
    };

    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
            page: 1,
        });
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
    };

    const handlePerPageChange = (perPage: number) => {
        setFilters({ per_page: perPage, page: 1 });
    };

    const handleDeleteClick = (id: number) => {
        setDocumentToDelete(id);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = async () => {
        if (documentToDelete) {
            try {
                await deleteDocument(documentToDelete);
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
                search: filters.search || undefined,
                status: filters.status !== 'all' ? filters.status : undefined,
                document_type: filters.doc_type_id || undefined,
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
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
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
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
                            <Select value={filters.doc_type_id || 'all'} onValueChange={handleDocTypeChange}>
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
                            <Select value={filters.status} onValueChange={handleStatusChange}>
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
                                onUpdate={handleDateRangeChange}
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
                                currentPage={filters.page}
                                totalPages={totalPages}
                                total={total}
                                perPage={filters.per_page}
                                onPageChange={handlePageChange}
                                onPerPageChange={handlePerPageChange}
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
