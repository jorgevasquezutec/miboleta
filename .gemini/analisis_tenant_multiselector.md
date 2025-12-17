# Análisis: Implementar Selección Múltiple de Tenants (Empresas)

## 📋 Resumen Ejecutivo

Actualmente, el sistema usa `TenantSwitcher` que permite al usuario seleccionar **solo una empresa a la vez**. Esto afecta todos los filtros en las páginas JSX que dependen de `currentTenant` del `authStore`.

**Objetivo**: Permitir que el usuario pueda:
- Ver **todas las empresas** (sin filtro)
- Ver **una sola empresa** (comportamiento actual)
- Ver **múltiples empresas** seleccionadas (por ejemplo, 2 de 3)

---

## 🔍 Arquitectura Actual

### 1. **TenantSwitcher Component**
- **Ubicación**: `/src/presentation/components/shared/TenantSwitcher.tsx`
- **Función**: Dropdown que permite cambiar entre empresas
- **Limitación**: Solo maneja `currentTenant` (un valor)
- **Acción**: Llama a `switchTenant(tenantId)` que actualiza `currentTenant`

```tsx
// Comportamiento actual
const handleTenantSwitch = (tenantId: string) => {
    switchTenant(tenantId);  // Solo puede seleccionar UNA empresa
    window.location.reload(); // Recarga la página
}
```

### 2. **AuthStore State**
- **Ubicación**: `/src/presentation/stores/authStore.ts`
- **Estado actual**:
```tsx
interface AuthState {
  user: User | null;
  currentTenant: TenantAssociation | null;  // ⚠️ Solo UNA empresa
  // ...
}
```

### 3. **API Client - Header Injection**
- **Ubicación**: `/src/infrastructure/http/apiClient.ts` (líneas 59-73)
- **Comportamiento**: Lee `currentTenant.id` del localStorage y lo envía en header `X-Tenant-Id`

```tsx
const currentTenantId = state?.currentTenant?.id;
if (currentTenantId) {
    config.headers['X-Tenant-Id'] = currentTenantId;  // ⚠️ Solo UN ID
}
```

### 4. **Páginas que dependen de currentTenant**

| Página | Uso de currentTenant | Propósito |
|--------|---------------------|-----------|
| `DashboardPage.tsx` | `currentTenant?.id` | Filtrar estadísticas por empresa |
| `VacationHistoryPage.tsx` | `currentTenant?.id` | Filtrar historial de vacaciones |
| `VacationApprovalsPage.tsx` | `currentTenant?.id` | Filtrar aprobaciones |
| `TeamVacationsPage.tsx` | `currentTenant?.id` | Gestión de equipo |
| `DocumentsListPage.tsx` | `currentTenant` | Filtrar documentos |
| `BatchesListPage.tsx` | `currentTenant` | Filtrar lotes |
| `VacationRequestsListPage.tsx` | `currentTenant` | Solicitudes de empleado |
| `AuditLogsPage.tsx` | `currentTenant?.id` | Logs de auditoría |

**Total**: ~13 archivos usan `currentTenant`

---

## 🎯 Cambios Necesarios

### **FASE 1: Modificar el State (AuthStore)**

#### 1.1. Expandir el State para soportar múltiples tenants

**Archivo**: `/src/presentation/stores/authStore.ts`

```tsx
interface AuthState {
  user: User | null;
  
  // ⬇️ NUEVO: Array de tenants seleccionados
  selectedTenants: TenantAssociation[];
  
  // ⬇️ MANTENER por compatibilidad (deprecado)
  currentTenant: TenantAssociation | null;
  
  isLoading: boolean;
  error: string | null;

  // ⬇️ NUEVA ACCIÓN
  setSelectedTenants: (tenantIds: string[]) => void;
  
  // ⬇️ MANTENER (deprecado pero necesario para retrocompatibilidad)
  switchTenant: (tenantId: string) => void;
  
  // ... otras acciones
}
```

#### 1.2. Implementar la nueva lógica

