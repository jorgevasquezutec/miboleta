# Implementación de Departamentos por Tenant

## Resumen Ejecutivo

Este documento detalla la implementación de un sistema de departamentos escalable para el proyecto Miboleta, siguiendo la arquitectura multi-tenant existente.

**Estado:** Pendiente de implementación
**Fecha creación:** 2025-12-10
**Prioridad:** Media

---

## 1. Análisis y Decisión de Arquitectura

### Contexto Actual

El sistema Miboleta es una plataforma multi-tenant donde:
- Los usuarios pueden pertenecer a múltiples tenants (organizaciones)
- Cada usuario tiene un tenant primario
- Existe jerarquía de supervisores (`immediate_supervisor`)
- Los roles son: `root`, `admin`, `client`

### Problema

Actualmente se intenta usar un campo `department` (string) en la UI, pero no existe en el modelo de dominio. Esto genera:
- ❌ Inconsistencias (sin validación)
- ❌ Duplicación de datos
- ❌ Imposibilidad de reportes estructurados
- ❌ No escala en arquitectura multi-tenant

### Decisión: Opción 2 - Departamentos por Tenant

Cada tenant define sus propios departamentos, y un usuario puede pertenecer a diferentes departamentos según el tenant.

**Ventajas:**
- ✅ Escalable y mantenible
- ✅ Datos normalizados
- ✅ Alineado con arquitectura multi-tenant actual
- ✅ Permite reportes y métricas por departamento
- ✅ Flexible para futuras expansiones (manager de departamento, presupuestos, etc.)

---

## 2. Diseño de Base de Datos

### 2.1. Nueva Tabla: `departments`

```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    manager_id BIGINT UNSIGNED NULL COMMENT 'Usuario responsable del departamento',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Índices
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_manager_id (manager_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_department_per_tenant (tenant_id, name),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nota:** `UNIQUE KEY unique_department_per_tenant` previene duplicados por tenant (no puede haber dos "Ventas" en la misma empresa).

### 2.2. Modificar Tabla Pivot: `tenant_user`

Agregar columna `department_id` a la relación usuario-tenant:

```sql
ALTER TABLE tenant_user
ADD COLUMN department_id BIGINT UNSIGNED NULL AFTER is_primary,
ADD INDEX idx_department_id (department_id),
ADD FOREIGN KEY fk_department (department_id)
    REFERENCES departments(id) ON DELETE SET NULL;
```

**Lógica:**
- Un usuario en Tenant A puede estar en "Ventas"
- El mismo usuario en Tenant B puede estar en "Marketing"
- El departamento es específico de la relación user-tenant

---

## 3. Diseño de Dominio (TypeScript)

### 3.1. Nueva Entidad: `Department`

Crear archivo: `src/core/domain/entities/Department.ts`

```typescript
// Domain Entity - Department
export interface Department {
  id: string;
  tenant_id: string;
  name: string;
  description?: string;
  manager_id?: string | null;
  manager?: DepartmentManager | null; // Relación
  status: 'active' | 'inactive';

  // Metadata
  created_at?: string;
  updated_at?: string;
}

// Información básica de departamento
export interface DepartmentBasic {
  id: string;
  name: string;
  tenant_id: string;
}

// Manager del departamento
export interface DepartmentManager {
  id: string;
  name: string;
  full_name?: string;
  email?: string;
}

export type CreateDepartmentData = Omit<Department, 'id' | 'created_at' | 'updated_at'>;
export type UpdateDepartmentData = Partial<Omit<Department, 'id' | 'tenant_id' | 'created_at' | 'updated_at'>>;
```

### 3.2. Actualizar Entidad: `User`

Modificar: `src/core/domain/entities/User.ts`

```typescript
import { DepartmentBasic } from './Department';

export interface User {
  id: string;
  name: string;
  last_name?: string;
  full_name?: string;
  email: string;
  document_type?: string;
  document_text?: string;
  phone?: string;
  role: 'root' | 'admin' | 'client';
  roles?: string[];
  status: 'active' | 'inactive' | 'suspended' | 'pending';

  // Password management
  must_change_password?: boolean;

  // Multi-tenancy
  tenants?: TenantAssociation[];
  primary_tenant?: TenantBasic | null;

  // Supervisor
  immediate_supervisor?: SupervisorBasic | null;
  immediate_supervisor_id?: string | null;

  // Department - Departamento en el tenant actual (contexto)
  department?: DepartmentBasic | null;
  department_id?: string | null;

  // Metadata
  avatar?: string;
  createdAt?: Date;
  updatedAt?: Date;
  created_at?: string;
  updated_at?: string;
}

