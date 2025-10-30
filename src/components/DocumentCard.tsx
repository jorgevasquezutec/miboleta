import { FileText, Download, Eye, Edit, CheckCircle, Clock, XCircle } from "lucide-react";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";

export type DocumentStatus = "pending" | "signed" | "expired" | "draft";
export type DocumentCategory = "payslip" | "contract" | "certificate" | "other";

interface DocumentCardProps {
  id: string;
  title: string;
  category: DocumentCategory;
  status: DocumentStatus;
  date: string;
  onView?: () => void;
  onDownload?: () => void;
  onSign?: () => void;
}

const statusConfig: Record<DocumentStatus, { label: string; color: string; icon: any }> = {
  pending: { label: "Pendiente", color: "bg-[#F59E0B] text-white", icon: Clock },
  signed: { label: "Firmado", color: "bg-[#10B981] text-white", icon: CheckCircle },
  expired: { label: "Vencido", color: "bg-[#EF4444] text-white", icon: XCircle },
  draft: { label: "Borrador", color: "bg-[#64748B] text-white", icon: Edit },
};

const categoryConfig: Record<DocumentCategory, { label: string; color: string }> = {
  payslip: { label: "Boleta de Pago", color: "bg-blue-100 text-blue-800" },
  contract: { label: "Contrato", color: "bg-purple-100 text-purple-800" },
  certificate: { label: "Certificado", color: "bg-green-100 text-green-800" },
  other: { label: "Otro", color: "bg-gray-100 text-gray-800" },
};

export function DocumentCard({
  id,
  title,
  category,
  status,
  date,
  onView,
  onDownload,
  onSign,
}: DocumentCardProps) {
  const StatusIcon = statusConfig[status].icon;

  return (
    <Card className="hover:shadow-md transition-shadow duration-200">
      <CardContent className="p-6">
        <div className="flex gap-4">
          {/* Document Icon */}
          <div className="flex-shrink-0">
            <div className="w-12 h-12 bg-[#F1F5F9] rounded-lg flex items-center justify-center">
              <FileText className="w-6 h-6 text-[#2563EB]" />
            </div>
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0">
            <div className="flex items-start justify-between gap-4 mb-2">
              <h3 className="truncate">{title}</h3>
              <Badge className={statusConfig[status].color}>
                <StatusIcon className="w-3 h-3 mr-1" />
                {statusConfig[status].label}
              </Badge>
            </div>

            <div className="flex flex-wrap gap-2 mb-3">
              <Badge variant="secondary" className={categoryConfig[category].color}>
                {categoryConfig[category].label}
              </Badge>
              <span className="text-[#64748B]">{date}</span>
            </div>

            {/* Actions */}
            <div className="flex gap-2">
              {onView && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={onView}
                  className="gap-2"
                >
                  <Eye className="w-4 h-4" />
                  Ver
                </Button>
              )}
              {onDownload && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={onDownload}
                  className="gap-2"
                >
                  <Download className="w-4 h-4" />
                  Descargar
                </Button>
              )}
              {status === "pending" && onSign && (
                <Button
                  size="sm"
                  onClick={onSign}
                  className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
                >
                  <Edit className="w-4 h-4" />
                  Firmar
                </Button>
              )}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
