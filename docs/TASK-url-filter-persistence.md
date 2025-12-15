# Task: Implementar Persistencia de Filtros en URL

## Descripción

Actualmente, los filtros de las tablas se almacenan en estado local del componente. Cuando el usuario navega a un detalle y regresa, el estado del filtro se pierde (el input aparece vacío aunque los datos siguen filtrados).

## Objetivo

Implementar un hook reutilizable `useUrlFilters` que sincronice los filtros con los query params de la URL, permitiendo:
- Persistencia de filtros al navegar
- URLs compartibles con filtros aplicados
- Compatibilidad con el botón back del navegador

## Páginas Afectadas

### Admin
1. **UsersListPage.tsx**
   - Filtros: `searchTerm`, `roleFilter`, `currentPage`, `perPage`
   
2. **TenantsListPage.tsx**
   - Filtros: `searchTerm`, `currentPage`, `perPage`
   
3. **DocumentsListPage.tsx**
   - Filtros: `searchTerm`, `statusFilter`, `typeFilter`, `dateRange`, `currentPage`, `perPage`
   
4. **BatchesListPage.tsx**
   - Filtros: `searchTerm`, `statusFilter`, `dateRange`, `currentPage`, `perPage`
   
5. **AuditLogsPage.tsx**
   - Filtros: `searchTerm`, `actionFilter`, `categoryFilter`, `selectedTenantId`, `currentPage`, `perPage`
   
6. **VacationHistoryPage.tsx**
   - Filtros: `searchTerm`, `statusFilter`, `yearFilter`, `currentPage`, `perPage`

### Employee
7. **VacationRequestsListPage.tsx**
   - Filtros: `statusFilter`, `currentPage`
   
8. **EmployeeDashboardPage.tsx**
   - Filtros: `searchTerm`, `statusFilter`

## Implementación

### Paso 1: Crear el Hook useUrlFilters

```typescript
// src/presentation/hooks/useUrlFilters.ts

import { useSearchParams } from 'react-router-dom';
import { useCallback, useMemo } from 'react';

interface FilterConfig<T> {
  defaultValues: T;
  parseValue?: (key: keyof T, value: string) => T[keyof T];
  serializeValue?: (key: keyof T, value: T[keyof T]) => string;
}

export function useUrlFilters<T extends Record<string, unknown>>({
  defaultValues,
  parseValue,
  serializeValue,
}: FilterConfig<T>) {
  const [searchParams, setSearchParams] = useSearchParams();

  const filters = useMemo(() => {
    const result = { ...defaultValues };
    
    for (const key of Object.keys(defaultValues) as (keyof T)[]) {
      const paramValue = searchParams.get(String(key));
      if (paramValue !== null) {
        if (parseValue) {
          result[key] = parseValue(key, paramValue);
        } else {
          // Default parsing
          const defaultValue = defaultValues[key];
          if (typeof defaultValue === 'number') {
            result[key] = parseInt(paramValue, 10) as T[keyof T];
          } else if (typeof defaultValue === 'boolean') {
            result[key] = (paramValue === 'true') as T[keyof T];
          } else {
            result[key] = paramValue as T[keyof T];
          }
        }
      }
    }
    
    return result;
  }, [searchParams, defaultValues, parseValue]);

  const setFilters = useCallback((updates: Partial<T>) => {
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      
      for (const [key, value] of Object.entries(updates)) {
        if (value === undefined || value === null || value === '' || value === defaultValues[key as keyof T]) {
          newParams.delete(key);
        } else {
          const serialized = serializeValue 
            ? serializeValue(key as keyof T, value as T[keyof T])
            : String(value);
          newParams.set(key, serialized);
        }
      }
      
      return newParams;
    }, { replace: true });
  }, [setSearchParams, defaultValues, serializeValue]);

  const setFilter = useCallback((key: keyof T, value: T[keyof T]) => {
    setFilters({ [key]: value } as Partial<T>);
  }, [setFilters]);

  const resetFilters = useCallback(() => {
    setSearchParams({}, { replace: true });
  }, [setSearchParams]);

  return { filters, setFilter, setFilters, resetFilters };
}
```

### Paso 2: Crear tipos para cada página

```typescript
// Ejemplo de tipos para UsersListPage
interface UsersFilters {
  search: string;
  role: string;
  page: number;
  per_page: number;
}

const defaultUsersFilters: UsersFilters = {
  search: '',
  role: 'all',
  page: 1,
  per_page: 10,
};
```

### Paso 3: Refactorizar cada página

Ejemplo de refactorización para UsersListPage:

**Antes:**
```tsx
const [searchTerm, setSearchTerm] = useState("");
const [roleFilter, setRoleFilter] = useState("all");
const [currentPage, setCurrentPage] = useState(1);
const [perPage, setPerPage] = useState(10);
```

**Después:**
```tsx
const { filters, setFilter, resetFilters } = useUrlFilters({
  defaultValues: {
    search: '',
    role: 'all',
    page: 1,
    per_page: 10,
  }
});

// Uso
<Input 
  value={filters.search}
  onChange={(e) => setFilter('search', e.target.value)}
/>
```

### Paso 4: Actualizar los componentes uno por uno

El orden recomendado es:
1. UsersListPage (más simple)
2. TenantsListPage
3. BatchesListPage
4. VacationHistoryPage
5. AuditLogsPage
6. DocumentsListPage (más complejo, tiene dateRange)
7. VacationRequestsListPage
8. EmployeeDashboardPage

## Consideraciones Especiales

### DateRange en URL
Para campos de fecha, serializar como:
```
?start_date=2025-01-01&end_date=2025-01-31
```

### Tenant ID para Root Users
El `selectedTenantId` también debe persistirse en URL para root users.

### Debounce en búsqueda
Mantener el debounce al escribir, pero actualizar la URL solo después del debounce.

## Pruebas

Para cada página refactorizada, verificar:
1. ✅ Al cambiar filtros, la URL se actualiza
2. ✅ Al recargar la página, los filtros se restauran desde la URL
3. ✅ Al navegar a detalle y volver con back, los filtros persisten
4. ✅ El botón "Limpiar filtros" limpia la URL
5. ✅ Los valores por defecto no aparecen en la URL
6. ✅ La paginación también persiste

## Estimación

- Hook useUrlFilters: 1 hora
- Refactorizar 8 páginas: 3-4 horas
- Pruebas: 1 hora

**Total estimado: 5-6 horas**
