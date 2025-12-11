# TenantMultiSelector - Documentación

## Descripción

Componente React avanzado para selección múltiple de organizaciones (tenants) con búsqueda server-side, paginación y gestión de tenant primario.

**Ubicación:** `src/presentation/components/shared/TenantMultiSelector.tsx`

---

## Características

✅ **Búsqueda server-side** con debounce (300ms)
✅ **Paginación incremental** (infinite scroll / load more)
✅ **Selección múltiple** con validaciones
✅ **Tenant primario** visual y funcional
✅ **Totalmente tipado** con TypeScript
✅ **Accesible** (ARIA labels, keyboard navigation)
✅ **Responsive** y optimizado
✅ **Reutilizable** en cualquier formulario

---

## Uso Básico

```tsx
import { TenantMultiSelector } from '@/presentation/components/shared/TenantMultiSelector';
import { useState } from 'react';

function MyForm() {
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [primaryId, setPrimaryId] = useState<string | null>(null);

  return (
    <TenantMultiSelector
      selectedTenantIds={selectedIds}
      onSelectionChange={setSelectedIds}
      primaryTenantId={primaryId}
      onPrimaryChange={setPrimaryId}
      minSelections={1}
    />
  );
}
```

---

## Props

### Requeridas

| Prop | Tipo | Descripción |
|------|------|-------------|
| `selectedTenantIds` | `string[]` | Array de IDs de tenants seleccionados |
| `onSelectionChange` | `(ids: string[]) => void` | Callback cuando cambia la selección |
| `primaryTenantId` | `string \| null` | ID del tenant marcado como primario |
| `onPrimaryChange` | `(id: string \| null) => void` | Callback cuando cambia el tenant primario |

### Opcionales

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `selectedTenants` | `Tenant[]` | `[]` | Tenants ya seleccionados (para mostrar info completa) |
| `minSelections` | `number` | `0` | Número mínimo de selecciones requeridas |
| `maxSelections` | `number` | `undefined` | Número máximo de selecciones permitidas |
| `placeholder` | `string` | `'Buscar organizaciones...'` | Texto del placeholder del buscador |
| `error` | `string` | `undefined` | Mensaje de error a mostrar |
| `disabled` | `boolean` | `false` | Deshabilita el componente |

---

## Ejemplos de Uso

### 1. Formulario de Usuario (Caso Real)

```tsx
import { UserFormPage } from '@/presentation/pages/admin/UserFormPage';

function UserFormPage() {
  const [selectedTenantIds, setSelectedTenantIds] = useState<string[]>([]);
  const [primaryTenantId, setPrimaryTenantId] = useState<string | null>(null);
  const [selectedTenants, setSelectedTenants] = useState<Tenant[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});

  return (
    <Card>
      <CardHeader>
        <CardTitle>Organizaciones *</CardTitle>
        <CardDescription>
          Selecciona las organizaciones a las que pertenecerá el usuario
        </CardDescription>
      </CardHeader>
      <CardContent>
        <TenantMultiSelector
          selectedTenantIds={selectedTenantIds}
          onSelectionChange={(ids) => {
            setSelectedTenantIds(ids);
            if (errors.tenants) {
              setErrors(prev => ({ ...prev, tenants: '' }));
            }
          }}
          primaryTenantId={primaryTenantId}
          onPrimaryChange={setPrimaryTenantId}
          selectedTenants={selectedTenants}
          minSelections={1}
          error={errors.tenants}
        />
      </CardContent>
    </Card>
  );
}
```

### 2. Con Validación

```tsx
function validateTenants() {
  const errors: Record<string, string> = {};

  if (selectedTenantIds.length === 0) {
    errors.tenants = 'Debes seleccionar al menos una organización';
  }

  if (!primaryTenantId) {
    errors.tenants = 'Debes marcar una organización como primaria';
  }

  return errors;
}
```

### 3. Con Límite Máximo

```tsx
<TenantMultiSelector
  selectedTenantIds={selectedIds}
  onSelectionChange={setSelectedIds}
  primaryTenantId={primaryId}
  onPrimaryChange={setPrimaryId}
  minSelections={1}
  maxSelections={5} // Máximo 5 organizaciones
  placeholder="Selecciona hasta 5 organizaciones..."
/>
```

### 4. Con Estado de Carga Inicial

