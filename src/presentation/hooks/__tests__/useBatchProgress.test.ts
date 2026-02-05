import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useBatchProgress } from '../useBatchProgress';

// Mock the bulkUserUploadService
vi.mock('@/infrastructure/services/bulkUserUploadService', () => ({
  bulkUserUploadService: {
    getBatch: vi.fn(),
  },
}));

import { bulkUserUploadService } from '@/infrastructure/services/bulkUserUploadService';

describe('useBatchProgress', () => {
  const mockBatch = {
    id: '123',
    status: 'processing',
    is_processing: true,
    is_completed: false,
    progress: 50,
    total_rows: 100,
    processed_rows: 50,
    created_rows: 45,
    failed_rows: 5,
    errors: [],
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
  };

  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should fetch batch on mount', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    const { result } = renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 3000 })
    );

    expect(result.current.isLoading).toBe(true);

    // Allow the initial fetch to complete
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(bulkUserUploadService.getBatch).toHaveBeenCalledWith('123');
    expect(result.current.batch).toEqual(mockBatch);
    expect(result.current.isLoading).toBe(false);
  });

  it('should poll at specified interval', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 1000 })
    );

    // Initial fetch
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(1);

    // After 1 second - should poll again
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(2);

    // After another second
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(3);
  });

  it('should not poll when disabled', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    renderHook(() =>
      useBatchProgress({ id: '123', enabled: false })
    );

    await act(async () => {
      await vi.advanceTimersByTimeAsync(5000);
    });

    expect(bulkUserUploadService.getBatch).not.toHaveBeenCalled();
  });

  it('should stop polling when batch is completed', async () => {
    const completedBatch = {
      ...mockBatch,
      status: 'completed',
      is_completed: true,
      is_processing: false,
    };

    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(completedBatch as any);

    renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 1000 })
    );

    // Initial fetch
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(1);

    // After poll interval - should not poll again (completed)
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2000);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(1);
  });

  it('should call onComplete when batch completes', async () => {
    const onComplete = vi.fn();
    const completedBatch = {
      ...mockBatch,
      status: 'completed',
      is_completed: true,
      is_processing: false,
    };

    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(completedBatch as any);

    renderHook(() =>
      useBatchProgress({ id: '123', onComplete, pollInterval: 1000 })
    );

    // Allow the initial fetch to complete
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(onComplete).toHaveBeenCalledWith(completedBatch);
  });

  it('should call onComplete only once', async () => {
    const onComplete = vi.fn();
    const completedBatch = {
      ...mockBatch,
      status: 'completed',
      is_completed: true,
      is_processing: false,
    };

    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(completedBatch as any);

    const { result } = renderHook(() =>
      useBatchProgress({ id: '123', onComplete, pollInterval: 100 })
    );

    // Multiple fetches
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    // Manual refresh
    await act(async () => {
      result.current.refresh();
      await vi.advanceTimersByTimeAsync(0);
    });

    // onComplete should only be called once
    expect(onComplete).toHaveBeenCalledTimes(1);
  });

  it('should handle errors and call onError', async () => {
    const onError = vi.fn();
    const error = new Error('Network error');

    vi.mocked(bulkUserUploadService.getBatch).mockRejectedValue(error);

    const { result } = renderHook(() =>
      useBatchProgress({ id: '123', onError, pollInterval: 1000 })
    );

    // Allow the initial fetch to complete (and fail)
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.error).toEqual(error);
    expect(onError).toHaveBeenCalledWith(error);
    expect(result.current.isLoading).toBe(false);
  });

  it('should stop polling on error', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockRejectedValue(new Error('Error'));

    renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 1000 })
    );

    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(1);

    // Should not poll again after error
    await act(async () => {
      await vi.advanceTimersByTimeAsync(2000);
    });
    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(1);
  });

  it('should expose isProcessing and isCompleted', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    const { result } = renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 3000 })
    );

    // Allow the initial fetch to complete
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(result.current.isProcessing).toBe(true);
    expect(result.current.isCompleted).toBe(false);
  });

  it('should allow manual refresh', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    const { result } = renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 5000 })
    );

    // Allow the initial fetch to complete
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    const callCount = vi.mocked(bulkUserUploadService.getBatch).mock.calls.length;

    // Call refresh and allow it to complete
    await act(async () => {
      result.current.refresh();
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(callCount + 1);
  });

  it('should cleanup interval on unmount', async () => {
    vi.mocked(bulkUserUploadService.getBatch).mockResolvedValue(mockBatch as any);

    const { unmount } = renderHook(() =>
      useBatchProgress({ id: '123', pollInterval: 1000 })
    );

    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    unmount();

    // After unmount, polling should stop
    const callCount = vi.mocked(bulkUserUploadService.getBatch).mock.calls.length;

    await act(async () => {
      await vi.advanceTimersByTimeAsync(5000);
    });

    expect(bulkUserUploadService.getBatch).toHaveBeenCalledTimes(callCount);
  });
});
