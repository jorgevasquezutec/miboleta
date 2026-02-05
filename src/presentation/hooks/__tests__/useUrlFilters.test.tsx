import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { useUrlFilters } from '../useUrlFilters';
import type { ReactNode } from 'react';

// Wrapper component with router
function createWrapper(initialEntries: string[] = ['/']) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <MemoryRouter initialEntries={initialEntries}>
        {children}
      </MemoryRouter>
    );
  };
}

describe('useUrlFilters', () => {
  const defaultValues = {
    search: '',
    status: 'all',
    page: 1,
    active: false,
  };

  describe('initial state', () => {
    it('should return default values when no URL params', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/']) }
      );

      expect(result.current.filters).toEqual(defaultValues);
    });

    it('should parse string params from URL', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?search=test&status=active']) }
      );

      expect(result.current.filters.search).toBe('test');
      expect(result.current.filters.status).toBe('active');
    });

    it('should parse number params from URL', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?page=5']) }
      );

      expect(result.current.filters.page).toBe(5);
    });

    it('should parse boolean params from URL', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?active=true']) }
      );

      expect(result.current.filters.active).toBe(true);
    });

    it('should keep default for invalid number params', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?page=invalid']) }
      );

      expect(result.current.filters.page).toBe(1);
    });
  });

  describe('setFilter', () => {
    it('should update single filter', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/']) }
      );

      act(() => {
        result.current.setFilter('search', 'hello');
      });

      expect(result.current.filters.search).toBe('hello');
    });

    it('should update number filter', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/']) }
      );

      act(() => {
        result.current.setFilter('page', 3);
      });

      expect(result.current.filters.page).toBe(3);
    });

    it('should update boolean filter', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/']) }
      );

      act(() => {
        result.current.setFilter('active', true);
      });

      expect(result.current.filters.active).toBe(true);
    });

    it('should remove param when set to default value', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?search=test']) }
      );

      expect(result.current.filters.search).toBe('test');

      act(() => {
        result.current.setFilter('search', '');
      });

      expect(result.current.filters.search).toBe('');
    });
  });

  describe('setFilters', () => {
    it('should update multiple filters at once', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/']) }
      );

      act(() => {
        result.current.setFilters({
          search: 'query',
          status: 'pending',
          page: 2,
        });
      });

      expect(result.current.filters.search).toBe('query');
      expect(result.current.filters.status).toBe('pending');
      expect(result.current.filters.page).toBe(2);
    });

    it('should remove params for empty values', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?search=test&status=active']) }
      );

      act(() => {
        result.current.setFilters({
          search: '',
          status: 'pending',
        });
      });

      expect(result.current.filters.search).toBe('');
      expect(result.current.filters.status).toBe('pending');
    });
  });

  describe('resetFilters', () => {
    it('should reset all filters to defaults', () => {
      const { result } = renderHook(
        () => useUrlFilters({ defaultValues }),
        { wrapper: createWrapper(['/?search=test&status=active&page=5']) }
      );

      expect(result.current.filters.search).toBe('test');

      act(() => {
        result.current.resetFilters();
      });

      expect(result.current.filters).toEqual(defaultValues);
    });
  });

  describe('custom parseValue', () => {
    it('should use custom parser for specific keys', () => {
      const customDefaults = { tags: [] as string[] };

      const { result } = renderHook(
        () =>
          useUrlFilters({
            defaultValues: customDefaults,
            parseValue: (key, value) => {
              if (key === 'tags') {
                return value.split(',');
              }
              return value;
            },
          }),
        { wrapper: createWrapper(['/?tags=a,b,c']) }
      );

      expect(result.current.filters.tags).toEqual(['a', 'b', 'c']);
    });
  });

  describe('custom serializeValue', () => {
    it('should use custom serializer for specific keys', () => {
      const customDefaults = { tags: [] as string[] };

      const { result } = renderHook(
        () =>
          useUrlFilters({
            defaultValues: customDefaults,
            parseValue: (key, value) => {
              if (key === 'tags') return value.split(',');
              return value;
            },
            serializeValue: (key, value) => {
              if (key === 'tags' && Array.isArray(value)) {
                return value.join(',');
              }
              return String(value);
            },
          }),
        { wrapper: createWrapper(['/']) }
      );

      act(() => {
        result.current.setFilter('tags', ['x', 'y', 'z']);
      });

      expect(result.current.filters.tags).toEqual(['x', 'y', 'z']);
    });
  });
});
