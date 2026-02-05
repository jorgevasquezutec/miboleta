import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useDebounce } from '../useDebounce';

describe('useDebounce', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('should return initial value immediately', () => {
    const { result } = renderHook(() => useDebounce('initial', 500));

    expect(result.current).toBe('initial');
  });

  it('should debounce value changes', () => {
    const { result, rerender } = renderHook(
      ({ value }) => useDebounce(value, 500),
      { initialProps: { value: 'initial' } }
    );

    expect(result.current).toBe('initial');

    // Change the value
    rerender({ value: 'changed' });

    // Value should still be initial immediately after change
    expect(result.current).toBe('initial');

    // Fast-forward time by 250ms (half the delay)
    act(() => {
      vi.advanceTimersByTime(250);
    });

    // Value should still be initial
    expect(result.current).toBe('initial');

    // Fast-forward the remaining time
    act(() => {
      vi.advanceTimersByTime(250);
    });

    // Now the value should be updated
    expect(result.current).toBe('changed');
  });

  it('should reset timer on rapid changes', () => {
    const { result, rerender } = renderHook(
      ({ value }) => useDebounce(value, 500),
      { initialProps: { value: 'a' } }
    );

    // Rapid changes
    rerender({ value: 'ab' });
    act(() => {
      vi.advanceTimersByTime(200);
    });

    rerender({ value: 'abc' });
    act(() => {
      vi.advanceTimersByTime(200);
    });

    rerender({ value: 'abcd' });

    // At this point, 400ms have passed total, but timer keeps resetting
    expect(result.current).toBe('a');

    // Wait full delay after last change
    act(() => {
      vi.advanceTimersByTime(500);
    });

    // Now should have final value
    expect(result.current).toBe('abcd');
  });

  it('should use default delay of 300ms', () => {
    const { result, rerender } = renderHook(
      ({ value }) => useDebounce(value),
      { initialProps: { value: 'initial' } }
    );

    rerender({ value: 'changed' });

    // At 200ms, should still be initial
    act(() => {
      vi.advanceTimersByTime(200);
    });
    expect(result.current).toBe('initial');

    // At 300ms, should be changed
    act(() => {
      vi.advanceTimersByTime(100);
    });
    expect(result.current).toBe('changed');
  });

  it('should work with different types', () => {
    // Test with number
    const { result: numResult, rerender: numRerender } = renderHook(
      ({ value }) => useDebounce(value, 100),
      { initialProps: { value: 0 } }
    );

    numRerender({ value: 42 });
    act(() => {
      vi.advanceTimersByTime(100);
    });
    expect(numResult.current).toBe(42);

    // Test with object
    const { result: objResult, rerender: objRerender } = renderHook(
      ({ value }) => useDebounce(value, 100),
      { initialProps: { value: { name: 'a' } } }
    );

    const newObj = { name: 'b' };
    objRerender({ value: newObj });
    act(() => {
      vi.advanceTimersByTime(100);
    });
    expect(objResult.current).toEqual(newObj);
  });

  it('should handle delay change', () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'initial', delay: 500 } }
    );

    rerender({ value: 'changed', delay: 100 });

    // With new shorter delay
    act(() => {
      vi.advanceTimersByTime(100);
    });

    expect(result.current).toBe('changed');
  });
});