// Actualizar TenantAssociation para incluir department
export interface TenantAssociation {
  id: string;
  name: string;
  ruc: string;
  logo_path?: string;
  is_primary: boolean;
  department?: DepartmentBasic | null; // Departamento en este tenant
  department_id?: string | null;
}
```

---

## 4. Implementación Backend (Laravel)

### 4.1. Migración

Crear: `database/migrations/YYYY_MM_DD_create_departments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crear tabla departments
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()
                  ->constrained('users')->onDelete('set null');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // Índices
            $table->index('status');
            $table->unique(['tenant_id', 'name'], 'unique_department_per_tenant');
        });

        // Agregar department_id a tenant_user
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()
                  ->after('is_primary')
                  ->constrained('departments')->onDelete('set null');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
```

### 4.2. Modelo: `Department`

Crear: `backend/app/Models/Department.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'manager_id',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Relación: Departamento pertenece a un Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relación: Manager del departamento
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Relación: Usuarios en este departamento
     */
    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'department_id');
    }

    /**
     * Scope: Solo departamentos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Filtrar por tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
```

### 4.3. Actualizar Modelo: `User`

Modificar: `backend/app/Models/User.php`

```php
// Dentro de la clase User

/**
 * Obtener departamento del usuario en un tenant específico
 */
public function getDepartmentForTenant($tenantId): ?Department
{
    $tenantUser = $this->tenants()
        ->where('tenant_id', $tenantId)
        ->first();

    return $tenantUser?->pivot?->department;
}

/**
 * Obtener departamento del tenant primario
 */
public function getPrimaryDepartment(): ?Department
{
    $primaryTenant = $this->tenants()
        ->where('is_primary', true)
        ->first();

    return $primaryTenant?->pivot?->department;
}

// Actualizar relación tenants para incluir department
public function tenants(): BelongsToMany
{
    return $this->belongsToMany(Tenant::class, 'tenant_user')
        ->withPivot(['is_primary', 'department_id'])
        ->withTimestamps()
        ->with('pivot.department'); // Eager load department
}
```

### 4.4. Actualizar Modelo Pivot: `TenantUser`

Si usas modelo pivot explícito:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUser extends Pivot
{
    protected $table = 'tenant_user';

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Relación con Department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
```

### 4.5. Controlador: `DepartmentController`

Crear: `backend/app/Http/Controllers/Api/DepartmentController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    /**
     * Listar departamentos del tenant actual
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->current_tenant_id
                    ?? $request->header('X-Tenant-Id');

        $departments = Department::query()
            ->forTenant($tenantId)
            ->with('manager:id,name,email')
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $departments
        ]);
    }

    /**
     * Crear departamento
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $tenantId = $request->user()->current_tenant_id
                    ?? $request->header('X-Tenant-Id');

        $department = Department::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'status' => 'active',
        ]);

        return response()->json([
            'data' => $department->load('manager'),
            'message' => 'Departamento creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar departamento
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $department->update($validated);

        return response()->json([
            'data' => $department->load('manager'),
            'message' => 'Departamento actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar departamento
     */
    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json([
            'message' => 'Departamento eliminado exitosamente'
        ]);
    }
}
```

### 4.6. Rutas API

Agregar a: `backend/routes/api.php`

```php
// Departments
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index']);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::put('/{department}', [DepartmentController::class, 'update']);
        Route::delete('/{department}', [DepartmentController::class, 'destroy']);
    });
});
```

---

## 5. Implementación Frontend (React + TypeScript)

### 5.1. Repository: `DepartmentRepository`

Crear: `src/infrastructure/persistence/repositories/DepartmentRepository.ts`

```typescript
import apiClient from '@/infrastructure/http/apiClient';
import { Department, CreateDepartmentData, UpdateDepartmentData } from '@/core/domain/entities/Department';

export class DepartmentRepository {
  async getAll(tenantId?: string): Promise<Department[]> {
    const response = await apiClient.get<{ data: Department[] }>('/departments', {
      headers: tenantId ? { 'X-Tenant-Id': tenantId } : undefined,
    });
    return response.data.data;
  }

  async create(data: CreateDepartmentData): Promise<Department> {
    const response = await apiClient.post<{ data: Department }>('/departments', data);
    return response.data.data;
  }

  async update(id: string, data: UpdateDepartmentData): Promise<Department> {
    const response = await apiClient.put<{ data: Department }>(`/departments/${id}`, data);
    return response.data.data;
  }

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/departments/${id}`);
  }
}

export const departmentRepository = new DepartmentRepository();
```

### 5.2. Store: `useDepartmentsStore`

Crear: `src/presentation/stores/departmentsStore.ts`

```typescript
import { create } from 'zustand';
import { Department, CreateDepartmentData, UpdateDepartmentData } from '@/core/domain/entities/Department';
import { departmentRepository } from '@/infrastructure/persistence/repositories/DepartmentRepository';

