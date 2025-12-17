# Plan de Implementación: Preview Editable para Carga Masiva de Usuarios

## 📋 Objetivo
Implementar un **editor de datos tipo spreadsheet** en el navegador que permita a los usuarios revisar y **corregir errores directamente** sin tener que volver al archivo Excel.

---

## 🎯 Características del Editor

### Vista de Preview Editable:
- ✏️ **Edición inline** de todas las celdas
- ✅ **Validación en tiempo real** mientras se edita
- ➕ **Agregar nuevas filas** manualmente
- 🗑️ **Eliminar filas** con errores
- 🔄 **Revalidar** después de cada edición
- 💾 **Guardar cambios** y procesar datos editados
- 📊 **Vista de errores** con navegación directa a la celda

---

## 🛠️ Stack Tecnológico Recomendado

### Opción 1: react-data-grid (Recomendado)
```bash
npm install react-data-grid
```
- ✅ Gratis y open source
- ✅ Excelente rendimiento
- ✅ Support para edición inline
- ✅ Validaciones custom
- ✅ Copiar/pegar desde Excel
- 📚 Docs: https://adazzle.github.io/react-data-grid/

### Opción 2: AG Grid Community (Alternativa)
```bash
npm install ag-grid-react ag-grid-community
```
- ✅ Más features avanzadas
- ✅ Mejor para grandes datasets
- ⚠️ Curva de aprendizaje más alta
- 📚 Docs: https://www.ag-grid.com/

### Opción 3: Handsontable (Premium)
```bash
npm install handsontable @handsontable/react
```
- ✅ Experience más parecido a Excel
- ⚠️ Licencia comercial requerida
- 💰 No gratuito para uso comercial

---

## 📐 Arquitectura Propuesta

### 1. Estado de Datos
```typescript
interface EditableUser {
  id: string; // UUID temporal
  row_number: number;
  nombre: string;
  apellido: string;
  email: string;
  tipo_documento: 'dni' | 'ce' | 'passport' | 'ruc';
  numero_documento: string;
  rol: 'client' | 'root' | 'admin';
  estado: 'active' | 'inactive';
  telefono?: string;
  organizaciones: Array<{
    ruc: string;
    supervisor_email?: string;
  }>;
  // Metadatos de validación
  _errors: Record<string, string>; // { email: "Email inválido" }
  _warnings: Record<string, string>;
  _isValid: boolean;
  _isNew: boolean; // Agregado manualmente
  _isModified: boolean; // Editado desde el Excel
}
```

### 2. Custom Hooks
```typescript
// hooks/useEditableUsers.ts
export function useEditableUsers() {
  const [users, setUsers] = useState<EditableUser[]>([]);
  const [selectedCell, setSelectedCell] = useState<{row: number, col: string} | null>(null);
  
  const updateUser = (id: string, field: string, value: any) => {
    // Actualizar usuario
    // Revalidar campo
    // Actualizar errores
  };
  
  const validateUser = (user: EditableUser) => {
    // Validaciones en tiempo real
  };
  
  const addNewRow = () => {
    // Agregar fila vacía
  };
  
  const deleteRow = (id: string) => {
    // Eliminar fila
  };
  
  return { users, updateUser, addNewRow, deleteRow, selectedCell };
}
```

---

## 📝 Componentes a Crear

### 1. `EditablePreviewModal.tsx`
Modal principal que contiene todo el editor.

```typescript
interface EditablePreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  initialData: any[]; // Datos del Excel
  initialErrors: any[];
  initialWarnings: any[];
  onConfirm: (editedData: EditableUser[]) => Promise<void>;
}
```

### 2. `UserDataGrid.tsx`
Grid editable usando react-data-grid.

