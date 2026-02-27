import { describe, it, expect, beforeEach, vi } from 'vitest';
import { act } from '@testing-library/react';
import { useTenantFilterStore } from '../tenantFilterStore';

/**
 * Tests for Issue 2: TenantFilterStore fixes.
 * - Non-root users in 'all' mode should have their tenant IDs set
 * - Root users in 'all' mode should have empty IDs (sees everything)
 * - clearFilter() and selectAll() behave correctly per role
 */
describe('tenantFilterStore', () => {
  beforeEach(() => {
    // Reset store to initial state
    useTenantFilterStore.setState({
      filter: { mode: 'all', tenantIds: [], tenants: [] },
    });
    // Clear localStorage mocks
    localStorage.clear();
  });

  describe('initial state', () => {
    it('should have mode "all" initially', () => {
      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
    });

    it('should have empty tenantIds initially', () => {
      const state = useTenantFilterStore.getState();
      expect(state.filter.tenantIds).toEqual([]);
    });
  });

  describe('setFilter', () => {
    it('should set single tenant filter', () => {
      const tenants = [
        { id: 1, name: 'Tenant A', slug: 'tenant-a', is_primary: true },
      ];

      act(() => {
        useTenantFilterStore.getState().setFilter(['1'], tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('single');
      expect(state.filter.tenantIds).toEqual(['1']);
      expect(state.filter.tenants).toHaveLength(1);
    });

    it('should set multiple tenant filter', () => {
      const tenants = [
        { id: 1, name: 'Tenant A', slug: 'tenant-a', is_primary: true },
        { id: 2, name: 'Tenant B', slug: 'tenant-b', is_primary: false },
      ];

      act(() => {
        useTenantFilterStore.getState().setFilter(['1', '2'], tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('selected');
      expect(state.filter.tenantIds).toEqual(['1', '2']);
    });

    it('should set mode to all when no IDs provided', () => {
      const tenants: any[] = [];

      act(() => {
        useTenantFilterStore.getState().setFilter([], tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
    });
  });

  describe('clearFilter - Issue 2 fix', () => {
    it('should clear filter for root user (empty IDs)', () => {
      // Set up root user in localStorage
      localStorage.setItem('auth-storage', JSON.stringify({
        state: { user: { role: 'root', tenants: [] } },
      }));

      act(() => {
        useTenantFilterStore.getState().clearFilter();
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
      expect(state.filter.tenantIds).toEqual([]);
    });

    it('should set tenant IDs for non-root user when clearing', () => {
      // Set up admin user in localStorage with tenants
      localStorage.setItem('auth-storage', JSON.stringify({
        state: {
          user: {
            role: 'admin',
            tenants: [
              { id: 1, name: 'IPDA PERU', slug: 'ipda' },
              { id: 2, name: 'Otra', slug: 'otra' },
            ],
          },
        },
      }));

      act(() => {
        useTenantFilterStore.getState().clearFilter();
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
      // Non-root users should have their tenant IDs populated
      expect(state.filter.tenantIds).toEqual(['1', '2']);
      expect(state.filter.tenants).toHaveLength(2);
    });
  });

  describe('selectAll - Issue 2 fix', () => {
    it('should select all with empty IDs for root user', () => {
      localStorage.setItem('auth-storage', JSON.stringify({
        state: { user: { role: 'root' } },
      }));

      const tenants = [
        { id: 1, name: 'Tenant A', slug: 'a', is_primary: true },
        { id: 2, name: 'Tenant B', slug: 'b', is_primary: false },
      ];

      act(() => {
        useTenantFilterStore.getState().selectAll(tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
      expect(state.filter.tenantIds).toEqual([]);
    });

    it('should select all with IDs for non-root user', () => {
      localStorage.setItem('auth-storage', JSON.stringify({
        state: { user: { role: 'admin' } },
      }));

      const tenants = [
        { id: 1, name: 'Tenant A', slug: 'a', is_primary: true },
        { id: 2, name: 'Tenant B', slug: 'b', is_primary: false },
      ];

      act(() => {
        useTenantFilterStore.getState().selectAll(tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.mode).toBe('all');
      // Non-root should have actual IDs
      expect(state.filter.tenantIds).toEqual(['1', '2']);
    });
  });

  describe('selectors', () => {
    it('getFilteredTenantIds returns undefined for all mode', () => {
      const ids = useTenantFilterStore.getState().getFilteredTenantIds();
      expect(ids).toBeUndefined();
    });

    it('getFilteredTenantIds returns numbers for filtered mode', () => {
      act(() => {
        useTenantFilterStore.getState().setFilter(
          ['1', '2'],
          [
            { id: 1, name: 'A', slug: 'a', is_primary: true },
            { id: 2, name: 'B', slug: 'b', is_primary: false },
          ]
        );
      });

      const ids = useTenantFilterStore.getState().getFilteredTenantIds();
      expect(ids).toEqual([1, 2]);
    });

    it('isFiltering returns false for all mode', () => {
      expect(useTenantFilterStore.getState().isFiltering()).toBe(false);
    });

    it('isFiltering returns true for single/selected mode', () => {
      act(() => {
        useTenantFilterStore.getState().setFilter(
          ['1'],
          [{ id: 1, name: 'A', slug: 'a', is_primary: true }]
        );
      });

      expect(useTenantFilterStore.getState().isFiltering()).toBe(true);
    });

    it('getFilterDisplayText shows correct text', () => {
      expect(useTenantFilterStore.getState().getFilterDisplayText()).toBe('Todas las empresas');

      act(() => {
        useTenantFilterStore.getState().setFilter(
          ['1'],
          [{ id: 1, name: 'IPDA PERU', slug: 'ipda', is_primary: true }]
        );
      });

      expect(useTenantFilterStore.getState().getFilterDisplayText()).toBe('IPDA PERU');
    });
  });

  describe('toggleTenant', () => {
    it('should add tenant when not selected', () => {
      const tenants = [
        { id: 1, name: 'A', slug: 'a', is_primary: true },
        { id: 2, name: 'B', slug: 'b', is_primary: false },
      ];

      act(() => {
        useTenantFilterStore.getState().setFilter(['1'], tenants);
      });

      act(() => {
        useTenantFilterStore.getState().toggleTenant('2', tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.tenantIds).toContain('1');
      expect(state.filter.tenantIds).toContain('2');
    });

    it('should remove tenant when already selected', () => {
      const tenants = [
        { id: 1, name: 'A', slug: 'a', is_primary: true },
        { id: 2, name: 'B', slug: 'b', is_primary: false },
      ];

      act(() => {
        useTenantFilterStore.getState().setFilter(['1', '2'], tenants);
      });

      act(() => {
        useTenantFilterStore.getState().toggleTenant('2', tenants);
      });

      const state = useTenantFilterStore.getState();
      expect(state.filter.tenantIds).toContain('1');
      expect(state.filter.tenantIds).not.toContain('2');
    });
  });
});