```tsx
export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      currentTenant: null,
      selectedTenants: [], // ⬅️ NUEVO
      isLoading: false,
      error: null,

      // ⬇️ NUEVA: Actualizar tenants seleccionados
      setSelectedTenants: (tenantIds: string[]) => {
        const { user } = get();
        if (!user?.tenants) return;

        const selected = user.tenants.filter(t => 
          tenantIds.includes(String(t.id))
        );

        set({ 
          selectedTenants: selected,
          // Retrocompatibilidad: currentTenant es el primero
          currentTenant: selected[0] || null
        });
      },

      // ⬇️ MODIFICAR: switchTenant ahora usa setSelectedTenants
      switchTenant: (tenantId: string) => {
        const { setSelectedTenants } = get();
        setSelectedTenants([tenantId]); // Solo uno para compatibilidad
      },

      // ... resto de acciones
    }),
    {
      name: "auth-storage",
      partialize: (state) => ({
        user: state.user,
        currentTenant: state.currentTenant,
        selectedTenants: state.selectedTenants, // ⬅️ NUEVO: persistir
      }),
    }
  )
);
```

---

### **FASE 2: Crear Nuevo TenantMultiSwitcher Component**

**Archivo**: `/src/presentation/components/shared/TenantMultiSwitcher.tsx` (NUEVO)

```tsx
import { Building2, Check, ChevronsUpDown } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { Button } from "@/presentation/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { Checkbox } from "@/presentation/components/ui/checkbox";
import { toast } from "sonner";
import { cn } from "@/presentation/components/ui/utils";
import { useState } from "react";

export function TenantMultiSwitcher() {
    const { user, selectedTenants, setSelectedTenants } = useAuthStore();
    const [tempSelection, setTempSelection] = useState<string[]>(
        selectedTenants.map(t => String(t.id))
    );

    const handleToggleTenant = (tenantId: string) => {
        setTempSelection(prev => {
            if (prev.includes(tenantId)) {
                // Deseleccionar
                return prev.filter(id => id !== tenantId);
            } else {
                // Seleccionar
                return [...prev, tenantId];
            }
        });
    };

    const handleSelectAll = () => {
        if (!user?.tenants) return;
        const allIds = user.tenants.map(t => String(t.id));
        setTempSelection(allIds);
    };

    const handleClearAll = () => {
        setTempSelection([]);
    };

    const handleApply = () => {
        setSelectedTenants(tempSelection);
        toast.success(
            tempSelection.length === 0 
                ? "Mostrando todas las empresas" 
                : `${tempSelection.length} empresa(s) seleccionada(s)`
        );
        // Reload para refrescar datos
        setTimeout(() => window.location.reload(), 500);
    };

    // Si no hay tenants, mostrar branding estático
    if (!user?.tenants || user.tenants.length === 0) {
        return <div className="flex items-center gap-2">...</div>;
    }

    const displayText = selectedTenants.length === 0 
        ? "Todas las empresas"
        : selectedTenants.length === 1
        ? selectedTenants[0].name
        : `${selectedTenants.length} empresas`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="w-[220px] justify-between">
                    <div className="flex items-center gap-2">
                        <Building2 className="h-4 w-4 text-blue-600" />
                        <span className="truncate">{displayText}</span>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[260px]" align="start">
                {/* Controles rápidos */}
                <div className="flex justify-between p-2">
                    <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={handleSelectAll}
                    >
                        Todas
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={handleClearAll}
                    >
                        Ninguna
                    </Button>
                </div>
                <DropdownMenuSeparator />

                {/* Lista de tenants */}
                <div className="max-h-[300px] overflow-y-auto">
                    {user.tenants.map(tenant => {
                        const isSelected = tempSelection.includes(String(tenant.id));
                        return (
                            <DropdownMenuItem
                                key={tenant.id}
                                onClick={() => handleToggleTenant(String(tenant.id))}
                                className="cursor-pointer"
                            >
                                <Checkbox 
                                    checked={isSelected} 
                                    className="mr-2"
                                />
                                <div className="flex items-center gap-2 flex-1">
                                    {tenant.logo_url ? (
                                        <img 
                                            src={tenant.logo_url} 
                                            className="h-5 w-5 rounded"
                                            alt={tenant.name}
                                        />
                                    ) : (
                                        <Building2 className="h-4 w-4" />
                                    )}
                                    <span>{tenant.name}</span>
                                </div>
                                {isSelected && <Check className="h-4 w-4 text-blue-600" />}
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <DropdownMenuSeparator />
                <div className="p-2">
                    <Button 
                        onClick={handleApply} 
                        className="w-full"
                        size="sm"
                    >
                        Aplicar ({tempSelection.length})
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

---

### **FASE 3: Modificar API Client**

**Archivo**: `/src/infrastructure/http/apiClient.ts`

#### Cambio en el interceptor de request (líneas 59-73):

```tsx
// ❌ ANTES (solo un tenant)
const currentTenantId = state?.currentTenant?.id;
if (currentTenantId) {
    config.headers['X-Tenant-Id'] = currentTenantId;
}