```tsx
function MyComponent() {
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [primaryId, setPrimaryId] = useState<string | null>(null);
  const [selectedTenants, setSelectedTenants] = useState<Tenant[]>([]);

  useEffect(() => {
    // Cargar usuario existente
    loadUser(userId).then(user => {
      if (user.tenants) {
        setSelectedIds(user.tenants.map(t => t.id));
        setSelectedTenants(user.tenants as Tenant[]);

        const primary = user.tenants.find(t => t.is_primary);
        setPrimaryId(primary?.id || user.tenants[0]?.id || null);
      }
    });
  }, [userId]);

  return (
    <TenantMultiSelector
      selectedTenantIds={selectedIds}
      onSelectionChange={setSelectedIds}
      primaryTenantId={primaryId}
      onPrimaryChange={setPrimaryId}
      selectedTenants={selectedTenants}
      minSelections={1}
    />
  );
}
```

---

## Arquitectura Interna

### Componentes Utilizados

- **shadcn/ui Popover** - Para el dropdown
- **shadcn/ui Command** - Para búsqueda y lista
- **shadcn/ui ScrollArea** - Para scroll suave
- **shadcn/ui Checkbox** - Para selección
- **shadcn/ui Badge** - Para indicador "Primaria"
- **shadcn/ui Button** - Para acciones

### Hooks Personalizados

```typescript
// Hook de búsqueda con debounce
const {
  searchQuery,      // Query actual
  results,          // Resultados de la búsqueda
  isSearching,      // Estado de carga
  hasMore,          // Si hay más páginas
  loadMore,         // Cargar siguiente página
  setSearchQuery    // Actualizar query
} = useTenantSearch();
```

### Flujo de Búsqueda

```
Usuario escribe "acme"
    ↓
Debounce 300ms (useDebounce)
    ↓
useTenantSearch detecta cambio
    ↓
tenantsStore.searchTenants("acme", page=1)
    ↓
API: GET /tenants?search=acme&per_page=20&status=active
    ↓
Resultados se muestran en Command dropdown
    ↓
Usuario hace scroll → loadMore()
    ↓
searchTenants("acme", page=2)
    ↓
Resultados se agregan al array existente
```

---

## Estado y Sincronización

### Estado Local vs Store

El componente maneja:
- ✅ **IDs seleccionados** (estado del padre)
- ✅ **Tenant primario** (estado del padre)
- ✅ **UI del dropdown** (estado interno)

El store (tenantsStore) maneja:
- ✅ **Resultados de búsqueda** paginados
- ✅ **Loading states**
- ✅ **Errores de API**

### Sincronización de Datos

```typescript
// Los tenants seleccionados vienen del padre
selectedTenants: Tenant[]

// Los resultados de búsqueda vienen del store
const { results } = useTenantSearch()

// Se combinan para mostrar:
// 1. Tenants ya seleccionados (siempre visibles)
// 2. Resultados de búsqueda nuevos
const displayTenants = getDisplayTenants()
```

---

## Validaciones

### Validación en el Componente

El componente aplica automáticamente:
- ✅ No permitir deseleccionar si se alcanza `minSelections`
- ✅ No permitir seleccionar más de `maxSelections`
- ✅ Asignar automáticamente primario si solo hay 1 selección
- ✅ Reasignar primario si se elimina el actual

### Validación Externa (Formulario)

```typescript
function validateForm(): boolean {
  const errors: Record<string, string> = {};

  // Validar tenants
  if (formData.role !== 'root' && selectedTenantIds.length === 0) {
    errors.tenants = 'Los usuarios no-root deben tener al menos una organización';
  }

  if (selectedTenantIds.length > 0 && !primaryTenantId) {
    errors.tenants = 'Debes marcar una organización como primaria';
  }

  setErrors(errors);
  return Object.keys(errors).length === 0;
}
```

---

## Performance

### Optimizaciones Implementadas

1. **Debounce de búsqueda** (300ms) - Reduce llamadas a API
2. **Paginación** (20 items por página) - Carga incremental
3. **Búsqueda server-side** - No carga todos los tenants
4. **React.memo en CommandItem** - Evita re-renders innecesarios
5. **Estado mínimo** - Solo IDs, no objetos completos

### Métricas Esperadas

- Primera búsqueda: ~200-300ms
- Búsquedas subsecuentes (cached): ~50-100ms
- Renderizado de 20 items: <16ms (60fps)
- Memoria: ~500KB para 100 resultados