interface DepartmentsState {
  departments: Department[];
  isLoading: boolean;
  error: string | null;

  fetchDepartments: (tenantId?: string) => Promise<void>;
  createDepartment: (data: CreateDepartmentData) => Promise<Department>;
  updateDepartment: (id: string, data: UpdateDepartmentData) => Promise<Department>;
  deleteDepartment: (id: string) => Promise<void>;
  clearError: () => void;
}

export const useDepartmentsStore = create<DepartmentsState>((set) => ({
  departments: [],
  isLoading: false,
  error: null,

  fetchDepartments: async (tenantId?: string) => {
    set({ isLoading: true, error: null });
    try {
      const departments = await departmentRepository.getAll(tenantId);
      set({ departments, isLoading: false });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Error al cargar departamentos',
        isLoading: false,
      });
    }
  },

  createDepartment: async (data: CreateDepartmentData) => {
    set({ isLoading: true, error: null });
    try {
      const newDepartment = await departmentRepository.create(data);
      set((state) => ({
        departments: [...state.departments, newDepartment],
        isLoading: false,
      }));
      return newDepartment;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Error al crear departamento',
        isLoading: false,
      });
      throw error;
    }
  },

  updateDepartment: async (id: string, data: UpdateDepartmentData) => {
    set({ isLoading: true, error: null });
    try {
      const updatedDepartment = await departmentRepository.update(id, data);
      set((state) => ({
        departments: state.departments.map((d) => (d.id === id ? updatedDepartment : d)),
        isLoading: false,
      }));
      return updatedDepartment;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Error al actualizar departamento',
        isLoading: false,
      });
      throw error;
    }
  },

  deleteDepartment: async (id: string) => {
    set({ isLoading: true, error: null });
    try {
      await departmentRepository.delete(id);
      set((state) => ({
        departments: state.departments.filter((d) => d.id !== id),
        isLoading: false,
      }));
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Error al eliminar departamento',
        isLoading: false,
      });
      throw error;
    }
  },

  clearError: () => set({ error: null }),
}));
```

### 5.3. Componente: `DepartmentSelector`

Crear: `src/presentation/components/shared/DepartmentSelector.tsx`

```typescript
import { useEffect } from 'react';
import { useDepartmentsStore } from '@/presentation/stores/departmentsStore';
import { useAuthStore } from '@/presentation/stores/authStore';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/presentation/components/ui/select';

interface DepartmentSelectorProps {
  value?: string | null;
  onChange: (departmentId: string | null) => void;
  tenantId?: string;
  disabled?: boolean;
}

