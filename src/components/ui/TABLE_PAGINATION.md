# Table Pagination - Documentación

Sistema de paginación reutilizable para tablas que soporta tanto **client-side** como **server-side pagination**.

## 📦 Componentes Incluidos

### 1. `TablePagination` Component
Componente de UI para controles de paginación con diseño responsivo.

### 2. `useTablePagination` Hook
Hook personalizado para manejar la lógica de paginación.

## 🚀 Uso Rápido

### Client-Side Pagination (Datos en memoria)

```tsx
import { useTablePagination } from "../../hooks/useTablePagination";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TablePagination } from "../ui/table";

function MyComponent() {
  const { users } = usePlatform(); // o cualquier array de datos

  const {
    paginatedData,
    currentPage,
    pageSize,
    totalPages,
    totalItems,
    setCurrentPage,
    setPageSize,
  } = useTablePagination({
    data: users,
    initialPageSize: 10,
    mode: "client",
  });

  return (
    <>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Nombre</TableHead>
            <TableHead>Email</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {paginatedData.map((user) => (
            <TableRow key={user.id}>
              <TableCell>{user.name}</TableCell>
              <TableCell>{user.email}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <TablePagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={totalItems}
        pageSize={pageSize}
        onPageChange={setCurrentPage}
        onPageSizeChange={setPageSize}
      />
    </>
  );
}
```

### Server-Side Pagination (Datos del servidor)

```tsx
import { useState, useEffect } from "react";
import { TablePagination } from "../ui/table";

function MyComponent() {
  const [data, setData] = useState([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [totalItems, setTotalItems] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const fetchData = async (page: number, size: number) => {
    const response = await fetch(`/api/users?page=${page}&pageSize=${size}`);
    const result = await response.json();

    setData(result.data);
    setTotalItems(result.totalItems);
    setTotalPages(result.totalPages);
  };

  useEffect(() => {
    fetchData(currentPage, pageSize);
  }, [currentPage, pageSize]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handlePageSizeChange = (size: number) => {
    setPageSize(size);
    setCurrentPage(1); // Reset a primera página
  };

  return (
    <>
      <Table>
        {/* Renderizar data */}
      </Table>

      <TablePagination
        currentPage={currentPage}
        totalPages={totalPages}
        totalItems={totalItems}
        pageSize={pageSize}
        onPageChange={handlePageChange}
        onPageSizeChange={handlePageSizeChange}
        mode="server"
      />
    </>
  );
}
```

## 📋 Props de TablePagination

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `currentPage` | `number` | **Requerido** | Página actual (1-based) |
| `totalPages` | `number` | **Requerido** | Total de páginas |
| `pageSize` | `number` | **Requerido** | Tamaño de página actual |
| `onPageChange` | `(page: number) => void` | **Requerido** | Callback al cambiar de página |
| `totalItems` | `number` | `undefined` | Total de items (para mostrar info) |
| `pageSizeOptions` | `number[]` | `[10, 20, 50, 100]` | Opciones de tamaño de página |
| `onPageSizeChange` | `(size: number) => void` | `undefined` | Callback al cambiar tamaño |
| `mode` | `"client" \| "server"` | `"client"` | Modo de paginación |
| `showPageSizeSelector` | `boolean` | `true` | Mostrar selector de tamaño |
| `showPageInfo` | `boolean` | `true` | Mostrar información de página |
| `labels` | `object` | `{}` | Etiquetas personalizadas |

## 🎯 Características

### ✅ Client-Side Pagination
- Automáticamente divide los datos en páginas
- No requiere llamadas al servidor
- Ideal para conjuntos de datos pequeños/medianos (< 1000 items)

### ✅ Server-Side Pagination
- Carga solo los datos necesarios
- Ideal para conjuntos de datos grandes
- Reduce uso de memoria y ancho de banda

### ✅ Controles de Navegación
- **Primera página**: Ir directamente al inicio
- **Página anterior**: Retroceder una página
- **Números de página**: Clic directo en páginas
- **Página siguiente**: Avanzar una página
- **Última página**: Ir directamente al final

### ✅ Selector de Tamaño de Página
- Cambiar dinámicamente items por página
- Opciones configurables (10, 20, 50, 100, etc.)
- Se resetea a página 1 al cambiar tamaño

### ✅ Información de Página
- Muestra "Mostrando X-Y de Z elementos"
- Personalizable con labels custom

### ✅ Diseño Responsivo
- En móvil: muestra página actual simplificada
- En desktop: muestra todos los números de página
- Botones adaptativos según espacio

### ✅ Estados Disabled
- Botones deshabilitados cuando no aplican
- Primera/Anterior disabled en página 1
- Última/Siguiente disabled en última página

## 🎨 Personalización

### Etiquetas Personalizadas

```tsx
<TablePagination
  // ... otras props
  labels={{
    pageInfo: `Estás en la página ${currentPage} de ${totalPages}`,
    itemsInfo: "custom items info",
    rowsPerPage: "Items por página:",
  }}
/>
```

### Opciones de Tamaño Personalizadas

```tsx
<TablePagination
  // ... otras props
  pageSizeOptions={[5, 15, 25, 50]}
/>
```

### Sin Selector de Tamaño

```tsx
<TablePagination
  // ... otras props
  showPageSizeSelector={false}
/>
```

## 📱 Adaptación Móvil

El componente se adapta automáticamente:
- **Desktop**: Muestra números de página individuales
- **Móvil**: Muestra solo "X/Y" para ahorrar espacio
- Todos los controles funcionan igual en ambos modos

## 🔧 Integración con Vistas Existentes

### TenantManagementView

```tsx
// En lugar de:
{tenants.map((tenant) => ...)}

// Usar:
const { paginatedData, ... } = useTablePagination({ data: tenants, ... });
{paginatedData.map((tenant) => ...)}

// Y agregar después de </Table>:
<TablePagination ... />
```

### UserManagementView

```tsx
const { paginatedData, ... } = useTablePagination({
  data: filteredUsers, // Ya filtrados por búsqueda
  initialPageSize: 10
});

{paginatedData.map((user) => ...)}
<TablePagination ... />
```

## 🌐 Server-Side API Example

Tu backend debe devolver:

```json
{
  "data": [...],
  "page": 1,
  "pageSize": 10,
  "totalItems": 95,
  "totalPages": 10
}
```

Endpoint esperado:
```
GET /api/users?page=1&pageSize=10
```

## 🎯 Tips de Rendimiento

### Client-Side
- Usa para < 1000 items
- No requiere re-fetch al cambiar página
- Más rápido para el usuario

### Server-Side
- Usa para > 1000 items
- Reduce memoria del frontend
- Agrega loading states durante fetch

## 📝 Ejemplos Completos

Ver `src/examples/TablePaginationExample.tsx` para ejemplos completos y funcionales.