---

## Estilos y Tematización

El componente usa:
- Tailwind CSS para estilos
- Variables CSS de shadcn/ui para colores
- Clases utilitarias para estados

### Personalización de Colores

```tsx
// Tenant primario
className="bg-blue-50 border-blue-200"

// Tenant normal
className="bg-gray-50 border-gray-200"

// Badge primario
className="bg-blue-600 text-white"

// Errores
className="border-red-500 text-red-500"
```

---

## Accesibilidad

### Características de A11y

- ✅ **ARIA labels** en todos los botones
- ✅ **role="combobox"** en el trigger
- ✅ **aria-expanded** indica estado del dropdown
- ✅ **Navegación por teclado** completa
- ✅ **Focus management** correcto
- ✅ **Screen reader friendly**

### Navegación por Teclado

| Tecla | Acción |
|-------|--------|
| `Enter` | Abrir/cerrar dropdown |
| `↓` / `↑` | Navegar opciones |
| `Space` | Seleccionar/deseleccionar |
| `Esc` | Cerrar dropdown |
| `Tab` | Navegar entre elementos |

---

## Testing

### Casos de Prueba Sugeridos

```typescript
describe('TenantMultiSelector', () => {
  it('permite seleccionar múltiples tenants', () => {
    // Test lógica de selección
  });

  it('marca automáticamente el primero como primario', () => {
    // Test auto-asignación de primario
  });

  it('no permite deseleccionar si minSelections=1', () => {
    // Test validación mínima
  });

  it('busca tenants con debounce', async () => {
    // Test búsqueda con delay
  });

  it('carga más resultados al hacer scroll', () => {
    // Test paginación
  });

  it('muestra error cuando se provee', () => {
    // Test manejo de errores
  });
});
```

---

## Troubleshooting

### Problema: No aparecen resultados de búsqueda

**Causa:** El backend no devuelve datos o hay error en la API.

**Solución:**
```typescript
// Verificar en tenantsStore
console.log(searchResults); // Debe tener datos

// Verificar API call
// GET /tenants?search=query&per_page=20&status=active
```

### Problema: El debounce no funciona

**Causa:** El hook useDebounce no está aplicado correctamente.

**Solución:**
```typescript
// Verificar en useTenantSearch.ts
const debouncedQuery = useDebounce(searchQuery, 300);
```

### Problema: No se marca el tenant como primario

**Causa:** `onPrimaryChange` no está actualizado el estado del padre.

**Solución:**
```typescript
<TenantMultiSelector
  primaryTenantId={primaryId}
  onPrimaryChange={(id) => {
    console.log('Setting primary:', id);
    setPrimaryId(id); // Asegúrate que actualiza
  }}
/>
```

---

## Dependencias

### Paquetes Requeridos

- `react` ^18.0.0
- `lucide-react` (iconos)
- `@radix-ui/react-*` (primitivos de shadcn/ui)
- `zustand` (state management)

### Componentes shadcn/ui Requeridos

```bash
npx shadcn-ui@latest add popover
npx shadcn-ui@latest add command
npx shadcn-ui@latest add scroll-area
npx shadcn-ui@latest add checkbox
npx shadcn-ui@latest add badge
npx shadcn-ui@latest add button
```

---

## Roadmap / Mejoras Futuras

- [ ] Modo de selección única (radio en lugar de checkbox)
- [ ] Soporte para grupos de tenants
- [ ] Búsqueda por múltiples campos (RUC, dirección)
- [ ] Exportar selección como CSV
- [ ] Drag & drop para reordenar
- [ ] Historico de búsquedas recientes
- [ ] Modo offline con cache
- [ ] Virtualización para 1000+ items

---

## Changelog

### v1.0.0 (2025-12-10)
- ✅ Implementación inicial
- ✅ Búsqueda server-side con debounce
- ✅ Paginación incremental
- ✅ Gestión de tenant primario
- ✅ Validaciones integradas
- ✅ Accesibilidad completa

---

## Créditos

**Desarrollado por:** Claude & Jorge
**Fecha:** 2025-12-10
**Stack:** React + TypeScript + shadcn/ui + Zustand
**Licencia:** MIT (siguiendo licencia del proyecto)

---

## Soporte

Para bugs o mejoras, crear issue en el repositorio del proyecto.

Para preguntas sobre uso, consultar este documento primero.
