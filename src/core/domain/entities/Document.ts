// Domain Entity - Document
export interface Document {
  id: string;
  title: string;
  description?: string;
  category: 'payslip' | 'contract' | 'certificate' | 'invoice' | 'receipt' | 'report' | 'other';
  status: 'pending' | 'approved' | 'rejected' | 'signed' | 'expired' | 'draft';
  fileUrl?: string;
  fileName?: string;
  fileSize?: number;
  mimeType?: string;
  uploadedBy: string;
  tenantId: string;
  tags?: string[];
  metadata?: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
}

export type CreateDocumentData = Omit<Document, 'id' | 'createdAt' | 'updatedAt'>;
export type UpdateDocumentData = Partial<Omit<Document, 'id' | 'createdAt' | 'updatedAt'>>;
