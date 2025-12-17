# 💻 Guía de Implementación con Código

## Ejemplos concretos de código para implementar el Multi-Tenant Selector

---

## 1️⃣ FRONTEND: AuthStore (TypeScript)

### Archivo: `/src/presentation/stores/authStore.ts`

```typescript
import { create } from "zustand";
import { persist } from "zustand/middleware";
import { User, TenantAssociation } from "@/core/domain/entities";
import { userRepository } from "@/infrastructure/persistence/repositories";

interface AuthState {
  user: User | null;
  
  // ✅ NUEVO: Array de tenants seleccionados
  selectedTenants: TenantAssociation[];
  
  // ⚠️ MANTENER por retrocompatibilidad (se actualizará automáticamente)
  currentTenant: TenantAssociation | null;
  
  isLoading: boolean;
  error: string | null;

  // Actions
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  me: () => Promise<void>;
  
  // ✅ NUEVA: Cambiar selección de tenants
  setSelectedTenants: (tenantIds: string[]) => void;
  
  // ⚠️ MANTENER: Retrocompatibilidad
  switchTenant: (tenantId: string) => void;
  
  updateProfile: (updates: Partial<User>) => Promise<void>;
  uploadAvatar: (file: File) => Promise<string>;
  deleteAvatar: () => Promise<void>;
  clearError: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      currentTenant: null,
      selectedTenants: [], // ⬅️ NUEVO: Por defecto vacío = todas las empresas
      isLoading: false,
      error: null,

      login: async (email: string, password: string) => {
        set({ isLoading: true, error: null });

        try {
          const response = await userRepository.login(email, password);

          // ✅ NUEVO: Inicializar con tenant primario o vacío
          const primaryTenant = response.user.tenants?.find(t => t.is_primary);
          const initialSelectedTenants = primaryTenant ? [primaryTenant] : [];

          set({
            user: response.user,
            selectedTenants: initialSelectedTenants,
            currentTenant: primaryTenant || response.user.tenants?.[0] || null,
            isLoading: false,
            error: null,
          });
        } catch (error) {
          console.error('[AuthStore] Login failed:', error);
          set({
            error: error instanceof Error ? error.message : "Error al iniciar sesión",
            isLoading: false,
            user: null,
            currentTenant: null,
            selectedTenants: [],
          });
          throw error;
        }
      },

      logout: async () => {
        set({ isLoading: true });

        try {
          await userRepository.logout();
        } catch (error) {
          console.error('Logout error:', error);
        } finally {
          set({
            user: null,
            currentTenant: null,
            selectedTenants: [], // ⬅️ NUEVO: Limpiar selección
            isLoading: false,
            error: null,
          });
          localStorage.removeItem('auth-storage');
        }
      },

      me: async () => {
        set({ isLoading: true, error: null });

        try {
          const user = await userRepository.me();

          // Mantener selección actual si es válida
          const { selectedTenants } = get();
          const validSelection = selectedTenants.filter(st => 
            user.tenants?.some(ut => ut.id === st.id)
          );

          set({
            user,
            selectedTenants: validSelection.length > 0 
              ? validSelection 
              : (user.tenants?.[0] ? [user.tenants[0]] : []),
            currentTenant: validSelection[0] || user.tenants?.[0] || null,
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al obtener usuario",
            isLoading: false,
          });
          throw error;
        }
      },

      // ✅ NUEVA ACCIÓN: Cambiar múltiples tenants
      setSelectedTenants: (tenantIds: string[]) => {
        const { user } = get();
        if (!user || !user.tenants) {
          console.warn('[AuthStore] No user or tenants available');
          return;
        }

        // Filtrar y obtener los objetos completos de los tenants
        const selectedTenants = user.tenants.filter(t => 
          tenantIds.includes(String(t.id))
        );

        console.log(`🏢 [AuthStore] Selected ${selectedTenants.length} tenant(s):`, 
          selectedTenants.map(t => t.name).join(', ') || 'All tenants'
        );

        set({ 
          selectedTenants,
          // Retrocompatibilidad: currentTenant es el primero de la selección
          currentTenant: selectedTenants[0] || null
        });
      },

      // ⚠️ MANTENER: Retrocompatibilidad con código existente
      switchTenant: (tenantId: string) => {
        const { setSelectedTenants } = get();
        // Ahora usa la nueva función internamente
        setSelectedTenants([tenantId]);
      },

      updateProfile: async (updates: Partial<User>) => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          const response = await apiClient.put<{ user: User }>('/profile', updates);
          const updatedUser = response.data.user;

          set({
            user: { ...user, ...updatedUser },
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al actualizar perfil",
            isLoading: false,
          });
          throw error;
        }
      },

      uploadAvatar: async (file: File) => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          const formData = new FormData();
          formData.append('avatar', file);

          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          const response = await apiClient.post('/profile/avatar', formData, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          });

          set({
            user: { ...user, avatar_url: response.data.avatar_url },
            isLoading: false,
          });

          return response.data.avatar_url;
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al subir avatar",
            isLoading: false,
          });
          throw error;
        }
      },

      deleteAvatar: async () => {
        const { user } = get();
        if (!user) throw new Error("No user logged in");

        set({ isLoading: true, error: null });

        try {
          const apiClient = (await import('@/infrastructure/http/apiClient')).default;
          await apiClient.delete('/profile/avatar');

          set({
            user: { ...user, avatar_url: undefined },
            isLoading: false,
          });
        } catch (error) {
          set({
            error: error instanceof Error ? error.message : "Error al eliminar avatar",
            isLoading: false,
          });
          throw error;
        }
      },

      clearError: () => set({ error: null }),
    }),
    {
      name: "auth-storage",
      partialize: (state) => ({
        user: state.user,
        currentTenant: state.currentTenant,
        selectedTenants: state.selectedTenants, // ⬅️ NUEVO: Persistir selección
      }),
    }
  )
);
```

