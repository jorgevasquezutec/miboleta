import { create } from "zustand";
import { Document } from "@/core/domain/entities";
import { GetDocumentsUseCase, DeleteDocumentUseCase } from "@/core/domain/use-cases/documents";
import { documentRepository } from "@/infrastructure/persistence/repositories";
import { PaginatedDocuments } from "@/core/domain/repositories";

// Instanciar use cases
const getDocumentsUseCase = new GetDocumentsUseCase(documentRepository);
const deleteDocumentUseCase = new DeleteDocumentUseCase(documentRepository);

interface DocumentsState {
  documents: Document[];
  currentDocument: Document | null;
  isLoading: boolean;
  error: string | null;

  // Pagination
  page: number;
  limit: number;
  total: number;
  totalPages: number;

  // Filters
  searchTerm: string;
  statusFilter: string;
  categoryFilter: string;

  // Actions
  fetchDocuments: (params?: {
    page?: number;
    limit?: number;
    search?: string;
    status?: string;
    category?: string;
  }) => Promise<void>;
  fetchDocumentById: (id: string) => Promise<void>;
  deleteDocument: (id: string) => Promise<void>;
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
  limit: 6,
  total: 0,
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
      pageSize: params?.limit ?? state.limit,
      searchTerm: params?.search ?? state.searchTerm,
      status: params?.status !== 'all' && params?.status ? params?.status as Document['status'] : 
              state.statusFilter !== 'all' ? state.statusFilter as Document['status'] : undefined,
      category: params?.category !== 'all' && params?.category ? params?.category as Document['category'] : 
                state.categoryFilter !== 'all' ? state.categoryFilter as Document['category'] : undefined,
    };

    try {
      const response: PaginatedDocuments = await getDocumentsUseCase.execute(fetchParams);

      set({
        documents: response.data,
        page: response.page,
        limit: response.pageSize,
        total: response.total,
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
      const document = await documentRepository.findById(id);

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

  deleteDocument: async (id: string) => {
    set({ isLoading: true, error: null });

    try {
      await deleteDocumentUseCase.execute(id);

      set((state) => ({
        documents: state.documents.filter((d) => d.id !== id),
        isLoading: false,
      }));
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al eliminar documento",
        isLoading: false,
      });
      throw error;
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
    set({ limit: size, page: 1 });
    get().fetchDocuments();
  },

  clearError: () => set({ error: null }),
}));
