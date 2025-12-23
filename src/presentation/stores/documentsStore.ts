import { create } from "zustand";
import { Document, DocumentType, DocumentBatch, ZipPreviewResponse } from "@/core/domain/entities";
import { documentRepository } from "@/infrastructure/persistence/repositories";
import { getErrorMessage } from "@/infrastructure/http/apiClient";

interface DocumentsState {
  // Documents
  documents: Document[];
  currentDocument: Document | null;
  isLoading: boolean;
  error: string | null;

  // Pagination
  page: number;
  perPage: number;
  total: number;
  totalPages: number;

  // Filters
  searchTerm: string;
  statusFilter: Document['status'] | 'all';
  typeFilter: number | null;
  periodFilter: string;

  // Document Types
  documentTypes: DocumentType[];
  typesLoading: boolean;

  // Batches
  batches: DocumentBatch[];
  currentBatch: (DocumentBatch & { documentsSummary?: any }) | null;
  batchesLoading: boolean;
  batchesMeta: { currentPage: number; lastPage: number; perPage: number; total: number } | null;

  // Orphans
  orphans: Document[];
  orphansLoading: boolean;

  // Signature
  signatureTermsAccepted: boolean;
  signatureLoading: boolean;

  // ZIP Preview
  zipPreview: ZipPreviewResponse | null;

  // Document Actions
  fetchDocuments: (params?: {
    page?: number;
    perPage?: number;
    search?: string;
    status?: Document['status'] | 'all';
    docTypeId?: number | null;
    tenantId?: number | null;
    period?: string;
    dateFrom?: string;
    dateTo?: string;
    myDocuments?: boolean;
  }) => Promise<void>;
  fetchDocumentById: (id: number) => Promise<void>;
  deleteDocument: (id: number) => Promise<void>;

  // Filter Actions
  setSearchTerm: (term: string) => void;
  setStatusFilter: (status: Document['status'] | 'all') => void;
  setTypeFilter: (typeId: number | null) => void;
  setPeriodFilter: (period: string) => void;
  setPage: (page: number) => void;
  setPerPage: (size: number) => void;

  // Document Types Actions
  fetchDocumentTypes: () => Promise<void>;

  // Batch Actions
  fetchBatches: (params?: { page?: number; perPage?: number; status?: string; typeId?: number; dateFrom?: string; dateTo?: string }) => Promise<void>;
  fetchBatchById: (id: number) => Promise<void>;
  uploadBatch: (data: {
    file: File;
    typeId: number;
    period: string;
    notifyEmployees: boolean;
    requiresSignature: boolean;
  }) => Promise<{ batchId: number }>;
  previewZip: (file: File) => Promise<void>;
  clearZipPreview: () => void;

  // Orphan Actions
  fetchOrphans: (page?: number) => Promise<void>;
  assignOrphan: (documentId: number, userId: number) => Promise<void>;

  // Signature Actions
  checkSignatureTerms: () => Promise<void>;
  acceptSignatureTerms: () => Promise<void>;
  requestSignatureCode: (documentId: number) => Promise<{ expiresIn: number; emailSentTo: string }>;
  signDocument: (documentId: number, code: string) => Promise<void>;

  // Utility
  clearError: () => void;
  reset: () => void;
}

const initialState = {
  documents: [],
  currentDocument: null,
  isLoading: false,
  error: null,
  page: 1,
  perPage: 10,
  total: 0,
  totalPages: 0,
  searchTerm: "",
  statusFilter: "all" as const,
  typeFilter: null,
  periodFilter: "",
  documentTypes: [],
  typesLoading: false,
  batches: [],
  currentBatch: null,
  batchesLoading: false,
  batchesMeta: null,
  orphans: [],
  orphansLoading: false,
  signatureTermsAccepted: false,
  signatureLoading: false,
  zipPreview: null,
};