```typescript
import DataGrid, { Column } from 'react-data-grid';

const columns: Column<EditableUser>[] = [
  {
    key: 'row_number',
    name: '#',
    width: 50,
    frozen: true,
  },
  {
    key: 'nombre',
    name: 'Nombre *',
    width: 150,
    editable: true,
    editor: TextEditor,
    cellClass: (row) => row._errors.nombre ? 'cell-error' : '',
  },
  {
    key: 'email',
    name: 'Email *',
    width: 200,
    editable: true,
    editor: TextEditor,
    cellClass: (row) => row._errors.email ? 'cell-error' : '',
  },
  {
    key: 'rol',
    name: 'Rol *',
    width: 120,
    editable: true,
    editor: SelectEditor,
    editorOptions: {
      options: ['client', 'root', 'admin']
    },
  },
  // ... más columnas
];

export function UserDataGrid({ users, onUserUpdate }: Props) {
  return (
    <DataGrid
      columns={columns}
      rows={users}
      onRowsChange={setRows}
      rowKeyGetter={(row) => row.id}
      className="rdg-light"
    />
  );
}
```

### 3. `ValidationPanel.tsx`
Panel lateral que muestra errores/warnings con navegación.

```typescript
export function ValidationPanel({ users, onNavigateToError }: Props) {
  const errors = users.flatMap(user => 
    Object.entries(user._errors).map(([field, message]) => ({
      userId: user.id,
      row: user.row_number,
      field,
      message,
    }))
  );
  
  return (
    <div className="validation-panel">
      <h3>Errores ({errors.length})</h3>
      {errors.map((error, idx) => (
        <div 
          key={idx}
          className="error-item"
          onClick={() => onNavigateToError(error.userId, error.field)}
        >
          <span>Fila {error.row}:</span>
          <span>{error.field}</span>
          <span>{error.message}</span>
        </div>
      ))}
    </div>
  );
}
```

### 4. `CellValidationTooltip.tsx`
Tooltip que muestra error al hover sobre celda inválida.

```typescript
export function CellValidationTooltip({ error, warning }: Props) {
  if (!error && !warning) return null;
  
  return (
    <div className={error ? 'tooltip-error' : 'tooltip-warning'}>
      {error || warning}
    </div>
  );
}
```

---

## 🔄 Flujo de Usuario

```
1. Usuario sube Excel
   ↓
2. Backend valida y parsea → Retorna datos + errores
   ↓
3. Frontend abre MODAL EDITABLE con:
   ├─ Grid editable (react-data-grid)
   ├─ Panel de errores (click para navegar)
   ├─ Summary cards (total, válidos, errores)
   └─ Botones: Cancelar | Guardar y Procesar
   ↓
4. Usuario EDITA datos directamente:
   ├─ Cambia valores en celdas
   ├─ Ve validación en tiempo real
   ├─ Agrega/elimina filas
   └─ Corrige errores
   ↓
5. Usuario click "Guardar y Procesar"
   ↓
6. Frontend valida TODO nuevamente
   ↓
7. Si válido → POST /api/user-batches con datos editados
   ↓
8. Backend procesa usuarios
```

---

## ⚙️ Validaciones en Tiempo Real

### Validación por Campo:
```typescript
const validators: Record<string, (value: any, user: EditableUser) => string | null> = {
  nombre: (value) => {
    if (!value || value.trim() === '') return 'Nombre es requerido';
    if (value.length < 2) return 'Nombre muy corto';
    return null;
  },
  
  email: (value) => {
    if (!value) return 'Email es requerido';
    const emailRegex = /^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/;
    if (!emailRegex.test(value)) return 'Email inválido';
    return null;
  },
  
  rol: (value) => {
    if (!['client', 'root', 'admin'].includes(value)) {
      return 'Rol inválido';
    }
    return null;
  },
  
  // ... más validadores
};

function validateField(field: string, value: any, user: EditableUser): string | null {
  const validator = validators[field];
  return validator ? validator(value, user) : null;
}
```

---

## 🎨 Estilos CSS Necesarios