// ✅ DESPUÉS (múltiples tenants)
const selectedTenants = state?.selectedTenants || [];
if (selectedTenants.length > 0) {
    // Enviar IDs como string separado por comas
    const tenantIds = selectedTenants.map(t => String(t.id)).join(',');
    config.headers['X-Tenant-Ids'] = tenantIds;  // ⬅️ NUEVO header
} else {
    // Si no hay selección, no enviar header (significa "todas")
    // El backend interpretará la ausencia como "sin filtro"
}
```

**⚠️ IMPORTANTE**: Esto requiere cambios en el backend para soportar el header `X-Tenant-Ids`

---

### **FASE 4: Actualizar todas las páginas que usan currentTenant**

Cada página necesita adaptarse para manejar múltiples tenants. Hay 2 enfoques:

#### **Opción A: Las páginas NO cambian (recomendado para empezar)**
- El backend recibe `X-Tenant-Ids` y filtra automáticamente
- Las páginas siguen obteniendo datos ya filtrados
- **Pro**: Menos cambios en frontend
- **Contra**: Requiere cambios extensos en backend

#### **Opción B: Las páginas se adaptan**
Ejemplo en `DashboardPage.tsx`:

```tsx
// ❌ ANTES
const { currentTenant, user } = useAuthStore();
const tenantId = currentTenant?.id ? Number(currentTenant.id) : undefined;

// ✅ DESPUÉS
const { selectedTenants, user } = useAuthStore();
const tenantIds = selectedTenants.length > 0 
    ? selectedTenants.map(t => Number(t.id))
    : undefined;  // undefined = todas las empresas

// Llamada API con múltiples IDs
fetchDashboardStats(tenantIds, startDate, endDate);
```

---

### **FASE 5: Actualizar el Navbar**

**Archivo**: `/src/presentation/components/layout/Navbar.tsx`

```tsx
// ❌ ANTES
import { TenantSwitcher } from "@/presentation/components/shared/TenantSwitcher";

// ✅ DESPUÉS
import { TenantMultiSwitcher } from "@/presentation/components/shared/TenantMultiSwitcher";

// En el JSX:
<TenantMultiSwitcher />
```

---

## 🔧 Cambios en Backend (Requeridos)

### 1. **Actualizar Middleware TenantScope**

**Archivo**: `backend/app/Http/Middleware/TenantScope.php` (estimado)

```php
// ❌ ANTES
$tenantId = $request->header('X-Tenant-Id');
if ($tenantId) {
    $query->where('tenant_id', $tenantId);
}