export function DepartmentSelector({
  value,
  onChange,
  tenantId,
  disabled = false,
}: DepartmentSelectorProps) {
  const { departments, fetchDepartments, isLoading } = useDepartmentsStore();
  const { user } = useAuthStore();

  useEffect(() => {
    const targetTenantId = tenantId || user?.primary_tenant?.id;
    if (targetTenantId) {
      fetchDepartments(targetTenantId);
    }
  }, [tenantId, user]);

  return (
    <Select
      value={value || 'none'}
      onValueChange={(val) => onChange(val === 'none' ? null : val)}
      disabled={disabled || isLoading}
    >
      <SelectTrigger>
        <SelectValue placeholder={isLoading ? 'Cargando...' : 'Seleccionar departamento'} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="none">Sin departamento</SelectItem>
        {departments.map((dept) => (
          <SelectItem key={dept.id} value={dept.id}>
            {dept.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
```

### 5.4. Actualizar UserFormPage

Modificar: `src/presentation/pages/admin/UserFormPage.tsx`

```typescript
// Importar DepartmentSelector
import { DepartmentSelector } from '@/presentation/components/shared/DepartmentSelector';

// Agregar state para department por tenant
const [departmentsByTenant, setDepartmentsByTenant] = useState<Record<string, string | null>>({});

// En la sección de Tenants, agregar selector de departamento
{tenants.map((tenant) => {
  const isSelected = selectedTenantIds.includes(String(tenant.id));
  const isPrimary = primaryTenantId === String(tenant.id);

  return (
    <div key={tenant.id} className="space-y-2">
      {/* Checkbox y nombre del tenant... */}

      {isSelected && (
        <div className="ml-8">
          <Label>Departamento en {tenant.name}</Label>
          <DepartmentSelector
            value={departmentsByTenant[tenant.id] || null}
            onChange={(deptId) =>
              setDepartmentsByTenant(prev => ({
                ...prev,
                [tenant.id]: deptId
              }))
            }
            tenantId={tenant.id}
          />
        </div>
      )}
    </div>
  );
})}
```

---

## 6. Plan de Implementación

### Fase 1: Backend (Prioridad Alta)
- [ ] Crear migración `departments`
- [ ] Modificar migración `tenant_user` para agregar `department_id`
- [ ] Crear modelo `Department`
- [ ] Actualizar modelo `User` con relaciones
- [ ] Crear `DepartmentController`
- [ ] Agregar rutas API
- [ ] Crear seeders con departamentos de ejemplo
- [ ] Testing de endpoints

### Fase 2: Frontend - Dominio (Prioridad Alta)
- [ ] Crear entidad `Department.ts`
- [ ] Actualizar entidad `User.ts` y `TenantAssociation`
- [ ] Exportar desde `entities/index.ts`

### Fase 3: Frontend - Infraestructura (Prioridad Media)
- [ ] Crear `DepartmentRepository`
- [ ] Crear `useDepartmentsStore`
- [ ] Exportar desde `repositories/index.ts`

### Fase 4: Frontend - UI (Prioridad Media)
- [ ] Crear componente `DepartmentSelector`
- [ ] Crear página `DepartmentsListPage` (CRUD completo)
- [ ] Integrar selector en `UserFormPage`
- [ ] Mostrar departamento en `UserDetailPage`
- [ ] Actualizar `UsersPage` para mostrar departamento

### Fase 5: Testing y Refinamiento (Prioridad Baja)
- [ ] Testing unitario de repositorio
- [ ] Testing de integración
- [ ] Documentación de API (Swagger/OpenAPI)
- [ ] Optimizaciones de queries (N+1 prevention)

---

## 7. Consideraciones Técnicas

### 7.1. Permisos y Autorización

Definir políticas (Laravel Policies):
- Solo `admin` o `root` pueden crear/editar/eliminar departamentos
- Los usuarios `client` solo pueden ver los departamentos de su tenant

### 7.2. Validaciones

- Un usuario solo puede ser asignado a departamentos de los tenants a los que pertenece
- El manager de un departamento debe pertenecer al mismo tenant
- No se pueden eliminar departamentos con usuarios asignados (o se debe manejar la reasignación)

### 7.3. Performance

- Usar **eager loading** para evitar N+1:
  ```php
  $users = User::with(['tenants.pivot.department'])->get();
  ```
- Indexar columnas `tenant_id`, `department_id`, `status`

### 7.4. Migraciones de Datos Existentes

Si ya existen usuarios con `department` como string:
1. Crear departamentos desde los valores únicos existentes
2. Mapear usuarios a los nuevos IDs
3. Eliminar columna legacy (si existiera)

---

## 8. Extensiones Futuras

Una vez implementado el sistema básico, se pueden agregar:

### 8.1. Métricas por Departamento
- Cantidad de usuarios
- Documentos generados
- Performance metrics

### 8.2. Presupuestos por Departamento
- Asignar presupuestos mensuales/anuales
- Tracking de gastos

### 8.3. Reportes Organizacionales
- Organigrama visual
- Jerarquía departamental
- Exportación a PDF/Excel

### 8.4. Sub-departamentos
- Estructura jerárquica de departamentos
- Ej: Ventas → Ventas Región Norte

---

## 9. Referencias

- **Modelo de datos:** `docs/MODELADO_BASE_DATOS_SQL.md`
- **Arquitectura:** `docs/CLEAN_ARCHITECTURE.md`
- **Sistema de autenticación:** `docs/AUTH_SYSTEM.md`

---

## 10. Checklist de Implementación

Marcar cuando se complete cada item:

### Backend
- [ ] Migración `departments` ejecutada
- [ ] Migración `tenant_user` actualizada
- [ ] Modelo `Department` creado
- [ ] Relaciones en modelos configuradas
- [ ] Controller con CRUD completo
- [ ] Rutas agregadas y testeadas
- [ ] Seeders creados
- [ ] Tests unitarios pasando

### Frontend
- [ ] Entidades TypeScript definidas
- [ ] Repository implementado
- [ ] Store Zustand creado
- [ ] Componente selector funcionando
- [ ] CRUD UI completa
- [ ] Integración en formularios de usuario
- [ ] Testing de integración

### Documentación
- [ ] Actualizar `MODELADO_BASE_DATOS.md`
- [ ] Actualizar diagramas ER
- [ ] Documentar endpoints en Swagger/OpenAPI
- [ ] Actualizar README principal

---

**Nota final:** Este documento debe mantenerse actualizado conforme se implemente la funcionalidad. Cualquier desviación o decisión importante debe documentarse aquí.