export const useDocumentsStore = create<DocumentsState>((set, get) => ({
  ...initialState,

  // ============ Documents ============

  fetchDocuments: async (params) => {
    set({ isLoading: true, error: null });

    const state = get();
    const fetchParams = {
      page: params?.page ?? state.page,
      perPage: params?.perPage ?? state.perPage,
      search: (params?.search ?? state.searchTerm) || undefined,
      status: (params?.status ?? state.statusFilter) !== 'all'
        ? (params?.status ?? state.statusFilter) as Document['status']
        : undefined,
      docTypeId: params?.docTypeId ?? state.typeFilter ?? undefined,
      tenantId: params?.tenantId ?? undefined,
      period: (params?.period ?? state.periodFilter) || undefined,
      dateFrom: params?.dateFrom || undefined,
      dateTo: params?.dateTo || undefined,
      myDocuments: params?.myDocuments || undefined,
    };

    try {
      const response = await documentRepository.findAll(fetchParams);

      set({
        documents: response.data,
        page: response.meta.currentPage,
        perPage: response.meta.perPage,
        total: response.meta.total,
        totalPages: response.meta.lastPage,
        isLoading: false,
      });
    } catch (error) {
      console.error("Error in fetchDocuments:", error);
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
    }
  },

  fetchDocumentById: async (id: number) => {
    set({ isLoading: true, error: null });

    try {
      const document = await documentRepository.findById(id);
      set({ currentDocument: document, isLoading: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
    }
  },

  deleteDocument: async (id: number) => {
    set({ isLoading: true, error: null });

    try {
      await documentRepository.delete(id);
      set((state) => ({
        documents: state.documents.filter((d) => d.id !== id),
        isLoading: false,
      }));
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
      throw error;
    }
  },

  // ============ Filters ============

  setSearchTerm: (term: string) => {
    set({ searchTerm: term, page: 1 });
    get().fetchDocuments();
  },

  setStatusFilter: (status) => {
    set({ statusFilter: status, page: 1 });
    get().fetchDocuments();
  },

  setTypeFilter: (typeId) => {
    set({ typeFilter: typeId, page: 1 });
    get().fetchDocuments();
  },

  setPeriodFilter: (period) => {
    set({ periodFilter: period, page: 1 });
    get().fetchDocuments();
  },

  setPage: (page: number) => {
    set({ page });
    get().fetchDocuments();
  },

  setPerPage: (size: number) => {
    set({ perPage: size, page: 1 });
    get().fetchDocuments();
  },

  // ============ Document Types ============

  fetchDocumentTypes: async () => {
    set({ typesLoading: true });
    try {
      const types = await documentRepository.getDocumentTypes();
      set({ documentTypes: types, typesLoading: false });
    } catch (error) {
      set({ typesLoading: false });
      console.error("Error fetching document types:", error);
    }
  },

  // ============ Batches ============

  fetchBatches: async (params) => {
    set({ batchesLoading: true, error: null });

    try {
      const response = await documentRepository.getBatches(params);
      set({
        batches: response.data,
        batchesMeta: {
          currentPage: response.meta.currentPage,
          lastPage: response.meta.lastPage,
          perPage: response.meta.perPage,
          total: response.meta.total,
        },
        batchesLoading: false
      });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        batchesLoading: false,
      });
    }
  },

  fetchBatchById: async (id: number) => {
    set({ batchesLoading: true, error: null });

    try {
      const batch = await documentRepository.getBatchById(id);
      set({ currentBatch: batch, batchesLoading: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        batchesLoading: false,
      });
    }
  },

  uploadBatch: async (data) => {
    set({ isLoading: true, error: null });

    try {
      const result = await documentRepository.uploadBatch(data);
      set({ isLoading: false });
      return result;
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
      throw error;
    }
  },

  previewZip: async (file: File) => {
    set({ isLoading: true, error: null, zipPreview: null });

    try {
      const preview = await documentRepository.previewZip(file);
      set({ zipPreview: preview, isLoading: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
      throw error;
    }
  },

  clearZipPreview: () => set({ zipPreview: null }),

  // ============ Orphans ============

  fetchOrphans: async (page = 1) => {
    set({ orphansLoading: true, error: null });

    try {
      const response = await documentRepository.getOrphans({ page });
      set({ orphans: response.data, orphansLoading: false });
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        orphansLoading: false,
      });
    }
  },

  assignOrphan: async (documentId: number, userId: number) => {
    set({ isLoading: true, error: null });

    try {
      await documentRepository.assignOrphan(documentId, userId);
      // Remove from orphans list
      set((state) => ({
        orphans: state.orphans.filter((d) => d.id !== documentId),
        isLoading: false,
      }));
    } catch (error) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        isLoading: false,
      });
      throw error;
    }
  },

  // ============ Signature ============

  checkSignatureTerms: async () => {
    try {
      console.log('[documentsStore] Checking signature terms...');
      const result = await documentRepository.checkSignatureTerms();
      console.log('[documentsStore] Signature terms result:', result);
      set({ signatureTermsAccepted: result.accepted });
      console.log('[documentsStore] signatureTermsAccepted set to:', result.accepted);
    } catch (error) {
      console.error("[documentsStore] Error checking signature terms:", error);
    }
  },

  acceptSignatureTerms: async () => {
    console.log('[documentsStore] Accepting signature terms...');
    set({ signatureLoading: true, error: null });

    try {
      await documentRepository.acceptSignatureTerms();
      console.log('[documentsStore] Signature terms accepted successfully');
      set({ signatureTermsAccepted: true, signatureLoading: false });
      console.log('[documentsStore] signatureTermsAccepted set to: true');
    } catch (error) {
      console.error('[documentsStore] Error accepting signature terms:', error);
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        signatureLoading: false,
      });
      throw error;
    }
  },

  requestSignatureCode: async (documentId: number) => {
    set({ signatureLoading: true, error: null });

    try {
      const result = await documentRepository.requestSignatureCode(documentId);
      set({ signatureLoading: false });
      return result;
    } catch (error: any) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        signatureLoading: false,
      });
      throw error;
    }
  },

  signDocument: async (documentId: number, code: string) => {
    set({ signatureLoading: true, error: null });

    try {
      await documentRepository.signDocument(documentId, code);

      // Update document in state
      set((state) => ({
        currentDocument: state.currentDocument
          ? { ...state.currentDocument, status: 'signed' as const, signedAt: new Date().toISOString() }
          : null,
        documents: state.documents.map((d) =>
          d.id === documentId
            ? { ...d, status: 'signed' as const, signedAt: new Date().toISOString() }
            : d
        ),
        signatureLoading: false,
      }));
    } catch (error: any) {
      const errorMessage = getErrorMessage(error);
      set({
        error: errorMessage,
        signatureLoading: false,
      });
      throw error;
    }
  },

  // ============ Utility ============

  clearError: () => set({ error: null }),

  reset: () => set(initialState),
}));
