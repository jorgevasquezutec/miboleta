import { describe, it, expect, beforeEach, vi } from 'vitest';
import { act } from '@testing-library/react';
import { useDocumentsStore } from '../documentsStore';
import { mockDocument } from '@/test/mocks/handlers';

// Mock the documentRepository
vi.mock('@/infrastructure/persistence/repositories', () => ({
  documentRepository: {
    findAll: vi.fn(),
    findById: vi.fn(),
    delete: vi.fn(),
    getDocumentTypes: vi.fn(),
    getBatches: vi.fn(),
    getBatchById: vi.fn(),
    uploadBatch: vi.fn(),
    previewZip: vi.fn(),
    getOrphans: vi.fn(),
    assignOrphan: vi.fn(),
    checkSignatureTerms: vi.fn(),
    acceptSignatureTerms: vi.fn(),
    requestSignatureCode: vi.fn(),
    signDocument: vi.fn(),
  },
}));

import { documentRepository } from '@/infrastructure/persistence/repositories';

describe('documentsStore', () => {
  beforeEach(() => {
    // Reset store state
    useDocumentsStore.getState().reset();
    vi.clearAllMocks();
  });

  describe('initial state', () => {
    it('should have empty documents initially', () => {
      const state = useDocumentsStore.getState();
      expect(state.documents).toEqual([]);
    });

    it('should have null currentDocument initially', () => {
      const state = useDocumentsStore.getState();
      expect(state.currentDocument).toBeNull();
    });

    it('should not be loading initially', () => {
      const state = useDocumentsStore.getState();
      expect(state.isLoading).toBe(false);
    });

    it('should have default pagination values', () => {
      const state = useDocumentsStore.getState();
      expect(state.page).toBe(1);
      expect(state.perPage).toBe(10);
      expect(state.total).toBe(0);
    });

    it('should have default filter values', () => {
      const state = useDocumentsStore.getState();
      expect(state.searchTerm).toBe('');
      expect(state.statusFilter).toBe('all');
      expect(state.typeFilter).toBeNull();
    });
  });

  describe('fetchDocuments', () => {
    it('should fetch documents successfully', async () => {
      const mockResponse = {
        data: [mockDocument],
        meta: {
          currentPage: 1,
          perPage: 10,
          total: 1,
          lastPage: 1,
        },
      };

      vi.mocked(documentRepository.findAll).mockResolvedValueOnce(mockResponse);

      await act(async () => {
        await useDocumentsStore.getState().fetchDocuments();
      });

      const state = useDocumentsStore.getState();
      expect(state.documents).toHaveLength(1);
      expect(state.total).toBe(1);
      expect(state.isLoading).toBe(false);
    });

    it('should set loading state during fetch', async () => {
      let resolvePromise: (value: any) => void;
      const pendingPromise = new Promise((resolve) => {
        resolvePromise = resolve;
      });

      vi.mocked(documentRepository.findAll).mockReturnValueOnce(pendingPromise as any);

      const fetchAction = useDocumentsStore.getState().fetchDocuments();
      expect(useDocumentsStore.getState().isLoading).toBe(true);

      resolvePromise!({
        data: [],
        meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 },
      });

      await act(async () => {
        await fetchAction;
      });

      expect(useDocumentsStore.getState().isLoading).toBe(false);
    });

    it('should handle fetch error', async () => {
      vi.mocked(documentRepository.findAll).mockRejectedValueOnce(new Error('Network error'));

      await act(async () => {
        await useDocumentsStore.getState().fetchDocuments();
      });

      const state = useDocumentsStore.getState();
      expect(state.documents).toEqual([]);
      expect(state.error).toBeTruthy();
      expect(state.isLoading).toBe(false);
    });

    it('should apply pagination parameters', async () => {
      const mockResponse = {
        data: [],
        meta: { currentPage: 2, perPage: 20, total: 50, lastPage: 3 },
      };

      vi.mocked(documentRepository.findAll).mockResolvedValueOnce(mockResponse);

      await act(async () => {
        await useDocumentsStore.getState().fetchDocuments({ page: 2, perPage: 20 });
      });

      expect(documentRepository.findAll).toHaveBeenCalledWith(
        expect.objectContaining({ page: 2, perPage: 20 })
      );
    });
  });

  describe('fetchDocumentById', () => {
    it('should fetch single document', async () => {
      vi.mocked(documentRepository.findById).mockResolvedValueOnce(mockDocument as any);

      await act(async () => {
        await useDocumentsStore.getState().fetchDocumentById(1);
      });

      const state = useDocumentsStore.getState();
      expect(state.currentDocument).toEqual(mockDocument);
      expect(state.isLoading).toBe(false);
    });

    it('should handle fetch by id error', async () => {
      vi.mocked(documentRepository.findById).mockRejectedValueOnce(new Error('Not found'));

      await act(async () => {
        await useDocumentsStore.getState().fetchDocumentById(999);
      });

      const state = useDocumentsStore.getState();
      expect(state.currentDocument).toBeNull();
      expect(state.error).toBeTruthy();
    });
  });

  describe('deleteDocument', () => {
    it('should delete document and remove from state', async () => {
      // Use numeric IDs to match how the store filters
      const doc1 = { ...mockDocument, id: 1 };
      const doc2 = { ...mockDocument, id: 2 };
      useDocumentsStore.setState({
        documents: [doc1 as any, doc2 as any],
      });

      vi.mocked(documentRepository.delete).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useDocumentsStore.getState().deleteDocument(1);
      });

      const state = useDocumentsStore.getState();
      expect(state.documents).toHaveLength(1);
      expect(state.documents.find(d => d.id === 1)).toBeUndefined();
    });

    it('should handle delete error', async () => {
      vi.mocked(documentRepository.delete).mockRejectedValueOnce(new Error('Delete failed'));

      await act(async () => {
        try {
          await useDocumentsStore.getState().deleteDocument(1);
        } catch {
          // Expected error
        }
      });

      const state = useDocumentsStore.getState();
      expect(state.error).toBeTruthy();
    });
  });

  describe('filter actions', () => {
    it('should set search term and reset page', () => {
      vi.mocked(documentRepository.findAll).mockResolvedValue({
        data: [],
        meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 },
      });

      useDocumentsStore.setState({ page: 5 });

      act(() => {
        useDocumentsStore.getState().setSearchTerm('test');
      });

      const state = useDocumentsStore.getState();
      expect(state.searchTerm).toBe('test');
      expect(state.page).toBe(1);
    });

    it('should set status filter and reset page', () => {
      vi.mocked(documentRepository.findAll).mockResolvedValue({
        data: [],
        meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 },
      });

      useDocumentsStore.setState({ page: 5 });

      act(() => {
        useDocumentsStore.getState().setStatusFilter('signed');
      });

      const state = useDocumentsStore.getState();
      expect(state.statusFilter).toBe('signed');
      expect(state.page).toBe(1);
    });

    it('should set type filter', () => {
      vi.mocked(documentRepository.findAll).mockResolvedValue({
        data: [],
        meta: { currentPage: 1, perPage: 10, total: 0, lastPage: 1 },
      });

      act(() => {
        useDocumentsStore.getState().setTypeFilter(2);
      });

      expect(useDocumentsStore.getState().typeFilter).toBe(2);
    });
  });

  describe('document types', () => {
    it('should fetch document types', async () => {
      const mockTypes = [
        { id: 1, name: 'Boleta' },
        { id: 2, name: 'Contrato' },
      ];

      vi.mocked(documentRepository.getDocumentTypes).mockResolvedValueOnce(mockTypes as any);

      await act(async () => {
        await useDocumentsStore.getState().fetchDocumentTypes();
      });

      const state = useDocumentsStore.getState();
      expect(state.documentTypes).toHaveLength(2);
      expect(state.typesLoading).toBe(false);
    });
  });

  describe('orphans', () => {
    it('should fetch orphan documents', async () => {
      const mockOrphans = {
        data: [{ ...mockDocument, user_id: null }],
        meta: { currentPage: 1, perPage: 10, total: 1, lastPage: 1 },
      };

      vi.mocked(documentRepository.getOrphans).mockResolvedValueOnce(mockOrphans as any);

      await act(async () => {
        await useDocumentsStore.getState().fetchOrphans();
      });

      const state = useDocumentsStore.getState();
      expect(state.orphans).toHaveLength(1);
      expect(state.orphansLoading).toBe(false);
    });

    it('should assign orphan document', async () => {
      // Use numeric ID to match how the store filters
      const orphan = { ...mockDocument, id: 1, user_id: null } as any;
      useDocumentsStore.setState({ orphans: [orphan] });

      vi.mocked(documentRepository.assignOrphan).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useDocumentsStore.getState().assignOrphan(1, 10);
      });

      const state = useDocumentsStore.getState();
      expect(state.orphans).toHaveLength(0);
    });
  });

  describe('signature', () => {
    it('should check signature terms', async () => {
      vi.mocked(documentRepository.checkSignatureTerms).mockResolvedValueOnce({
        accepted: true,
        acceptedAt: '2024-01-01',
      });

      await act(async () => {
        await useDocumentsStore.getState().checkSignatureTerms();
      });

      expect(useDocumentsStore.getState().signatureTermsAccepted).toBe(true);
    });

    it('should accept signature terms', async () => {
      vi.mocked(documentRepository.acceptSignatureTerms).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useDocumentsStore.getState().acceptSignatureTerms();
      });

      expect(useDocumentsStore.getState().signatureTermsAccepted).toBe(true);
      expect(useDocumentsStore.getState().signatureLoading).toBe(false);
    });

    it('should sign document', async () => {
      useDocumentsStore.setState({
        currentDocument: mockDocument as any,
        documents: [mockDocument as any],
      });

      vi.mocked(documentRepository.signDocument).mockResolvedValueOnce(undefined);

      await act(async () => {
        await useDocumentsStore.getState().signDocument(Number(mockDocument.id), '123456');
      });

      const state = useDocumentsStore.getState();
      expect(state.currentDocument?.status).toBe('signed');
      expect(state.signatureLoading).toBe(false);
    });
  });

  describe('utility actions', () => {
    it('should clear error', () => {
      useDocumentsStore.setState({ error: 'Some error' });

      act(() => {
        useDocumentsStore.getState().clearError();
      });

      expect(useDocumentsStore.getState().error).toBeNull();
    });

    it('should reset store to initial state', () => {
      useDocumentsStore.setState({
        documents: [mockDocument as any],
        searchTerm: 'test',
        page: 5,
      });

      act(() => {
        useDocumentsStore.getState().reset();
      });

      const state = useDocumentsStore.getState();
      expect(state.documents).toEqual([]);
      expect(state.searchTerm).toBe('');
      expect(state.page).toBe(1);
    });
  });
});
