import { useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { ArrowLeft, Download, FileText, CheckCircle, Info, Loader2, AlertCircle } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";
import { Separator } from "@/presentation/components/ui/separator";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { useDocumentsStore, useAuthStore } from "@/presentation/stores";
import { PDFViewer } from "@/presentation/components/shared/PDFViewer";
import { DocumentSignatureModal } from "@/presentation/components/shared/DocumentSignatureModal";
import { getDocumentStatusBadge, formatDate } from "@/presentation/utils";

interface DocumentViewerViewProps {
  onBack: () => void;
}

export function DocumentViewerView({ onBack }: DocumentViewerViewProps) {
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
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);

  useEffect(() => {
    if (documentId) {
      fetchDocumentById(parseInt(documentId));
      // Build PDF preview URL (inline display, not download)
      const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
      setPdfUrl(`${baseUrl}/documents/${documentId}/preview`);
    }
  }, [documentId, fetchDocumentById]);

  // Check if user has accepted signature terms
  useEffect(() => {
    checkSignatureTerms();
  }, [checkSignatureTerms]);

  // Log signature terms state for debugging
  useEffect(() => {
    console.log('[DocumentViewerView] signatureTermsAccepted:', signatureTermsAccepted);
  }, [signatureTermsAccepted]);

  const handleSign = () => {
    console.log('[DocumentViewerView] Opening signature modal');
    console.log('[DocumentViewerView] signatureTermsAccepted:', signatureTermsAccepted);
    console.log('[DocumentViewerView] requiresTermsAcceptance will be:', !signatureTermsAccepted);
    setShowSignatureModal(true);
  };

  const handleSignatureSuccess = async () => {
    toast.success("¡Documento firmado exitosamente!");
    // Refresh document to get updated status
    if (documentId) {
      await fetchDocumentById(parseInt(documentId));
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
            <Button onClick={onBack}>Volver</Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-xl font-semibold">{currentDocument.documentType?.displayName || "Documento"}</h1>
              {getDocumentStatusBadge(currentDocument.status)}
            </div>
            <p className="text-sm text-[#64748B]">
              {currentDocument.period} • Subido el {formatDate(currentDocument.createdAt)}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="gap-2" onClick={handleDownload}>
            <Download className="w-4 h-4" />
            Descargar
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Document Viewer */}
        <div className="lg:col-span-2">
          <Card className="overflow-hidden">
            <CardContent className="p-0">
              {/* PDF Viewer */}
              <div className="w-full h-[calc(100vh-200px)] min-h-[400px] max-h-[700px]">
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
        <div className="space-y-6">
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
                    <span>{formatDate(currentDocument.createdAt)}</span>
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
          {currentDocument.status === 'pending' &&
            currentDocument.requiresSignature &&
            currentDocument.userId === (user?.id ? parseInt(user.id) : null) && (
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
            )}

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
