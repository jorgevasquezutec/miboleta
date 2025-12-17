# 🔄 Guía de Migración de Páginas a Multi-Tenant

## ⚠️ Estado Actual

**El TenantMultiSwitcher funciona** pero las páginas **NO reaccionan** al cambio porque aún usan el código antiguo:

```typescript
// ❌ CÓDIGO ANTIGUO (no reacciona al switcher)
const { currentTenant } = useAuthStore();
const tenantId = currentTenant?.id;

useEffect(() => {
  fetchData(tenantId);
}, [tenantId]);
```

---

## ✅ Solución: Migrar a `useTenantFilteredData`

### **Ventajas**:
1. ✅ Reacciona automáticamente al cambio de filtro
2. ✅ Cache automático de React Query
3. ✅ Loading states gratis
4. ✅ Retry automático en errores
5. ✅ Refetch inteligente

---

## 📝 Ejemplo: DashboardPage

### **ANTES** (Código actual - 437 líneas):

```typescript
import { useEffect, useState } from "react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useReportsStore } from "@/presentation/stores/reportsStore";

export function AdminDashboardView() {
  const { currentTenant, user } = useAuthStore();
  const {
    documentStats,
    vacationStats,
    userStats,
    isLoadingDashboard,
    fetchDashboardStats,
  } = useReportsStore();

  const [selectedTenantId, setSelectedTenantId] = useState<string | null>(null);
  const [dateRange, setDateRange] = useState<DateRange>({
    from: subDays(new Date(), 30),
    to: new Date(),
  });

  const isRoot = user?.role === 'root';
  
  // ❌ Solo funciona con currentTenant
  const tenantId = isRoot
    ? (selectedTenantId ? Number(selectedTenantId) : undefined)
    : (currentTenant?.id ? Number(currentTenant.id) : undefined);

  // ❌ useEffect manual
  useEffect(() => {
    if (dateRange.from && dateRange.to) {
      const startDate = format(dateRange.from, 'yyyy-MM-dd');
      const endDate = format(dateRange.to, 'yyyy-MM-dd');
      fetchDashboardStats(tenantId, startDate, endDate);
    }
  }, [tenantId, dateRange, fetchDashboardStats]);

  return (
    <div>
      {isLoadingDashboard ? (
        <Skeleton />
      ) : (
        <StatsCard stats={documentStats} />
      )}
    </div>
  );
}
```

### **DESPUÉS** (Con useTenantFilteredData - Simplificado):

```typescript
import { useTenantFilteredData } from "@/presentation/hooks";
import { dashboardRepository } from "@/infrastructure/repositories";
import { format, subDays } from "date-fns";
import { useState } from "react";

export function AdminDashboardView() {
  const [dateRange, setDateRange] = useState<DateRange>({
    from: subDays(new Date(), 30),
    to: new Date(),
  });

  // ✅ NUEVO: Hook optimizado que reacciona al filtro automáticamente
  const { data: stats, isLoading } = useTenantFilteredData({
    queryKey: ['dashboard', 'stats', dateRange],
    queryFn: (tenantIds) => dashboardRepository.getStats({
      tenantIds, // ✅ Se pasan automáticamente
      startDate: format(dateRange.from, 'yyyy-MM-dd'),
      endDate: format(dateRange.to, 'yyyy-MM-dd'),
    }),
  });

  return (
    <div>
      {isLoading ? (
        <Skeleton />
      ) : (
        <>
          <StatsCard stats={stats?.documentStats} />
          <StatsCard stats={stats?.vacationStats} />
          <StatsCard stats={stats?.userStats} />
        </>
      )}
    </div>
  );
}
```

### **Cambios Clave**:
1. ❌ Eliminar `useAuthStore` para tenantId
2. ❌ Eliminar `useState` para selectedTenantId (root users)
3. ❌ Eliminar `useEffect` manual
4. ❌ Eliminar `useReportsStore` (usar React Query)
5. ✅ Añadir `useTenantFilteredData`
6. ✅ Simplificar lógica a ~50% menos código

---

## 📝 Ejemplo 2: VacationHistoryPage

### **ANTES**:

```typescript
export function VacationHistoryPage() {
  const { currentTenant } = useAuthStore();
  const { historyRequests, isLoading, fetchHistoryRequests } = useVacationsStore();
  const [filters, setFilters] = useState({ status: 'all' });

  const tenantId = currentTenant?.id ? Number(currentTenant.id) : undefined;

  useEffect(() => {
    fetchHistoryRequests({
      tenant_id: tenantId,
      status: filters.status,
    });
  }, [tenantId, filters]);

  return <div>{/* UI */}</div>;
}
```

### **DESPUÉS**:

```typescript
export function VacationHistoryPage() {
  const [filters, setFilters] = useState({ status: 'all' });

  // ✅ React Query + Tenant Filter
  const { data: requests, isLoading } = useTenantFilteredData({
    queryKey: ['vacation-history', filters],
    queryFn: (tenantIds) => vacationRepository.getHistory({
      tenantIds,
      status: filters.status,
    }),
  });

  return <div>{/* UI */}</div>;
}
```

---

## 🔄 Patrón de Migración General

### **Paso 1: Identificar el fetch actual**
```typescript
// Busca este patrón:
useEffect(() => {
  fetch*(...tenantId...);
}, [tenantId, ...]);
```