---

## 2️⃣ FRONTEND: TenantMultiSwitcher Component

### Archivo: `/src/presentation/components/shared/TenantMultiSwitcher.tsx` (CREAR NUEVO)

```typescript
import { Building2, Check, ChevronsUpDown, X } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { Button } from "@/presentation/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { Checkbox } from "@/presentation/components/ui/checkbox";
import { Badge } from "@/presentation/components/ui/badge";
import { toast } from "sonner";
import { cn } from "@/presentation/components/ui/utils";
import { useState } from "react";

export function TenantMultiSwitcher() {
    const { user, selectedTenants, setSelectedTenants } = useAuthStore();
    
    // Estado temporal mientras el dropdown está abierto
    const [tempSelection, setTempSelection] = useState<string[]>(
        selectedTenants.map(t => String(t.id))
    );
    const [isOpen, setIsOpen] = useState(false);

    // Si no hay tenants, mostrar branding estático
    if (!user?.tenants || user.tenants.length === 0) {
        return (
            <div className="flex items-center gap-2">
                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                    <Building2 className="h-5 w-5 text-blue-600" />
                </div>
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-gray-900">MiBoleta</span>
                    <span className="text-xs text-gray-500">Sistema de Gestión</span>
                </div>
            </div>
        );
    }

    // Si solo hay 1 tenant, mostrar estático (sin dropdown)
    if (user.tenants.length === 1) {
        const tenant = user.tenants[0];
        return (
            <div className="flex items-center gap-2">
                {tenant.logo_url ? (
                    <img
                        src={tenant.logo_url}
                        alt={tenant.name}
                        className="h-10 w-10 rounded-md object-cover flex-shrink-0"
                    />
                ) : (
                    <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                        <Building2 className="h-5 w-5 text-blue-600" />
                    </div>
                )}
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-gray-900">{tenant.name}</span>
                    <span className="text-xs text-gray-500">{tenant.is_primary ? "Principal" : "Secundario"}</span>
                </div>
            </div>
        );
    }

    // Handlers
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
        const allIds = user.tenants!.map(t => String(t.id));
        setTempSelection(allIds);
    };

    const handleClearAll = () => {
        setTempSelection([]);
    };

    const handleApply = () => {
        setSelectedTenants(tempSelection);
        
        const message = tempSelection.length === 0 
            ? "Mostrando todas las empresas" 
            : tempSelection.length === 1
            ? `Filtrando por: ${user.tenants!.find(t => String(t.id) === tempSelection[0])?.name}`
            : `Filtrando por ${tempSelection.length} empresas`;
        
        toast.success(message);
        setIsOpen(false);
        
        // Reload para refrescar datos con el nuevo filtro
        setTimeout(() => window.location.reload(), 500);
    };

    const handleOpenChange = (open: boolean) => {
        setIsOpen(open);
        if (open) {
            // Resetear selección temporal al abrir
            setTempSelection(selectedTenants.map(t => String(t.id)));
        }
    };

    // Texto a mostrar en el botón
    const displayText = selectedTenants.length === 0 
        ? "Todas las empresas"
        : selectedTenants.length === 1
        ? selectedTenants[0].name
        : `${selectedTenants.length} empresas`;

    const displaySubtext = selectedTenants.length === 0
        ? `${user.tenants.length} disponibles`
        : selectedTenants.length === 1
        ? (selectedTenants[0].is_primary ? "Principal" : "Secundario")
        : "Selección múltiple";

    return (
        <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-[220px] justify-between px-2 h-auto py-2 hover:!bg-gray-50"
                >
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {selectedTenants.length === 1 && selectedTenants[0].logo_url ? (
                            <img
                                src={selectedTenants[0].logo_url}
                                alt={selectedTenants[0].name}
                                className="h-8 w-8 rounded-md object-cover flex-shrink-0"
                            />
                        ) : (
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 flex-shrink-0">
                                <Building2 className="h-4 w-4 !text-blue-600" />
                            </div>
                        )}
                        <div className="flex flex-col items-start min-w-0 flex-1">
                            <span className="text-sm font-medium !text-gray-900 truncate w-full">
                                {displayText}
                            </span>
                            <span className="text-xs !text-gray-500">
                                {displaySubtext}
                            </span>
                        </div>
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 !text-gray-400" />
                </Button>
            </DropdownMenuTrigger>
            
            <DropdownMenuContent
                align="start"
                className="w-[280px] !bg-white !border-gray-200 shadow-xl"
                sideOffset={5}
            >
                {/* Header con controles rápidos */}
                <div className="flex items-center justify-between p-2 border-b border-gray-100">
                    <DropdownMenuLabel className="text-xs text-gray-500 uppercase font-semibold p-0">
                        Organizaciones
                    </DropdownMenuLabel>
                    <div className="flex gap-1">
                        <Button 
                            variant="ghost" 
                            size="sm"
                            onClick={handleSelectAll}
                            className="h-7 text-xs"
                        >
                            Todas
                        </Button>
                        <Button 
                            variant="ghost" 
                            size="sm"
                            onClick={handleClearAll}
                            className="h-7 text-xs"
                        >
                            Ninguna
                        </Button>
                    </div>
                </div>

                {/* Lista de tenants con checkboxes */}
                <div className="max-h-[320px] overflow-y-auto py-1">
                    {user.tenants.map(tenant => {
                        const isSelected = tempSelection.includes(String(tenant.id));
                        const isCurrentlyActive = selectedTenants.some(st => st.id === tenant.id);
                        
                        return (
                            <DropdownMenuItem
                                key={tenant.id}
                                onClick={(e) => {
                                    e.preventDefault();
                                    handleToggleTenant(String(tenant.id));
                                }}
                                className={cn(
                                    "cursor-pointer px-3 py-2.5 focus:bg-blue-50",
                                    isSelected && "bg-blue-50"
                                )}
                            >
                                <div className="flex items-center gap-3 w-full">
                                    <Checkbox 
                                        checked={isSelected}
                                        onCheckedChange={() => handleToggleTenant(String(tenant.id))}
                                        className="shrink-0"
                                    />
                                    
                                    {tenant.logo_url ? (
                                        <img 
                                            src={tenant.logo_url} 
                                            className="h-6 w-6 rounded object-cover shrink-0"
                                            alt={tenant.name}
                                        />
                                    ) : (
                                        <div className="h-6 w-6 rounded bg-gray-100 flex items-center justify-center shrink-0">
                                            <Building2 className="h-3.5 w-3.5 text-gray-400" />
                                        </div>
                                    )}
                                    
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium text-gray-900 truncate">
                                                {tenant.name}
                                            </span>
                                            {tenant.is_primary && (
                                                <Badge className="bg-yellow-100 text-yellow-700 text-xs px-1.5 py-0">
                                                    ★
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-gray-500">RUC: {tenant.ruc}</p>
                                    </div>
                                    
                                    {isSelected && (
                                        <Check className="h-4 w-4 text-blue-600 shrink-0" />
                                    )}
                                </div>
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <DropdownMenuSeparator className="my-1" />
                
                {/* Footer con contador y botón aplicar */}
                <div className="p-2 flex items-center justify-between gap-2">
                    <span className="text-xs text-gray-500">
                        {tempSelection.length === 0 
                            ? "Todas seleccionadas" 
                            : `${tempSelection.length} de ${user.tenants.length}`}
                    </span>
                    <Button 
                        onClick={handleApply}
                        size="sm"
                        className="h-8"
                        disabled={
                            JSON.stringify(tempSelection.sort()) === 
                            JSON.stringify(selectedTenants.map(t => String(t.id)).sort())
                        }
                    >
                        Aplicar
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default TenantMultiSwitcher;
```

