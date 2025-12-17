# 🎯 Guía de Uso: useTenantAwareEffect

## ✅ Hook Universal Creado

**Archivo**: `/src/presentation/hooks/useTenantAwareEffect.ts`

---

## 🚀 ¿Qué hace?

Reemplaza `useEffect` normal con uno que **automáticamente reacciona** cuando el usuario cambia empresas en el TenantMultiSwitcher.

**Ventajas**:
- ✅ Cambio de **1 línea** por página
- ✅ **Automático** - no necesitas pensar en tenant filter
- ✅ **Compatible** - funciona con código existente
- ✅ **Type-safe** - TypeScript completo

---

## 📝 Cómo Usar

### **ANTES** (código actual):
```typescript
import { useEffect } from 'react';

useEffect(() => {
  fetchDocuments(filters);
}, [filters.page, filters.status, currentTenant]);
```

### **DESPUÉS** (con el hook):
```typescript
import { useTenantAwareEffect } from '@/presentation/hooks';

useTenantAwareEffect(() => {
  fetchDocuments(filters);
}, [filters.page, filters.status]);
// ✅ Ya NO necesitas currentTenant
// ✅ Automáticamente reacciona al cambio de tenant
```

---

## 🔄 Migración de Páginas

### **1. DocumentsListPage.tsx**

**Cambio en línea 1**:
```typescript
// Añadir al import
import { useUrlFilters, useTenantAwareEffect } from "@/presentation/hooks";
```

**Cambio en línea 36**:
```typescript
// Eliminar currentTenant (solo si no se usa en UI)
const { user } = useAuthStore(); // Antes: const { user, currentTenant } = ...
```

**Cambio en línea 104-114**:
```typescript
// ANTES:
useEffect(() => {
    fetchDocuments({...});
}, [filters.page, filters.per_page, filters.search, filters.status, filters.doc_type_id, filters.date_from, filters.date_to, fetchDocuments, currentTenant]);

// DESPUÉS:
useTenantAwareEffect(() => {
    fetchDocuments({
        page: filters.page,
        perPage: filters.per_page,
        search: filters.search || undefined,
        status: filters.status !== 'all' ? (filters.status as Document['status']) : undefined,
        docTypeId: filters.doc_type_id ? parseInt(filters.doc_type_id) : undefined,
        dateFrom: filters.date_from || undefined,
        dateTo: filters.date_to || undefined,
    });
}, [filters.page, filters.per_page, filters.search, filters.status, filters.doc_type_id, filters.date_from, filters.date_to, fetchDocuments]);
// ✅ Eliminado currentTenant - ahora es automático
```

---

### **2. DashboardPage.tsx**

**Cambio en línea 1**:
```typescript
import { useState } from "react";
import { useTenantAwareEffect } from "@/presentation/hooks";
```

**Cambio en useEffect (línea ~111)**:
```typescript
// ANTES:
useEffect(() => {
    fetchDashboardStats(tenantId, startDate, endDate);
}, [tenantId, startDate, endDate, fetchDashboardStats]);

// DESPUÉS:
useTenantAwareEffect(() => {
    fetchDashboardStats(undefined, startDate, endDate); // Backend usa headers
}, [startDate, endDate, fetchDashboardStats]);
```

---

### **3. VacationHistoryPage.tsx**

**Cambio en imports**:
```typescript
import { useTenantAwareEffect } from "@/presentation/hooks";
```

**Cambio en useEffect**:
```typescript
// ANTES:
useEffect(() => {
    fetchHistoryRequests({
        tenant_id: tenantId,
        status: filters.status,
        ...
    });
}, [tenantId, filters, ...]);

// DESPUÉS:
useTenantAwareEffect(() => {
    fetchHistoryRequests({
        // tenant_id no es necesario - backend usa headers
        status: filters.status,
        ...
    });
}, [filters, ...]);
```

---

### **4. VacationApprovalsPage.tsx**

```typescript
useTenantAwareEffect(() => {
    fetchPendingApprovals(filters);
}, [filters, fetchPendingApprovals]);
```

---

### **5. TeamVacationsPage.tsx**

```typescript
useTenantAwareEffect(() => {
    fetchTeamVacations(filters);
}, [filters]);
```