### **Paso 2: Reemplazar con useTenantFilteredData**
```typescript
const { data, isLoading } = useTenantFilteredData({
  queryKey: ['unique-key', ...dependencies],
  queryFn: (tenantIds) => repository.method({ tenantIds, ...params }),
});
```

### **Paso 3: Actualizar el repositorio si es necesario**

```typescript
// backend/dashboardRepository.ts

// ❌ ANTES
export const getStats = async (tenantId?: number) => {
  const response = await apiClient.get('/api/dashboard/stats', {
    params: { tenant_id: tenantId },
  });
  return response.data;
};

// ✅ DESPUÉS
export const getStats = async (params: { 
  tenantIds?: number[],
  startDate?: string,
  endDate?: string 
}) => {
  // ✅ apiClient ya añade X-Tenant-Ids header automáticamente
  // No necesitas pasarlo en params
  const response = await apiClient.get('/api/dashboard/stats', {
    params: {
      start_date: params.startDate,
      end_date: params.endDate,
    },
  });
  return response.data;
};
```

---

## 📋 Páginas a Migrar

### **Alta Prioridad** (Usan filtro de tenant):
- [ ] `/dashboard` - DashboardPage.tsx
- [ ] `/admin/vacation-history` - VacationHistoryPage.tsx  
- [ ] `/admin/documents` - DocumentsListPage.tsx (si existe)
- [ ] `/admin/users` - UsersListPage.tsx (si existe)

### **Media Prioridad**:
- [ ] Otras páginas administrativas
- [ ] Reportes

### **Baja Prioridad** (No dependen de tenant):
- [ ] Profile page
- [ ] Settings

---

## ⚡ Beneficios de Migrar

### **Antes de Migrar**:
```
Usuario selecciona empresas 1, 2, 3
   ↓
Nada pasa (página sigue mostrando solo empresa actual)
   ↓
Usuario tiene que refrescar manualmente
```

### **Después de Migrar**:
```
Usuario selecciona empresas 1, 2, 3
   ↓
useTenantFilteredData detecta cambio
   ↓
React Query invalida cache
   ↓
Página refetch automático
   ↓
UI actualizada en <100ms ⚡
```

---

## 🧪 Testing Después de Migrar

### **1. Cambio de Filtro**
```
1. Abre /dashboard
2. Selecciona 1 empresa → Verifica datos
3. Selecciona 2 empresas → Verifica que se actualiza
4. Selecciona "Todas" → Verifica datos completos
```

### **2. Cache Funcionando**
```
1. Abre /dashboard
2. Navega a otra página
3. Vuelve a /dashboard
4. ✅ Debe cargar instantáneamente (desde cache)
```

### **3. React Query DevTools**
```
1. Abre DevTools (bottom-right)
2. Ve queries activas
3. Cambia filtro
4. Ve invalidaciones en tiempo real
```

---

## 🚀 Migración Sugerida

### **Opción A: Migración Gradual** (Recomendada)

```
Día 1: Migrar DashboardPage
   ↓
Día 2: Testing + ajustes
   ↓
Día 3: Migrar VacationHistoryPage
   ↓
Día 4: Testing + ajustes
   ↓
Día 5: Migrar otras páginas
```

**Ventaja**: Baj riesgo, fácil rollback

### **Opción B: Migración Completa** (Más rápida)

```
Día 1: Migrar todas las páginas
   ↓
Día 2-3: Testing intensivo
```

**Ventaja**: Todo listo de una vez

---

## 📝 Template de Migración

Copia y pega este template:

```typescript
// 1. Import el hook
import { useTenantFilteredData } from '@/presentation/hooks';

// 2. En tu componente
export function MyPage() {
  // Mantén tus otros estados (filters, pagination, etc)
  const [filters, setFilters] = useState({...});

  // 3. Reemplaza useEffect + store con useTenantFilteredData
  const { data, isLoading, error, refetch } = useTenantFilteredData({
    queryKey: ['my-page-data', filters], // Incluye dependencies
    queryFn: (tenantIds) => repository.getData({
      tenantIds, // Se inyecta automáticamente
      ...filters,
    }),
    // Opciones extra si necesitas:
    staleTime: 5 * 60 * 1000, // 5 minutos
    enabled: someCondition, // conditional fetching
  });

  // 4. Usa data directamente (no más state local)
  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorMessage />;

  return (
    <div>
      {data?.items.map(item => (
        <ItemCard key={item.id} {...item} />
      ))}
    </div>
  );
}
```

---

## ❓ FAQ

### **¿Tengo que migrar todas las páginas?**
No, pero las que NO migres no reaccionarán al cambio del switcher.

### **¿Qué pasa con el código antiguo?**
Sigue funcionando, pero solo con `currentTenant` (una empresa).

### **¿Puedo usar ambos sistemas temporalmente?**
Sí, son compatibles. Migra gradualmente.

### **¿Cómo sé si una página está migrada?**
Si usa `useTenantFilteredData` → Migrada ✅  
Si usa `useAuthStore().currentTenant` → Antigua ❌

---

## 🎯 Próximo Paso

**¿Quieres que migremos DashboardPage ahora?**

Solo necesito:
1. Confirmar que DashboardRepository existe
2. Modificar DashboardPage.tsx
3. Testing rápido

**Tiempo estimado**: 15-30 minutos

---

**Última actualización**: 16 Diciembre 2025, 19:00  
**Estado**: Guía lista para uso  
**Migración**: 0 páginas migradas, ~5 páginas pendientes