---

## 3️⃣ FRONTEND: API Client Interceptor

### Archivo: `/src/infrastructure/http/apiClient.ts` (MODIFICAR líneas 59-73)

```typescript
// Interceptor de Request - Agregar tenant headers, CSRF token y logging
apiClient.interceptors.request.use(
  (config) => {
    // Agregar CSRF token si está disponible
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      config.headers['X-XSRF-TOKEN'] = csrfToken;
    }

    // ✅ NUEVO: Obtener tenants seleccionados del localStorage
    const authStorage = localStorage.getItem('auth-storage');
    if (authStorage) {
      try {
        const { state } = JSON.parse(authStorage);
        const selectedTenants = state?.selectedTenants || [];

        // ✅ NUEVO: Enviar múltiples tenant IDs
        if (selectedTenants.length > 0) {
          // Header con IDs separados por comas: "1,2,3"
          const tenantIds = selectedTenants.map((t: any) => String(t.id)).join(',');
          config.headers['X-Tenant-Ids'] = tenantIds;
          
          console.log(`🏢 [API] Filtering by tenants: ${tenantIds}`);
        } else {
          // Si está vacío, significa "todas las empresas" - no enviar header
          console.log(`🏢 [API] No tenant filter (showing all)`);
        }

        // ⚠️ DEPRECADO: Mantener por retrocompatibilidad con backend legacy
        const currentTenantId = state?.currentTenant?.id;
        if (currentTenantId) {
          config.headers['X-Tenant-Id'] = currentTenantId;
        }
      } catch (error) {
        console.error('Error parsing auth storage:', error);
      }
    }

    return config;
  },
  (error) => {
    console.error('❌ [Request Error]', error);
    return Promise.reject(error);
  }
);
```

