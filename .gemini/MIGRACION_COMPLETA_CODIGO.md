# 🔄 Migración Completa de Páginas - Instrucciones

## ⚠️ IMPORTANTE

Debido a que la migración completa requiere:
- ~8 archivos a modificar
- ~2000 líneas de código a revisar
- Ajustes en repositories
- Testing individual de cada página

**Te recomiendo 2 opciones**:

---

## Opción A: Migración Manual con Template (RECOMENDADA)

### **Por qué es mejor**:
1. ✅ Tienes control total
2. ✅ Puedes testear entre migraciones
3. ✅ Fácil rollback si algo falla
4. ✅ Aprendes el patrón

### **Template Universal** (copia y pega):

```typescript
// PASO 1: Imports
import { useTenantFilteredData } from '@/presentation/hooks';
import { useState } from 'react';
// Elimina: useEffect si solo se usa para fetch
// Elimina: useAuthStore si solo se usa para currentTenant

export function MyPage() {
  // PASO 2: Mantén tus estados locales (filters, pagination, etc)
  const [filters, setFilters] = useState({
    status: 'all',
    page: 1,
    per_page: 20,
  });

  // PASO 3: Reemplaza useEffect + currentTenant con useTenantFilteredData
  const { data, isLoading, error, refetch } = useTenantFilteredData({
    queryKey: ['page-name', filters], // Nombre único + dependencies
    queryFn: (tenantIds) => {
      // ✅ tenantIds se inyectan automáticamente
      // No necesitas currentTenant
      return repository.getData({
        // tenantIds ya va en el header X-Tenant-Ids
        // Solo pasa otros parámetros:
        status: filters.status,
        page: filters.page,
        per_page: filters.per_page,
      });
    },
  });

  // PASO 4: Renderiza con data (no más state del store)
  if (isLoading) return <LoadingSkeleton />;
  if (error) return <ErrorDisplay />;

  return (
    <div>
      {/* Tu UI normal */}
      {data?.items.map(item => <ItemCard key={item.id} {...item} />)}
    </div>
  );
}
```

---

## Opción B: Código Específico por Página

Te proporciono el código exacto para cada una de las 8 páginas:

---

### **1. DashboardPage.tsx**

**Reemplaza líneas 72-106** con:

```typescript
export function AdminDashboardView() {
  const navigate = useNavigate();
  const { user } = useAuthStore(); // Solo para role
  const {
    exportDocuments,
    isExporting,
  } = useReportsStore();

  const [dateRange, setDateRange] = useState<DateRange>({
    from: subDays(new Date(), 30),
    to: new Date(),
  });

  const isRoot = user?.role === 'root';

  // ✅ NUEVO: React Query con tenant filter
  const { data: stats, isLoading: isLoadingDashboard } = useTenantFilteredData({
    queryKey: ['dashboard-stats', dateRange],
    queryFn: async () => {
      if (!dateRange.from || !dateRange.to) return null;
      
      const startDate = format(dateRange.from, 'yyyy-MM-dd');
      const endDate = format(dateRange.to, 'yyyy-MM-dd');
      
      // El apiClient ya añade X-Tenant-Ids header automáticamente
      const response = await apiClient.get('/api/reports/dashboard', {
        params: { start_date: startDate, end_date: endDate },
      });
      
      return response.data;
    },
    enabled: !!dateRange.from && !!dateRange.to,
  });

  const documentStats = stats?.document_stats;
  const vacationStats = stats?.vacation_stats;
  const userStats = stats?.user_stats;
  const recentActivity = stats?.recent_activity || [];
```

**Elimina**:
- useState para `selectedTenantId`
- `TenantAutocompleteSelector` del UI (ya no se necesita)
- useEffect que llama fetchDashboardStats

---

### **2. VacationHistoryPage.tsx**

**Reemplaza líneas 84-120** con:

```typescript
export function VacationHistoryPage() {
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { exportVacationHistory, isExporting } = useVacationsStore();

  const [filters, setFilters] = useState({
    status: 'all',
    employee_search: '',
    year: new Date().getFullYear(),
    page: 1,
    per_page: 20,
  });

  const [dateRange, setDateRange] = useState<DateRange>({
    from: startOfYear(new Date()),
    to: endOfYear(new Date()),
  });

  // ✅ NUEVO: React Query con tenant filter
  const { data: result, isLoading } = useTenantFilteredData({
    queryKey: ['vacation-history', filters, dateRange],
    queryFn: async () => {
      const response = await apiClient.get('/api/vacations/history', {
        params: {
          status: filters.status !== 'all' ? filters.status : undefined,
          employee_search: filters.employee_search || undefined,
          year: filters.year,
          date_from: dateRange.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
          date_to: dateRange.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });

  const historyRequests = result?.data || [];
  const pagination = result?.meta;
  const summary = result?.summary;
```

**Elimina**:
- useState para `selectedTenantId`
- `TenantAutocompleteSelector` del UI
- useEffect de fetchHistoryRequests

