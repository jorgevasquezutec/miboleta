# Presentation Utils

Utilidades reutilizables para la capa de presentación, siguiendo los principios de Clean Architecture.

## 📁 Estructura

```
utils/
├── documentStatus.tsx   # Utilidades para estados de documentos
├── batchStatus.tsx      # Utilidades para estados de batches
├── formatters.ts        # Funciones de formateo (fechas, archivos, etc.)
└── index.ts            # Exportaciones centralizadas
```

## 📚 Módulos

### `documentStatus.tsx`

Gestión de estados de documentos y sus representaciones visuales.

#### Configuración disponible

```typescript
DOCUMENT_STATUS_CONFIG = {
  pending: { label: "Pendiente Firma", className: "...", bgColor: "#eab308" },
  signed: { label: "Firmado", className: "...", bgColor: "#22c55e" },
  active: { label: "Disponible", className: "...", bgColor: "#3b82f6" },
  orphan: { label: "Huérfano", className: "...", bgColor: "#f97316" },
  expired: { label: "Expirado", className: "...", bgColor: "#ef4444" },
}
```

#### Funciones

- **`getDocumentStatusBadge(status)`** - Retorna un Badge component de shadcn/ui
  ```tsx
  import { getDocumentStatusBadge } from "@/presentation/utils";

  {getDocumentStatusBadge(document.status)}
  ```

- **`getDocumentStatusBadgeInline(status)`** - Retorna un span con estilos inline (para tablas)
  ```tsx
  import { getDocumentStatusBadgeInline } from "@/presentation/utils";

  {getDocumentStatusBadgeInline(document.status)}
  ```

- **`getDocumentStatusLabel(status)`** - Retorna solo el label
  ```typescript
  getDocumentStatusLabel('pending') // "Pendiente Firma"
  ```

- **`getDocumentStatusColor(status)`** - Retorna solo el color hex
  ```typescript
  getDocumentStatusColor('signed') // "#22c55e"
  ```

### `batchStatus.tsx`

Gestión de estados de batches de carga.

#### Configuración disponible

```typescript
BATCH_STATUS_CONFIG = {
  pending: { label: "Pendiente", bgColor: "#eab308", icon: Clock },
  processing: { label: "Procesando", bgColor: "#3b82f6", icon: RefreshCw },
  completed: { label: "Completado", bgColor: "#22c55e", icon: CheckCircle },
  failed: { label: "Fallido", bgColor: "#ef4444", icon: XCircle },
  partial: { label: "Parcial", bgColor: "#f97316", icon: AlertTriangle },
}
```

#### Funciones

- **`getBatchStatusBadge(status)`** - Retorna un badge con icono animado
  ```tsx
  import { getBatchStatusBadge } from "@/presentation/utils";

  {getBatchStatusBadge(batch.status)}
  ```

- **`getBatchStatusLabel(status)`** - Retorna solo el label
  ```typescript
  getBatchStatusLabel('processing') // "Procesando"
  ```

### `formatters.ts`

Funciones de formateo reutilizables.

#### Funciones

- **`formatDate(dateString, options?)`** - Formatea fechas
  ```typescript
  formatDate("2024-03-15T10:30:00") // "15 mar 2024"
  formatDate("2024-03-15T10:30:00", { includeTime: true }) // "15/03/2024, 10:30"
  formatDate(null) // "-"
  ```

- **`formatDateTime(dateString)`** - Atajo para fecha con hora
  ```typescript
  formatDateTime("2024-03-15T10:30:00") // "15/03/2024, 10:30"
  ```

- **`formatPeriod(period)`** - Formatea período YYYY-MM
  ```typescript
  formatPeriod("2024-03") // "Marzo 2024"
  ```

- **`formatFileSize(bytes)`** - Formatea tamaño de archivo
  ```typescript
  formatFileSize(1048576) // "1 MB"
  formatFileSize(2048) // "2 KB"
  ```

- **`truncateText(text, maxLength?)`** - Trunca texto largo
  ```typescript
  truncateText("Este es un texto muy largo", 10) // "Este es un..."
  ```

## 🎯 Uso

### Importación centralizada

```typescript
import {
  // Document Status
  getDocumentStatusBadge,
  getDocumentStatusBadgeInline,
  getDocumentStatusLabel,
  DOCUMENT_STATUS_CONFIG,

  // Batch Status
  getBatchStatusBadge,
  getBatchStatusLabel,
  BATCH_STATUS_CONFIG,

  // Formatters
  formatDate,
  formatDateTime,
  formatPeriod,
  formatFileSize,
  truncateText,
} from "@/presentation/utils";
```

### Ejemplos en componentes

#### Dashboard con badges de documentos
```tsx
import { getDocumentStatusBadge, formatDate } from "@/presentation/utils";

export function Dashboard() {
  return (
    <div>
      {documents.map(doc => (
        <div key={doc.id}>
          <h3>{doc.documentType?.displayName}</h3>
          {getDocumentStatusBadge(doc.status)}
          <p>{formatDate(doc.createdAt)}</p>
        </div>
      ))}
    </div>
  );
}
```

#### Tabla de batches
```tsx
import { getBatchStatusBadge, formatDateTime } from "@/presentation/utils";

export function BatchesTable() {
  return (
    <Table>
      <TableBody>
        {batches.map(batch => (
          <TableRow key={batch.id}>
            <TableCell>{getBatchStatusBadge(batch.status)}</TableCell>
            <TableCell>{formatDateTime(batch.createdAt)}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
```

#### Tabla con badges inline
```tsx
import { getDocumentStatusBadgeInline } from "@/presentation/utils";

export function DocumentsTable() {
  return (
    <Table>
      <TableBody>
        {documents.map(doc => (
          <TableRow key={doc.id}>
            <TableCell>{getDocumentStatusBadgeInline(doc.status)}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
```

## ✨ Ventajas

1. **DRY (Don't Repeat Yourself)** - Código reutilizable, sin duplicación
2. **Consistencia** - Mismos estilos y comportamiento en toda la app
3. **Mantenibilidad** - Cambios en un solo lugar
4. **Tipado fuerte** - TypeScript garantiza el uso correcto
5. **Clean Architecture** - Separación clara de responsabilidades

## 🔧 Extensión

Para agregar nuevos estados o utilidades:

1. Actualiza la configuración correspondiente
2. Exporta en `index.ts`
3. Actualiza este README

### Ejemplo: Agregar nuevo estado de documento

```typescript
// documentStatus.tsx
export const DOCUMENT_STATUS_CONFIG = {
  // ... estados existentes
  archived: {
    label: "Archivado",
    className: "bg-gray-500 text-white",
    bgColor: "#6b7280",
  },
} as const;
```

## 📝 Notas

- Los badges inline usan estilos inline para compatibilidad con contextos sin shadcn
- Los badges de shadcn/ui requieren el componente Badge instalado
- Todas las fechas usan locale `es-PE` por defecto
- Los colores siguen la paleta de Tailwind CSS