---

## 4️⃣ FRONTEND: Actualizar Navbar

### Archivo: `/src/presentation/components/layout/Navbar.tsx` (MODIFICAR)

```typescript
// ❌ ANTES
import { TenantSwitcher } from "@/presentation/components/shared/TenantSwitcher";

// ✅ DESPUÉS
import { TenantMultiSwitcher } from "@/presentation/components/shared/TenantMultiSwitcher";

// ... en el JSX:
<div className="flex items-center gap-4">
    {/* ❌ ANTES */}
    {/* <TenantSwitcher /> */}
    
    {/* ✅ DESPUÉS */}
    <TenantMultiSwitcher />
    
    {/* ... otros componentes */}
</div>
```

---

## 5️⃣ FRONTEND: Export en index.ts

### Archivo: `/src/presentation/components/shared/index.ts` (AÑADIR)

```typescript
// Exportaciones existentes
export { TenantSwitcher } from './TenantSwitcher';
export { TenantAutocompleteSelector } from './TenantAutocompleteSelector';
export { TenantMultiSelector } from './TenantMultiSelector';

// ✅ NUEVO
export { TenantMultiSwitcher } from './TenantMultiSwitcher';
```

---

## 6️⃣ BACKEND: Middleware (PHP/Laravel)

### Archivo: `backend/app/Http/Middleware/TenantScope.php` (MODIFICAR)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ NUEVO: Soportar múltiples tenant IDs
        $tenantIdsHeader = $request->header('X-Tenant-Ids');
        
        if ($tenantIdsHeader) {
            // Header contiene: "1,2,3"
            $tenantIds = array_map('intval', explode(',', $tenantIdsHeader));
            
            Log::info('🏢 [TenantScope] Filtering by tenant IDs', [
                'tenant_ids' => $tenantIds,
                'count' => count($tenantIds)
            ]);
            
            // Guardar en request para uso posterior
            $request->merge(['tenant_ids' => $tenantIds]);
            
            // Aplicar scope global a modelos
            \Illuminate\Database\Eloquent\Builder::macro('tenantScoped', function() use ($tenantIds) {
                return $this->whereIn('tenant_id', $tenantIds);
            });
        } else {
            // ⚠️ RETROCOMPATIBILIDAD: Soportar header legacy para un solo tenant
            $singleTenantId = $request->header('X-Tenant-Id');
            
            if ($singleTenantId) {
                $tenantIds = [intval($singleTenantId)];
                
                Log::info('🏢 [TenantScope] Filtering by single tenant (legacy)', [
                    'tenant_id' => $singleTenantId
                ]);
                
                $request->merge(['tenant_ids' => $tenantIds]);
                
                \Illuminate\Database\Eloquent\Builder::macro('tenantScoped', function() use ($tenantIds) {
                    return $this->whereIn('tenant_id', $tenantIds);
                });
            } else {
                // Sin headers = sin filtro (todas las empresas para root)
                Log::info('🏢 [TenantScope] No tenant filter (all tenants)');
                
                \Illuminate\Database\Eloquent\Builder::macro('tenantScoped', function() {
                    return $this; // No aplicar filtro
                });
            }
        }

        return $next($request);
    }
}
```

---

## 7️⃣ BACKEND: Ejemplo de uso en Controller

### Archivo: `backend/app/Http/Controllers/DashboardController.php` (EJEMPLO)

```php
<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        // ✅ Obtener tenant IDs del request (ya procesados por middleware)
        $tenantIds = $request->input('tenant_ids');
        
        $query = Document::query();
        
        // ✅ Aplicar filtro de tenants si existen
        if (!empty($tenantIds)) {
            $query->whereIn('tenant_id', $tenantIds);
        }
        
        // También filtrar por fechas si se proporcionan
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }
        
        $stats = [
            'total' => $query->count(),
            'signed' => (clone $query)->where('status', 'signed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
        ];
        
        // ✅ Stats de usuarios (también con filtro)
        $usersQuery = User::query();
        if (!empty($tenantIds)) {
            $usersQuery->whereHas('tenants', function($q) use ($tenantIds) {
                $q->whereIn('tenant_id', $tenantIds);
            });
        }
        
        $userStats = [
            'active' => (clone $usersQuery)->where('status', 'active')->count(),
            'total' => $usersQuery->count(),
        ];
        
        // ✅ Stats de vacaciones
        $vacationsQuery = VacationRequest::query();
        if (!empty($tenantIds)) {
            $vacationsQuery->whereHas('user.tenants', function($q) use ($tenantIds) {
                $q->whereIn('tenant_id', $tenantIds);
            });
        }
        
        $vacationStats = [
            'pending' => (clone $vacationsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $vacationsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $vacationsQuery)->where('status', 'rejected')->count(),
        ];
        
        return response()->json([
            'documents' => $stats,
            'users' => $userStats,
            'vacations' => $vacationStats,
            'filtered_by_tenants' => $tenantIds,
            'tenant_count' => $tenantIds ? count($tenantIds) : 'all',
        ]);
    }
}
```

---

## 8️⃣ TESTING: Casos de Prueba

### Test unitario para AuthStore

```typescript
// test/stores/authStore.test.ts

