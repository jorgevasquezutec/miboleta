import { useState, useEffect, useMemo } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useSearchParams, useNavigate } from "react-router-dom";
import { ArrowLeft, Download, FileText, CheckCircle, Info, Loader2, AlertCircle } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";
import { Separator } from "@/presentation/components/ui/separator";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { useDocumentsStore, useAuthStore } from "@/presentation/stores";
import { PDFViewer } from "@/presentation/components/shared/PDFViewer";
import { DocumentSignatureModal } from "@/presentation/components/features/documents/DocumentSignatureModal";
import { getDocumentStatusBadge, formatDate, formatDateTime } from "@/presentation/utils";

interface DocumentViewerViewProps {
  onBack?: () => void;
}

export function DocumentViewerView({ onBack }: DocumentViewerViewProps) {
  useDocumentTitle('Visor de Documento');
  const navigate = useNavigate();

  const handleBack = () => {
    if (onBack) {
      onBack();
    } else {
      navigate(-1); // Go back to previous page
    }
  };
  const [searchParams] = useSearchParams();
  const documentId = searchParams.get("id");

  const { user } = useAuthStore();
  const {
    currentDocument,
    fetchDocumentById,
    isLoading,
    error,
    signatureTermsAccepted,
    checkSignatureTerms,
    acceptSignatureTerms,
    requestSignatureCode,
    signDocument,
  } = useDocumentsStore();

  const [showSignatureModal, setShowSignatureModal] = useState(false);
  const [pdfCacheBuster, setPdfCacheBuster] = useState<number | null>(null);

  const pdfUrl = useMemo(() => {
    if (!documentId) return null;
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
    const url = `${baseUrl}/documents/${documentId}/preview`;
    return pdfCacheBuster ? `${url}?t=${pdfCacheBuster}` : url;
  }, [documentId, pdfCacheBuster]);

  useEffect(() => {
    if (documentId) {
      fetchDocumentById(parseInt(documentId));
    }
  }, [documentId, fetchDocumentById]);

  // Check if user has accepted signature terms
  useEffect(() => {
    checkSignatureTerms();
  }, [checkSignatureTerms]);

  const handleSign = () => {
    setShowSignatureModal(true);
  };

  const handleSignatureSuccess = async () => {
    toast.success("¡Documento firmado exitosamente!");
    // Refresh document to get updated status
    if (documentId) {
      await fetchDocumentById(parseInt(documentId));
      // Force PDF viewer to reload by adding cache-busting timestamp
      setPdfCacheBuster(Date.now());
    }
  };

  const handleDownload = () => {
    if (documentId) {
      const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
      window.open(`${baseUrl}/documents/${documentId}/download`, '_blank');
      toast.success("Descargando documento...");
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#2563EB] mx-auto mb-4" />
          <p className="text-[#64748B]">Cargando documento...</p>
        </div>
      </div>
    );
  }

  if (!currentDocument) {
    // Show error only when document is null (not loaded)
    if (error) {
      toast.error(error);
    }
    return (
      <div className="flex items-center justify-center h-96">
        <Card className="max-w-md">
          <CardContent className="p-6 text-center">
            <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h3 className="mb-2">Documento no encontrado</h3>
            <p className="text-[#64748B] mb-4">{error || "No se pudo cargar el documento"}</p>
            <Button onClick={handleBack}>Volver</Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
        <div className="flex items-start gap-3">
          <Button variant="ghost" size="icon" onClick={handleBack} className="h-9 w-9 flex-shrink-0 mt-0.5">
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div className="flex-1 min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-lg sm:text-xl font-semibold">{currentDocument.documentType?.displayName || "Documento"}</h1>
              {getDocumentStatusBadge(currentDocument.status)}
            </div>
            <p className="text-sm text-[#64748B] mt-1">
              {currentDocument.period} • Subido el {formatDate(currentDocument.createdAt)}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2 pl-12 sm:pl-0">
          <Button variant="outline" className="gap-2 h-9" onClick={handleDownload}>
            <Download className="w-4 h-4" />
            <span className="hidden xs:inline">Descargar</span>
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Document Viewer */}
        <div className="lg:col-span-2 order-2 lg:order-1">
          <Card className="overflow-hidden">
            <CardContent className="p-0">
              {/* PDF Viewer */}
              <div className="w-full h-[50vh] sm:h-[60vh] lg:h-[calc(100vh-200px)] min-h-[300px] max-h-[700px]">
                {pdfUrl ? (
                  <PDFViewer
                    url={pdfUrl}
                  />
                ) : (
                  <div className="flex items-center justify-center h-full bg-gray-100">
                    <div className="text-center">
                      <FileText className="w-16 h-16 text-[#64748B] mx-auto mb-4" />
                      <p className="text-[#64748B]">No se puede mostrar el documento</p>
                    </div>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Side Panel */}
        <div className="space-y-4 sm:space-y-6 order-1 lg:order-2">
          {/* Document Info */}
          <Card>
            <CardContent className="p-6 space-y-4">
              <div>
                <h3 className="mb-4">Información del Documento</h3>
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-[#64748B]">Tipo:</span>
                    <span>{currentDocument.documentType?.displayName || "-"}</span>
                  </div>
                  <Separator />
                  <div className="flex justify-between">
                    <span className="text-[#64748B]">Período:</span>
                    <span>{currentDocument.period}</span>
                  </div>
                  <Separator />
                  <div className="flex justify-between">
                    <span className="text-[#64748B]">Estado:</span>
                    {getDocumentStatusBadge(currentDocument.status)}
                  </div>
                  <Separator />
                  <div className="flex justify-between">
                    <span className="text-[#64748B]">Fecha subida:</span>
                    <span>{formatDateTime(currentDocument.createdAt)}</span>
                  </div>
                  {currentDocument.user && (
                    <>
                      <Separator />
                      <div className="flex justify-between">
                        <span className="text-[#64748B]">Usuario:</span>
                        <span>{currentDocument.user.name} {currentDocument.user.lastName}</span>
                      </div>
                    </>
                  )}
                  {currentDocument.signedAt && (
                    <>
                      <Separator />
                      <div className="flex justify-between">
                        <span className="text-[#64748B]">Firmado:</span>
                        <span className="text-green-600">{formatDate(currentDocument.signedAt)}</span>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Signature Section - Only show if pending, requires signature, AND user is the owner */}
          {(() => {
            const docUserId = currentDocument.userId;
            const currentUserId = user?.id ? Number(user.id) : null;
            const isOwner = docUserId !== null && currentUserId !== null && docUserId === currentUserId;

            if (currentDocument.status === 'pending' && currentDocument.requiresSignature && isOwner) {
              return (
                <Card className="border-[#2563EB]">
                  <CardContent className="p-6 space-y-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <FileText className="w-5 h-5 text-[#2563EB]" />
                      </div>
                      <div>
                        <h3 className="text-[#1E40AF]">Firma Digital</h3>
                        <p className="text-[#64748B]">
                          Requerida para continuar
                        </p>
                      </div>
                    </div>

                    <Alert>
                      <Info className="w-4 h-4" />
                      <AlertDescription>
                        Por favor lee el documento completo antes de firmar. Tu firma
                        tendrá validez legal.
                      </AlertDescription>
                    </Alert>

                    <Button
                      className="w-full h-12 bg-[#2563EB] hover:bg-[#1E40AF] mt-4"
                      onClick={handleSign}
                    >
                      <CheckCircle className="w-5 h-5 mr-2" />
                      Firmar Documento
                    </Button>
                  </CardContent>
                </Card>
              );
            }
            return null;
          })()}

          {/* Signed Info */}
          {currentDocument.status === 'signed' && (
            <Card className="border-green-500">
              <CardContent className="p-6">
                <div className="flex items-center gap-3 mb-4">
                  <div className="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <CheckCircle className="w-5 h-5 text-green-600" />
                  </div>
                  <div>
                    <h3 className="text-green-700">Documento Firmado</h3>
                    <p className="text-[#64748B]">
                      {currentDocument.signedAt
                        ? `Firmado el ${formatDate(currentDocument.signedAt)}`
                        : "Este documento ya ha sido firmado"}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      {/* Signature Modal */}
      {currentDocument && (
        <DocumentSignatureModal
          isOpen={showSignatureModal}
          onClose={() => setShowSignatureModal(false)}
          onSuccess={handleSignatureSuccess}
          documentId={parseInt(documentId || "0")}
          documentType={currentDocument.documentType?.displayName || "Documento"}
          period={currentDocument.period}
          requiresTermsAcceptance={!signatureTermsAccepted}
          onRequestCode={requestSignatureCode}
          onVerifyCode={signDocument}
          onAcceptTerms={acceptSignatureTerms}
        />
      )}
    </div>
  );
}

export default DocumentViewerView;