// ✅ DESPUÉS
$tenantIdsHeader = $request->header('X-Tenant-Ids');
if ($tenantIdsHeader) {
    $tenantIds = explode(',', $tenantIdsHeader);
    $query->whereIn('tenant_id', $tenantIds);  // ⬅️ Filtrar por múltiples
}
// Si no hay header, no filtrar (mostrar todas)
```

### 2. **Actualizar Controladores y Servicios**

Todos los endpoints que filtran por `tenant_id` necesitan soportar arrays:

- `DashboardController`
- `VacationController`
- `DocumentController`
- `AuditLogController`
- Etc.

---

## 📊 Matriz de Impacto

| Componente | Cambio Requerido | Complejidad | Prioridad |
|------------|------------------|-------------|-----------|
| `authStore.ts` | Alto - Añadir `selectedTenants` | Media | Alta |
| `TenantMultiSwitcher.tsx` | Alto - Crear componente nuevo | Media | Alta |
| `apiClient.ts` | Medio - Cambiar header | Baja | Alta |
| `Navbar.tsx` | Bajo - Cambiar import | Baja | Alta |
| Backend Middleware | Alto - Soportar múltiples IDs | Alta | Alta |
| Backend Controllers | Alto - Adaptar queries | Alta | Alta |
| Páginas JSX (13 archivos) | Bajo-Medio - Depende de enfoque | Variable | Media |

---

## 🎯 Plan de Implementación Recomendado

### **Sprint 1: Base + Backend** (Prioridad Alta)
1. ✅ Modificar `authStore` para soportar `selectedTenants`
2. ✅ Crear `TenantMultiSwitcher` component
3. ✅ Actualizar `apiClient` para enviar `X-Tenant-Ids`
4. ⚠️ **Actualizar backend middleware** para soportar múltiples IDs
5. ⚠️ **Actualizar backend controllers** para filtrar con `whereIn`

### **Sprint 2: Testing + Refinamiento**
6. ✅ Reemplazar `TenantSwitcher` por `TenantMultiSwitcher` en Navbar
7. ✅ Probar con 1 empresa seleccionada (compatibilidad)
8. ✅ Probar con múltiples empresas
9. ✅ Probar sin empresas seleccionadas (todas)

### **Sprint 3: Optimizaciones** (Opcional)
10. Actualizar páginas individuales si requieren lógica especial
11. Añadir indicadores visuales en las páginas de cuántas empresas están filtradas
12. Persistir la selección en localStorage

---

## ⚠️ Consideraciones Importantes

### 1. **Rendimiento**
- Filtrar por múltiples empresas puede ser más lento
- Considerar paginación y límites en el backend
- Caché de resultados si es posible

### 2. **Permisos**
- Verificar que el usuario tiene acceso a las empresas seleccionadas
- El backend debe validar que `X-Tenant-Ids` solo contenga IDs permitidos

### 3. **UI/UX**
- Mostrar claramente cuántas empresas están seleccionadas
- Indicador visual en cada página del filtro activo
- Opción de "limpiar filtro" fácilmente accesible

### 4. **Retrocompatibilidad**
- Mantener `currentTenant` por un tiempo para evitar breaking changes
- Migración gradual de las páginas

---

## 📝 Ejemplo de Flujo de Usuario

1. Usuario inicia sesión → Se cargan todas sus empresas
2. Por defecto, `selectedTenants = []` (todas las empresas)
3. Usuario abre `TenantMultiSwitcher` en Navbar
4. Selecciona 2 de 3 empresas disponibles
5. Click en "Aplicar"
6. Estado actualiza: `selectedTenants = [empresa1, empresa2]`
7. `apiClient` envía header: `X-Tenant-Ids: "1,2"`
8. Backend filtra queries con `whereIn('tenant_id', [1, 2])`
9. Todas las páginas muestran datos solo de esas 2 empresas

---

## 🚀 Próximos Pasos

1. **Validar** este análisis con el equipo de backend
2. **Estimar** tiempo de desarrollo para backend
3. **Decidir** si implementar Opción A o B para las páginas
4. **Crear** tickets en el sistema de gestión de proyectos
5. **Priorizar** según impacto en usuarios

---

## 📚 Referencias Técnicas

- Estado actual: `authStore.ts` (líneas 6-21)
- TenantSwitcher: `TenantSwitcher.tsx` (líneas 15-147)
- API Interceptor: `apiClient.ts` (líneas 59-73)
- Ejemplo de uso: `DashboardPage.tsx` (líneas 72-100)
- Ejemplo de uso: `VacationHistoryPage.tsx` (líneas 84-109)

---

**Fecha de análisis**: 2025-12-16  
**Autor**: Equipo de Desarrollo  
**Versión**: 1.0