import { renderHook, act } from '@testing-library/react';
import { useAuthStore } from '@/presentation/stores/authStore';

describe('AuthStore - Multi Tenant', () => {
  
  it('should initialize with empty selectedTenants', () => {
    const { result } = renderHook(() => useAuthStore());
    expect(result.current.selectedTenants).toEqual([]);
  });

  it('should select multiple tenants', () => {
    const { result } = renderHook(() => useAuthStore());
    
    // Mock user with tenants
    act(() => {
      result.current.setUser({
        id: 1,
        tenants: [
          { id: '1', name: 'Tenant A' },
          { id: '2', name: 'Tenant B' },
          { id: '3', name: 'Tenant C' },
        ]
      });
    });

    // Select 2 tenants
    act(() => {
      result.current.setSelectedTenants(['1', '2']);
    });

    expect(result.current.selectedTenants).toHaveLength(2);
    expect(result.current.selectedTenants[0].name).toBe('Tenant A');
    expect(result.current.selectedTenants[1].name).toBe('Tenant B');
  });

  it('should update currentTenant when selection changes', () => {
    const { result } = renderHook(() => useAuthStore());
    
    act(() => {
      result.current.setUser({
        id: 1,
        tenants: [
          { id: '1', name: 'Tenant A' },
          { id: '2', name: 'Tenant B' },
        ]
      });
    });

    act(() => {
      result.current.setSelectedTenants(['2']);
    });

    // currentTenant should be the first selected
    expect(result.current.currentTenant?.id).toBe('2');
  });

  it('should handle empty selection (all tenants)', () => {
    const { result } = renderHook(() => useAuthStore());
    
    act(() => {
      result.current.setSelectedTenants([]);
    });

    expect(result.current.selectedTenants).toEqual([]);
    expect(result.current.currentTenant).toBeNull();
  });
});
```

---

## ✅ Checklist de Implementación

### Frontend:
- [ ] Modificar `authStore.ts` - añadir `selectedTenants` y `setSelectedTenants()`
- [ ] Crear `TenantMultiSwitcher.tsx` component
- [ ] Actualizar `apiClient.ts` interceptor para enviar `X-Tenant-Ids`
- [ ] Modificar `Navbar.tsx` para usar `TenantMultiSwitcher`
- [ ] Añadir export en `components/shared/index.ts`
- [ ] Testing unitario de authStore
- [ ] Testing de componente TenantMultiSwitcher

### Backend:
- [ ] Crear/modificar middleware `TenantScope.php`
- [ ] Actualizar `DashboardController` para soportar múltiples tenants
- [ ] Actualizar `VacationController`
- [ ] Actualizar `DocumentController`
- [ ] Actualizar `AuditLogController`
- [ ] Testing de endpoints con múltiples tenant IDs
- [ ] Testing de retrocompatibilidad con `X-Tenant-Id` (single)

### QA:
- [ ] Probar con 0 tenants seleccionados (todas)
- [ ] Probar con 1 tenant seleccionado (comportamiento actual)
- [ ] Probar con múltiples tenants (2+)
- [ ] Verificar que datos se filtran correctamente
- [ ] Verificar performance con muchas empresas
- [ ] Testing de permisos (usuario solo ve sus empresas)

---

**Última actualización**: 16 Diciembre 2025  
**Versión**: 1.0
