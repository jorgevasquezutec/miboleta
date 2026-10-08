import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
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
    getSignatureStatus: vi.fn(),
    signDigital: vi.fn(),
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

    it('should sign document and reload the document from the API', async () => {
      const before = { ...mockDocument, id: 1, status: 'pending', digitalSignatureStatus: null } as any;
      const after = { ...before, status: 'signed', signedAt: 'x', digitalSignatureStatus: 'pending' } as any;
      useDocumentsStore.setState({ currentDocument: before, documents: [before] });

      vi.mocked(documentRepository.signDocument).mockResolvedValueOnce(undefined as any);
      vi.mocked(documentRepository.findById).mockResolvedValueOnce(after);

      await act(async () => {
        await useDocumentsStore.getState().signDocument(1, '123456');
      });

      const state = useDocumentsStore.getState();
      expect(documentRepository.findById).toHaveBeenCalledWith(1);
      expect(state.currentDocument?.status).toBe('signed');
      expect(state.currentDocument?.digitalSignatureStatus).toBe('pending');
      expect(state.documents[0].status).toBe('signed');
      expect(state.signatureLoading).toBe(false);
    });

    it('signDocument no falla si la recarga falla (la conformidad ya se registró)', async () => {
      vi.mocked(documentRepository.signDocument).mockResolvedValueOnce(undefined as any);
      vi.mocked(documentRepository.findById).mockRejectedValueOnce(new Error('net'));
      vi.spyOn(console, 'error').mockImplementation(() => {});

      await act(async () => {
        await useDocumentsStore.getState().signDocument(1, '123456');
      });
      expect(useDocumentsStore.getState().error).toBeNull();
    });
  });

  describe('pollDigitalSignature', () => {
    beforeEach(() => {
      vi.useFakeTimers();
    });
    afterEach(() => {
      vi.useRealTimers();
    });

    const status = (s: string | null) => ({
      documentId: 1, requiresSignature: true, isSigned: true, signedAt: 'x', signature: {},
      digitalSignatureStatus: s as any, digitallySignedAt: null, includesConformity: false,
    });

    it('usa getSignatureStatus y se detiene en signed, recargando el documento', async () => {
      vi.mocked(documentRepository.getSignatureStatus)
        .mockResolvedValueOnce(status('pending'))
        .mockResolvedValueOnce(status('signed'));
      vi.mocked(documentRepository.findById).mockResolvedValue({ ...mockDocument, id: 1 } as any);

      const p = useDocumentsStore.getState().pollDigitalSignature(1, { intervalMs: 3000, timeoutMs: 120000 });
      await vi.advanceTimersByTimeAsync(3000);
      expect(documentRepository.findById).not.toHaveBeenCalled();
      await vi.advanceTimersByTimeAsync(3000);

      await expect(p).resolves.toBe('signed');
      expect(documentRepository.getSignatureStatus).toHaveBeenCalledTimes(2);
      expect(documentRepository.findById).toHaveBeenCalledTimes(1);
    });

    it('se detiene en failed', async () => {
      vi.mocked(documentRepository.getSignatureStatus).mockResolvedValueOnce(status('failed'));
      vi.mocked(documentRepository.findById).mockResolvedValue({ ...mockDocument, id: 1 } as any);

      const p = useDocumentsStore.getState().pollDigitalSignature(1);
      await vi.advanceTimersByTimeAsync(3000);
      await expect(p).resolves.toBe('failed');
    });

    it('devuelve none (sin toast de éxito) cuando el estado pasa a null', async () => {
      vi.mocked(documentRepository.getSignatureStatus).mockResolvedValueOnce(status(null));
      vi.mocked(documentRepository.findById).mockResolvedValue({ ...mockDocument, id: 1 } as any);

      const p = useDocumentsStore.getState().pollDigitalSignature(1);
      await vi.advanceTimersByTimeAsync(3000);
      await expect(p).resolves.toBe('none');
      expect(documentRepository.findById).toHaveBeenCalledTimes(1);
    });

    it('devuelve timeout al agotar el tiempo', async () => {
      vi.mocked(documentRepository.getSignatureStatus).mockResolvedValue(status('pending'));

      const p = useDocumentsStore.getState().pollDigitalSignature(1, { intervalMs: 3000, timeoutMs: 9000 });
      await vi.advanceTimersByTimeAsync(10000);

      await expect(p).resolves.toBe('timeout');
      expect(documentRepository.findById).not.toHaveBeenCalled();
    });

    it('se puede cancelar con AbortSignal', async () => {
      vi.mocked(documentRepository.getSignatureStatus).mockResolvedValue(status('pending'));
      const ctrl = new AbortController();

      const p = useDocumentsStore.getState().pollDigitalSignature(1, { signal: ctrl.signal });
      ctrl.abort();
      await vi.advanceTimersByTimeAsync(3000);

      await expect(p).resolves.toBe('cancelled');
      expect(documentRepository.getSignatureStatus).not.toHaveBeenCalled();
    });
  });

  describe('retryDigitalSignature', () => {
    it('llama a signDigital y recarga', async () => {
      vi.mocked(documentRepository.signDigital).mockResolvedValueOnce(undefined);
      vi.mocked(documentRepository.findById).mockResolvedValueOnce({ ...mockDocument, id: 1 } as any);
      await useDocumentsStore.getState().retryDigitalSignature(1);
      expect(documentRepository.signDigital).toHaveBeenCalledWith(1);
      expect(documentRepository.findById).toHaveBeenCalledWith(1);
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