```css
/* Celdas con errores */
.cell-error {
  background-color: #fee;
  border: 1px solid #f88;
}

.cell-warning {
  background-color: #ffe;
  border: 1px solid #fc8;
}

/* Tooltip de validación */
.tooltip-error {
  position: absolute;
  z-index: 1000;
  background: #dc2626;
  color: white;
  padding: 8px 12px;
  border-radius: 4px;
  font-size: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.tooltip-warning {
  background: #f59e0b;
}

/* Panel de validación */
.validation-panel {
  width: 300px;
  height: 100%;
  border-left: 1px solid #ddd;
  overflow-y: auto;
  padding: 16px;
}

.error-item {
  padding: 8px;
  margin-bottom: 8px;
  background: #fee;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s;
}

.error-item:hover {
  background: #fcc;
}
```

---

## 📦 Instalación de Dependencias

```bash
# React Data Grid
npm install react-data-grid

# UUID para IDs temporales
npm install uuid
npm install -D @types/uuid

# Opcional: Lodash para debounce en validaciones
npm install lodash
npm install -D @types/lodash
```

---

## 🚀 Pasos de Implementación

### Fase 1: Setup Básico (2-3 horas)
1. ✅ Instalar `react-data-grid`
2. ✅ Crear `EditablePreviewModal.tsx` básico
3. ✅ Crear `useEditableUsers` hook
4. ✅ Setup inicial de columnas del grid

### Fase 2: Edición Inline (3-4 horas)
1. ✅ Implementar editores por tipo de campo:
   - TextEditor (nombre, apellido, email, etc.)
   - SelectEditor (rol, tipo_documento, estado)
   - NumberEditor (numero_documento)
2. ✅ Manejar cambios de celdas
3. ✅ Actualizar estado al editar

### Fase 3: Validaciones (2-3 horas)
1. ✅ Crear sistema de validadores por campo
2. ✅ Validar al editar (debounced)
3. ✅ Mostrar errores en celdas
4. ✅ Tooltips con mensajes de error

### Fase 4: Panel de Errores (1-2 horas)
1. ✅ Crear `ValidationPanel` component
2. ✅ Listar errores/warnings
3. ✅ Navegación click-to-error
4. ✅ Scroll automático a celda con error

### Fase 5: Features Avanzados (2-3 horas)
1. ✅ Agregar fila nueva
2. ✅ Eliminar filas
3. ✅ Copiar/pegar desde Excel
4. ✅ Undo/Redo (opcional)

### Fase 6: Integración y Testing (2-3 horas)
1. ✅ Conectar con backend
2. ✅ Manejo de organizaciones (subgrid o modal)
3. ✅ Testing con diferentes escenarios
4. ✅ Optimización de performance

**Total Estimado: 12-18 horas de trabajo**

---

## 💡 Consideraciones Importantes

### Performance:
- **Virtualización**: react-data-grid ya virtualiza filas (solo renderiza visibles)
- **Debounce**: Validaciones deben tener debounce de ~300ms
- **Memo**: Usar React.memo en componentes de celdas
- **Límite**: Máximo 1000 filas recomendado

### UX:
- **Navegación**: Soporte para Tab y Enter entre celdas
- **Feedback**: Indicadores visuales claros de errores
- **Autosave**: Guardar cambios en localStorage (recovery)
- **Confirmación**: Modal de confirmación antes de descartar cambios

### Validaciones Backend:
- Aunque usuarios editen en frontend, **backend DEBE re-validar**
- No confiar 100% en validación client-side
- Backend debe rechazar datos inválidos

---

## 📊 Ejemplo de Código Completo