---

### **6. BatchesListPage.tsx**

```typescript
useTenantAwareEffect(() => {
    fetchBatches(filters);
}, [filters, fetchBatches]);
```

---

### **7. AuditLogsPage.tsx**

```typescript
useTenantAwareEffect(() => {
    fetchAuditLogs({
        dateRange,
        filters
    });
}, [dateRange, filters, fetchAuditLogs]);
```

---

### **8. VacationRequestsListPage.tsx** (Employee)

```typescript
useTenantAwareEffect(() => {
    fetchVacationRequests(filters);
}, [filters, fetchVacationRequests]);
```

---

## ✅ Checklist de Migración

Para cada página:

- [ ] 1. Añadir import de `useTenantAwareEffect`
- [ ] 2. Cambiar `useEffect` por `useTenantAwareEffect`
- [ ] 3. Eliminar `currentTenant` de las dependencias
- [ ] 4. Eliminar `tenantId` como parámetro (backend usa headers)
- [ ] 5. Testing: Cambiar tenant y verificar que refetch automático

---

## 🧪 Testing

```bash
# Para cada página migrada:
1. Abrir la página
2. Ver datos actuales
3. Cambiar filtro de empresas en el switcher
4. Click "Aplicar"
5. ✅ Verificar que datos se actualizan automáticamente
```

---

## 📊 Progreso de Migración

```
Total páginas: 8

[ ] 1. DashboardPage.tsx
[ ] 2. DocumentsListPage.tsx                    ← EMPEZAR AQUÍ (más simple)
[ ] 3. VacationHistoryPage.tsx
[ ] 4. VacationRequestsListPage.tsx
[ ] 5. VacationApprovalsPage.tsx
[ ] 6. TeamVacationsPage.tsx
[ ] 7. BatchesListPage.tsx
[ ] 8. AuditLogsPage.tsx

Tiempo estimado: ~10 minutos por página = 80 minutos total
```

---

## 💡 Tips

### **Debugging**:
```typescript
useTenantAwareEffect(() => {
    console.log('🔄 Tenant filter changed, refetching...');
    fetchData();
}, [deps]);
```

### **Conditional fetching**:
```typescript
useTenantAwareEffect(() => {
    if (someCondition) {
        fetchData();
    }
}, [someCondition, deps]);
```

### **Cleanup**:
```typescript
useTenantAwareEffect(() => {
    const controller = new AbortController();
    
    fetchData(controller.signal);
    
    return () => controller.abort(); // ✅ Cleanup automático
}, [deps]);
```

---

## 🎯 Beneficios del Hook

| Aspecto | Sin Hook | Con Hook |
|---------|----------|----------|
| **Código** | +3-5 líneas/página | 1 línea cambiada |
| **Mantenibilidad** | Manual | Automático |
| **Errores** | Fácil olvidar tenant | Imposible |
| **Refetch** | Manual | Automático ✅ |
| **Tiempo migración** | 20 min/página | 5 min/página ✅ |

---

## ✅ Resultado Final

Después de migrar las 8 páginas:

```typescript
// Usuario cambia de empresas en el switcher
TenantMultiSwitcher: click "Aplicar"
   ↓
tenantFilterStore.setFilter() actualiza filter.tenantIds
   ↓
useTenantAwareEffect detecta cambio en filter.tenantIds
   ↓
Ejecuta callback automáticamente
   ↓
fetchData() se llama con nuevos headers
   ↓
Backend filtra datos
   ↓
UI actualizada ✅
```

**TODO automático, sin código extra** 🎉

---

## 🚀 Próximo Paso

**Migra DocumentsListPage AHORA** (5-10 minutos):

1. Abre `/src/presentation/pages/admin/DocumentsListPage.tsx`
2. Línea 1: Añade `useTenantAwareEffect` al import
3. Línea 104: Cambia `useEffect` por `useTenantAwareEffect`
4. Línea 114: Elimina `currentTenant` de dependencias
5. Guarda y prueba

**Eso es todo** ✅

---

**Última actualización**: 16 Diciembre 2025, 19:10  
**Estado**: Hook creado y listo para usar  
**Próxima acción**: Migrar primera página (DocumentsListPage)
