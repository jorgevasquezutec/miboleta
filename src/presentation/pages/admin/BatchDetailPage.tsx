import { useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
    ArrowLeft,
    FileStack,
    CheckCircle,
    Clock,
    AlertTriangle,
    XCircle,
    RefreshCw,
    Users,
    FileText,
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Progress } from "@/presentation/components/ui/progress";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/presentation/components/ui/table";
import { useDocumentsStore } from "@/presentation/stores";

export function BatchDetailPage() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const { currentBatch, fetchBatchById, batchesLoading, documents, fetchDocuments } = useDocumentsStore();

    useEffect(() => {
        if (id) {
            fetchBatchById(parseInt(id));
            // Fetch documents for this batch
            fetchDocuments({ page: 1, perPage: 100 });
        }
    }, [id, fetchBatchById, fetchDocuments]);

    if (batchesLoading && !currentBatch) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <RefreshCw className="w-8 h-8 animate-spin text-[#2563EB]" />
            </div>
        );
    }

    if (!currentBatch) {
        return (
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" onClick={() => navigate("/batches")}>
                        <ArrowLeft className="w-5 h-5" />
                    </Button>
                    <h1>Lote no encontrado</h1>
                </div>
            </div>
        );
    }

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return "-";
        return new Date(dateStr).toLocaleString("es-PE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const getStatusBadge = (status: string) => {
        const config = {
            pending: {
                label: "Pendiente",
                bgColor: "#eab308",
                icon: <Clock className="w-3 h-3" />
            },
            processing: {
                label: "Procesando",
                bgColor: "#3b82f6",
                icon: <RefreshCw className="w-3 h-3 animate-spin" />
            },
            completed: {
                label: "Completado",
                bgColor: "#22c55e",
                icon: <CheckCircle className="w-3 h-3" />
            },
            failed: {
                label: "Fallido",
                bgColor: "#ef4444",
                icon: <XCircle className="w-3 h-3" />
            },
            partial: {
                label: "Parcial",
                bgColor: "#f97316",
                icon: <AlertTriangle className="w-3 h-3" />
            },
        };

        const item = config[status as keyof typeof config] || config.pending;
        return (
            <span
                style={{
                    backgroundColor: item.bgColor,
                    color: 'white'
                }}
                className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium"
            >
                {item.icon}
                {item.label}
            </span>
        );
    };

    const progressPercentage = currentBatch.progressPercentage || 0;
    const summary = currentBatch.documentsSummary;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={() => navigate("/batches")}>
                    <ArrowLeft className="w-5 h-5" />
                </Button>
                <div className="flex-1">
                    <h1 className="flex items-center gap-2">
                        <FileStack className="w-6 h-6 text-[#2563EB]" />
                        Lote #{currentBatch.id}
                    </h1>
                    <p className="text-[#64748B]">{currentBatch.originalFilename}</p>
                </div>
                {getStatusBadge(currentBatch.status)}
            </div>

            {/* Progress Bar */}
            {currentBatch.status === "processing" && (
                <Card>
                    <CardContent className="p-6">
                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="font-medium">Progreso de procesamiento</span>
                                <span className="text-[#64748B]">
                                    {currentBatch.processedFiles} / {currentBatch.totalFiles} archivos
                                </span>
                            </div>
                            <Progress value={progressPercentage} className="h-2" />
                            <div className="text-sm text-[#64748B] text-right">{progressPercentage}%</div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-[#64748B]">Total Archivos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2">
                            <FileText className="w-5 h-5 text-[#2563EB]" />
                            <span className="text-2xl font-bold">{currentBatch.totalFiles}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-[#64748B]">Exitosos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2">
                            <CheckCircle className="w-5 h-5 text-green-600" />
                            <span className="text-2xl font-bold text-green-600">{currentBatch.successCount}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-[#64748B]">Huérfanos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2">
                            <Users className="w-5 h-5 text-orange-600" />
                            <span className="text-2xl font-bold text-orange-600">{currentBatch.orphanCount}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-[#64748B]">Errores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2">
                            <XCircle className="w-5 h-5 text-red-600" />
                            <span className="text-2xl font-bold text-red-600">{currentBatch.errorCount}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Batch Information */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del Lote</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Tipo de Documento</span>
                            <span className="font-medium">{currentBatch.documentType?.displayName || "-"}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Período</span>
                            <span className="font-medium">{currentBatch.period}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Cargado por</span>
                            <span className="font-medium">{currentBatch.uploader?.name || "-"}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Fecha de carga</span>
                            <span className="font-medium">{formatDate(currentBatch.createdAt)}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Iniciado</span>
                            <span className="font-medium">{formatDate(currentBatch.startedAt)}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Completado</span>
                            <span className="font-medium">{formatDate(currentBatch.completedAt)}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Notificar empleados</span>
                            <span className="font-medium">{currentBatch.notifyEmployees ? "Sí" : "No"}</span>
                        </div>
                        <div className="flex justify-between py-2">
                            <span className="text-[#64748B]">Requiere firma</span>
                            <span className="font-medium">{currentBatch.requiresSignature ? "Sí" : "No"}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Resumen de Procesamiento</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Archivos procesados</span>
                            <span className="font-medium">{currentBatch.processedFiles}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Documentos creados</span>
                            <span className="font-medium text-green-600">{currentBatch.successCount}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Documentos reemplazados</span>
                            <span className="font-medium text-blue-600">{currentBatch.replacedCount}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Documentos huérfanos</span>
                            <span className="font-medium text-orange-600">{currentBatch.orphanCount}</span>
                        </div>
                        <div className="flex justify-between py-2 border-b">
                            <span className="text-[#64748B]">Errores</span>
                            <span className="font-medium text-red-600">{currentBatch.errorCount}</span>
                        </div>
                        {summary && (
                            <>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-[#64748B]">Total documentos</span>
                                    <span className="font-medium">{summary.total}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b">
                                    <span className="text-[#64748B]">Pendientes</span>
                                    <span className="font-medium text-yellow-600">{summary.pending}</span>
                                </div>
                                <div className="flex justify-between py-2">
                                    <span className="text-[#64748B]">Firmados</span>
                                    <span className="font-medium text-green-600">{summary.signed}</span>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Errors */}
            {currentBatch.errors && currentBatch.errors.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-orange-600" />
                            Errores de Procesamiento ({currentBatch.errors.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {currentBatch.errors.map((error: any, index: number) => (
                                <div
                                    key={index}
                                    className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm"
                                >
                                    <div className="font-medium text-red-800">{error.file || error.message}</div>
                                    {error.reason && <div className="text-red-600 mt-1">{error.reason}</div>}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Documents Table */}
            {documents && documents.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Documentos del Lote ({documents.filter(d => d.batchId === currentBatch.id).length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Documento</TableHead>
                                    <TableHead>Archivo</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents
                                    .filter(d => d.batchId === currentBatch.id)
                                    .map((doc) => {
                                        const statusColors = {
                                            pending: { bg: "#eab308", label: "Pendiente" },
                                            signed: { bg: "#22c55e", label: "Firmado" },
                                            orphan: { bg: "#f97316", label: "Huérfano" },
                                            expired: { bg: "#ef4444", label: "Expirado" },
                                        };
                                        const statusInfo = statusColors[doc.status as keyof typeof statusColors] || statusColors.pending;

                                        return (
                                            <TableRow key={doc.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{doc.user?.name} {doc.user?.lastName}</div>
                                                        <div className="text-sm text-[#64748B]">{doc.user?.documentText || doc.employeeDocumentNumber}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{doc.documentType?.displayName || "-"}</TableCell>
                                                <TableCell className="max-w-[200px] truncate" title={doc.originalName}>
                                                    {doc.originalName}
                                                </TableCell>
                                                <TableCell>
                                                    <span
                                                        style={{ backgroundColor: statusInfo.bg, color: 'white' }}
                                                        className="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium"
                                                    >
                                                        {statusInfo.label}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-sm text-[#64748B]">
                                                    {new Date(doc.createdAt).toLocaleDateString('es-PE')}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => navigate(`/viewer?id=${doc.id}`)}
                                                    >
                                                        Ver
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {/* Actions */}
            <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => navigate("/batches")}>
                    Volver a la lista
                </Button>
            </div>
        </div>
    );
}

export default BatchDetailPage;