### EditablePreviewModal.tsx (Esqueleto)
```typescript
import { useState, useCallback } from 'react';
import DataGrid from 'react-data-grid';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { ValidationPanel } from './ValidationPanel';
import { useEditableUsers } from '@/hooks/useEditableUsers';

export function EditablePreviewModal({ 
  isOpen, 
  onClose, 
  initialData,
  initialErrors,
  onConfirm 
}: Props) {
  const {
    users,
    updateUser,
    addNewRow,
    deleteRow,
    isValid,
    errorCount
  } = useEditableUsers(initialData, initialErrors);
  
  const [isProcessing, setIsProcessing] = useState(false);
  
  const handleConfirm = async () => {
    if (!isValid) {
      toast.error('Corrige todos los errores antes de continuar');
      return;
    }
    
    setIsProcessing(true);
    try {
      await onConfirm(users);
    } finally {
      setIsProcessing(false);
    }
  };
  
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-7xl h-[90vh]">
        <div className="flex h-full gap-4">
          {/* Grid Principal */}
          <div className="flex-1 flex flex-col">
            <div className="flex justify-between items-center mb-4">
              <h2>Preview y Edición de Datos</h2>
              <Button onClick={addNewRow} variant="outline">
                + Agregar Fila
              </Button>
            </div>
            
            <div className="flex-1 overflow-hidden">
              <DataGrid
                columns={columns}
                rows={users}
                onRowsChange={handleRowsChange}
                className="rdg-light"
              />
            </div>
            
            <div className="flex gap-3 mt-4">
              <Button variant="outline" onClick={onClose}>
                Cancelar
              </Button>
              <Button 
                onClick={handleConfirm}
                disabled={!isValid || isProcessing}
                className="flex-1"
              >
                {isProcessing ? 'Procesando...' : `Guardar y Procesar ${users.length} Usuarios`}
              </Button>
            </div>
          </div>
          
          {/* Panel de Validación */}
          <ValidationPanel 
            users={users}
            onNavigateToError={handleNavigateToError}
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}
```

---

## ✅ Checklist de Implementación

- [ ] Instalar react-data-grid
- [ ] Crear hook useEditableUsers
- [ ] Crear EditablePreviewModal
- [ ] Implementar UserDataGrid con columnas básicas
- [ ] Agregar editores inline (Text, Select)
- [ ] Sistema de validaciones por campo
- [ ] Mostrar errores en celdas (background rojo)
- [ ] Tooltips con mensajes de error
- [ ] ValidationPanel lateral
- [ ] Click-to-navigate en errores
- [ ] Botón "Agregar Fila"
- [ ] Botón "Eliminar Fila"
- [ ] Integrar con UserBatchUploadPage
- [ ] Conectar con backend validate/upload
- [ ] Testing con archivos reales
- [ ] Optimización de performance
- [ ] Documentación de uso

---

## 🎓 Referencias y Recursos

### Documentación:
- [React Data Grid Docs](https://adazzle.github.io/react-data-grid/)
- [React Data Grid Examples](https://adazzle.github.io/react-data-grid/#/common-features)

### Tutoriales:
- [Editable Data Grid with React](https://medium.com/@contact_93940/building-an-editable-data-grid-with-react-data-grid-5d5e9e0e5d8f)
- [Cell Validation in Data Grids](https://blog.logrocket.com/react-data-grid-tutorial-examples/)

---

## 🔧 Troubleshooting

### Problema: Performance lenta con muchas filas
**Solución**: 
- Usar virtualización (viene por defecto)
- Limitar a 1000 filas máximo
- Debounce en validaciones

### Problema: Validaciones lentas
**Sol**: 
```typescript
const debouncedValidate = useMemo(
  () => debounce(validateUser, 300),
  []
);
```

### Problema: Celdas no se actualizan
**Sol**: Asegurar que `rowKeyGetter` use un ID único y estable

---

## 📌 Notas Finales

Este editor editable es una **feature avanzada** que mejorará significativamente la UX, pero requiere tiempo de implementación. El preview actual (solo lectura) es funcional y suficiente para un MVP.

**Priorización recomendada**:
1. ✅ Completar y probar preview actual (ya hecho)
2. ✅ Probar con datos reales y Excel correcto
3. ⏳ Implementar editor editable cuando:
   - Feature actual esté estable
   - Haya feedback de usuarios reales
   - Se justifique la inversión de tiempo

---

**Creado**: 2025-12-17  
**Estimación**: 12-18 horas de desarrollo  
**Complejidad**: Media-Alta