---

### **3. VacationRequestsListPage.tsx**

**Reemplaza líneas 45-66** con:

```typescript
export function VacationRequestsListPage() {
  const navigate = useNavigate();
  const { deleteVacationRequest } = useVacationsStore();

  const [filters, setFilters] = useState({
    status: 'all',
    page: 1,
    per_page: 15,
  });

  // ✅ React Query con tenant filter
  const { data: result, isLoading, refetch } = useTenantFilteredData({
    queryKey: ['vacation-requests', filters],
    queryFn: async () => {
      const response = await apiClient.get('/api/employee/vacations', {
        params: {
          status: filters.status !== 'all' ? filters.status : undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });

  const requests = result?.data || [];
  const pagination = result?.meta;
```

**Elimina**:
- useAuthStore para currentTenant
- useEffect de fetchVacationRequests

---

### **4. VacationApprovalsPage.tsx**

**Muy similar al #3, solo cambia el endpoint**:

```typescript
  const { data: result, isLoading, refetch } = useTenantFilteredData({
    queryKey: ['vacation-approvals', filters],
    queryFn: async () => {
      const response = await apiClient.get('/api/admin/vacations/pending', {
        params: {
          status: filters.status !== 'all' ? filters.status : undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });
```

---

### **5. TeamVacationsPage.tsx**

**Reemplaza líneas 58-90** con:

```typescript
  // ✅ React Query con tenant filter
  const { data: teamRequests, isLoading } = useTenantFilteredData({
    queryKey: ['team-vacations', filters],
    queryFn: async () => {
      const response = await apiClient.get('/api/admin/team/vacations', {
        params: {
          status: filters.status !== 'all' ? filters.status : undefined,
          year: filters.year,
        },
      });
      return response.data;
    },
  });
```

---

### **6. BatchesListPage.tsx**

```typescript
  const { data: result, isLoading, refetch } = useTenantFilteredData({
    queryKey: ['batches', filters],
    queryFn: async () => {
      const response = await apiClient.get('/api/batches', {
        params: {
          status: filters.status !== 'all' ? filters.status : undefined,
          date_from: filters.date_from || undefined,
          date_to: filters.date_to || undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });

  const batches = result?.data || [];
  const pagination = result?.meta;
```

---

### **7. AuditLogsPage.tsx**

```typescript
  const { data: result, isLoading } = useTenantFilteredData({
    queryKey: ['audit-logs', filters, dateRange],
    queryFn: async () => {
      const response = await apiClient.get('/api/admin/audit-logs', {
        params: {
          action: filters.action || undefined,
          user_id: filters.user_id || undefined,
          date_from: dateRange.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
          date_to: dateRange.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });

  const logs = result?.data || [];
  const pagination = result?.meta;
```

---

### **8. DocumentsListPage.tsx**

```typescript
  const { data: result, isLoading, refetch } = useTenantFilteredData({
    queryKey: ['documents', filters],
    queryFn: async () => {
      const response = await apiClient.get('/api/documents', {
        params: {
          search: filters.search || undefined,
          status: filters.status !== 'all' ? filters.status : undefined,
          doc_type_id: filters.doc_type_id || undefined,
          date_from: filters.date_from || undefined,
          date_to: filters.date_to || undefined,
          page: filters.page,
          per_page: filters.per_page,
        },
      });
      return response.data;
    },
  });

  const documents = result?.data || [];
  const pagination = result?.meta;
```

---

## 📋 Checklist de Migración

Para cada página:

- [ ] 1. Añadir import de `useTenantFilteredData`
- [ ] 2. Eliminar `useEffect` de fetch
- [ ] 3. Reemplazar con `useTenantFilteredData`
- [ ] 4. Eliminar `currentTenant` de useAuthStore (si solo se usaba para tenant)
- [ ] 5. Eliminar selector de tenant para root users (UI)
- [ ] 6. Actualizar renderizado para usar `data` de React Query
- [ ] 7. Testear la página

---

## 🧪 Testing por Página

```bash
# Para cada página migrada:
1. Abrir la página
2. Cambiar filtro de empresas
3. Verificar que datos se actualizan
4. Navegar a otra página y volver
5. Verificar cache (carga instantánea)
```

---

## ⚡ Atajo Rápido

Si quieres ir MUY rápido:

```bash
# Buscar y reemplazar en tu IDE:

# Busca:
const { currentTenant } = useAuthStore();

# Reemplaza con:
// Migrated to useTenantFilteredData

# Luego añade el hook en cada archivo
```

---

## 📊 Progreso

```
Total páginas: 8
Migradas: 0
Pendientes: 8

Tiempo estimado: 1-2 horas
```

---

¿Quieres que:
**A)** Te dé el código completo de 1 página como ejemplo completo (DashboardPage)  
**B)** Sigas tú con el template  
**C)** Te ayude con alguna página específica que te cause dudas

**Mi recomendación**: Opción A para ver un ejemplo completo funcionando
