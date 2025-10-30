import { create } from "zustand";
import { mockApi, type Document, type PaginatedResponse } from "../services/mockApi";

interface DocumentsState {
  documents: Document[];
  currentDocument: Document | null;
  isLoading: boolean;
  error: string | null;

  // Pagination
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;

  // Filters
  searchTerm: string;
  statusFilter: string;
  categoryFilter: string;

  // Actions
  fetchDocuments: (params?: {
    page?: number;
    pageSize?: number;
    search?: string;
    status?: string;
    category?: string;
  }) => Promise<void>;
  fetchDocumentById: (id: string) => Promise<void>;
  setSearchTerm: (term: string) => void;
  setStatusFilter: (status: string) => void;
  setCategoryFilter: (category: string) => void;
  setPage: (page: number) => void;
  setPageSize: (size: number) => void;
  clearError: () => void;
}

export const useDocumentsStore = create<DocumentsState>((set, get) => ({
  documents: [],
  currentDocument: null,
  isLoading: false,
  error: null,

  // Pagination
  page: 1,
  pageSize: 6,
  totalItems: 0,
  totalPages: 0,

  // Filters
  searchTerm: "",
  statusFilter: "all",
  categoryFilter: "all",

  fetchDocuments: async (params) => {
    set({ isLoading: true, error: null });

    const state = get();
    const fetchParams = {
      page: params?.page ?? state.page,
      pageSize: params?.pageSize ?? state.pageSize,
      search: params?.search ?? state.searchTerm,
      status: params?.status ?? state.statusFilter,
      category: params?.category ?? state.categoryFilter,
    };

    try {
      // Simular petición HTTP a /api/documents
      const response: PaginatedResponse<Document> = await mockApi.getDocuments(fetchParams);

      set({
        documents: response.data,
        page: response.page,
        pageSize: response.pageSize,
        totalItems: response.totalItems,
        totalPages: response.totalPages,
        isLoading: false,
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al cargar documentos",
        isLoading: false,
      });
    }
  },

  fetchDocumentById: async (id: string) => {
    set({ isLoading: true, error: null });

    try {
      const document = await mockApi.getDocumentById(id);

      set({
        currentDocument: document,
        isLoading: false,
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al cargar documento",
        isLoading: false,
      });
    }
  },

  setSearchTerm: (term: string) => {
    set({ searchTerm: term, page: 1 }); // Reset to page 1 on search
    get().fetchDocuments();
  },

  setStatusFilter: (status: string) => {
    set({ statusFilter: status, page: 1 });
    get().fetchDocuments();
  },

  setCategoryFilter: (category: string) => {
    set({ categoryFilter: category, page: 1 });
    get().fetchDocuments();
  },

  setPage: (page: number) => {
    set({ page });
    get().fetchDocuments();
  },

  setPageSize: (size: number) => {
    set({ pageSize: size, page: 1 });
    get().fetchDocuments();
  },

  clearError: () => set({ error: null }),
}));
