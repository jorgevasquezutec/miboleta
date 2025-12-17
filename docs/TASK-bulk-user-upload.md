# Task: Carga Masiva de Usuarios

**Fecha:** 16 de diciembre de 2025  
**Estado:** Análisis  
**Prioridad:** Media

## 1. Objetivo

Implementar una funcionalidad que permita cargar usuarios de manera masiva mediante archivos Excel, incluyendo la capacidad de:
- Generar un template Excel desde el backend
- Asignar múltiples organizaciones (tenants) a cada usuario
- Asignar supervisores por organización
- Previsualizar y editar los datos antes de confirmar la carga

## 2. Requisitos Funcionales

### 2.1 Campos del Usuario

#### Campos Requeridos:
- **Nombre** (name)
- **Apellido** (last_name)
- **Email** (único en el sistema)
- **Tipo de documento** (dni, ce, passport, ruc)
- **Número de documento**
- **Rol** (admin, employee, supervisor, root)
- **Estado** (active, inactive)

#### Campos Opcionales:
- **Teléfono** (phone)

#### Campos Complejos:
- **Organizaciones**: Lista de RUCs de las empresas a las que pertenece
- **Supervisores por organización**: Asignación de supervisor para cada empresa

### 2.2 Generación de Template Excel con Configuración

**Flujo mejorado:**

1. **Usuario abre modal de configuración**
2. **Selecciona número de organizaciones por usuario** (default: 1, máx: 5)
3. **Opcional: Pre-selecciona organizaciones específicas** (si solo cargará usuarios de ciertas empresas)
4. **Backend genera template personalizado** con validaciones

**Endpoint Backend:** `POST /api/users/bulk-upload/template`

```json
{
  "max_organizations": 2,  // Genera columnas org1 y org2 solamente
  "organization_ids": [1, 3, 5]  // Opcional: filtrar solo estas empresas
}
```

**Response:** Excel file con:
1. **Hoja 1: "Usuarios"** - Columnas dinámicas según max_organizations
2. **Hoja 2: "Catálogo_Organizaciones"** - RUCs y nombres (filtrados si aplica)
3. **Hoja 3: "Catálogo_Supervisores"** - Supervisores por organización (filtrados)
4. **Hoja 4: "Validaciones"** - Listas desplegables con datos reales del backend
5. **Hoja 5: "Instrucciones"** - Guía de llenado

**Lo que hace especial este template:**
- ✅ **Listas desplegables reales** para org_ruc (de la BD)
- ✅ **Listas de supervisores dinámicas** por organización
- ✅ **Validación de Excel nativa** (no permite valores inválidos)
- ✅ **Template optimizado** (solo columnas necesarias)

### 2.3 Preview y Edición en Frontend

Mostrar tabla editable con:
- Datos del usuario
- Validaciones en tiempo real
- Errores marcados en rojo
- Posibilidad de editar antes de confirmar
- Confirmación final para procesar

## 3. Análisis del Problema: Múltiples Organizaciones con Supervisores

### 3.1 Desafío Principal

En Excel no es trivial representar relaciones muchos-a-muchos donde cada relación tiene atributos adicionales (supervisor_id).

### 3.2 Propuestas de Solución

#### **OPCIÓN 1: Formato de Columnas Múltiples (RECOMENDADA)**

Crear columnas para hasta N organizaciones (ej. 3):

```
| Nombre | Apellido | Email | ... | Org1_RUC | Org1_Supervisor_Email | Org2_RUC | Org2_Supervisor_Email | Org3_RUC | Org3_Supervisor_Email |
|--------|----------|-------|-----|----------|---------------------|----------|---------------------|----------|---------------------|
| Juan   | Pérez    | j@... | ... | 20123... | super1@empresa.com  | 20456... | super2@empresa.com  |          |                     |
```

**Ventajas:**
- ✅ Simple de entender para el usuario
- ✅ Fácil de validar
- ✅ Compatible con Excel nativo
- ✅ Permite validación de listas desplegables por columna
- ✅ Template más limpio y manejable

**Desventajas:**
- ❌ Limitado a N organizaciones (3 cubre el 95% de casos)
- ❌ Desperdicia espacio si usuarios tienen pocas organizaciones

**Implementación:**
- Backend genera template con columnas `org1_ruc`, `org1_supervisor_email`, ..., `org5_ruc`, `org5_supervisor_email`
- Validación: Si `orgN_ruc` está vacío, se ignora
- Si `orgN_supervisor_email` está vacío, el usuario no tiene supervisor en esa org

---

#### **OPCIÓN 2: Formato de String Codificado**

Usar una columna con formato especial:

```
| Nombre | Apellido | Email | ... | Organizaciones_Supervisores |
|--------|----------|-------|-----|---------------------------|
| Juan   | Pérez    | j@... | ... | 20123456789:super1@mail.com,20456789012:super2@mail.com |
```

**Formato:** `RUC1:EMAIL_SUPERVISOR1,RUC2:EMAIL_SUPERVISOR2,...`
- Si no hay supervisor: `RUC1:,RUC2:super@mail.com`

**Ventajas:**
- ✅ Ilimitadas organizaciones por usuario
- ✅ Compacto en una sola columna

**Desventajas:**
- ❌ Complejo para usuarios no técnicos
- ❌ Difícil de validar en Excel
- ❌ Propenso a errores de formato
- ❌ No permite listas desplegables

---

#### **OPCIÓN 3: Archivo de Múltiples Hojas (Relacional)**

**Hoja 1: Usuarios**
```
| ID_Temp | Nombre | Apellido | Email | Tipo_Doc | Num_Doc | Rol | Estado | Teléfono |
|---------|--------|----------|-------|----------|---------|-----|--------|----------|
| U001    | Juan   | Pérez    | j@... | dni      | 12345   | employee | active | 999... |
```

**Hoja 2: Usuario_Organizaciones**
```
| ID_Temp_Usuario | RUC_Organizacion | Email_Supervisor |
|-----------------|------------------|------------------|
| U001            | 20123456789      | super1@mail.com  |
| U001            | 20456789012      | super2@mail.com  |
| U002            | 20123456789      |                  |
```

**Ventajas:**
- ✅ Ilimitadas organizaciones por usuario
- ✅ Estructura clara y relacional
- ✅ Escalable

**Desventajas:**
- ❌ Complejo para usuarios finales
- ❌ Requiere entender relaciones entre hojas
- ❌ Mayor posibilidad de errores (IDs temporales)

---

#### **OPCIÓN 4: Dos Archivos Separados**

1. **Archivo 1:** Usuarios básicos
2. **Archivo 2:** Asignaciones de organizaciones

**Ventajas:**
- ✅ Simplicidad en cada archivo

**Desventajas:**
- ❌ Dos pasos en el proceso
- ❌ Difícil de sincronizar

---

### 3.3 Recomendación Final

**OPCIÓN 1: Columnas Múltiples (hasta 3 organizaciones)**

**Justificación:**
1. La mayoría de usuarios pertenecen a 1-2 organizaciones
2. 3 organizaciones cubre más del 95% de casos reales
3. Template más limpio y fácil de manejar
4. Simple para usuarios no técnicos
5. Permite validaciones en Excel (listas desplegables)
6. Fácil de implementar en backend y frontend
7. Preview editable más sencillo

**Para casos con más de 3 organizaciones:**
- Repetir el usuario en múltiples filas (cada fila = hasta 3 orgs)
- O permitir carga manual adicional después

### 3.4 Manejo de Más de 3 Organizaciones

#### **Método 1: Repetir Filas (RECOMENDADO)**

Si un usuario necesita pertenecer a 5 organizaciones, simplemente se repite en múltiples filas:

```excel
| Nombre | Apellido | Email           | ... | org1_ruc    | org1_super      | org2_ruc    | org2_super      | org3_ruc    | org3_super      |
|--------|----------|-----------------|-----|-------------|-----------------|-------------|-----------------|-------------|-----------------|
| Pedro  | García   | pedro@mail.com  | ... | 20111111111 | super1@mail.com | 20222222222 | super2@mail.com | 20333333333 |                 |
| Pedro  | García   | pedro@mail.com  | ... | 20444444444 | super4@mail.com | 20555555555 |                 |             |                 |
```

**Cómo funciona:**

1. **El backend detecta email duplicado** → Identifica que es el mismo usuario
2. **Valida que los datos básicos coincidan:**
   - Nombre, apellido, tipo_documento, numero_documento deben ser idénticos
   - Si hay diferencias → ERROR (datos inconsistentes)
3. **Consolida organizaciones:**
   - Usuario final tendrá las 5 organizaciones (org1-5)
   - Con sus respectivos supervisores
4. **Crea/actualiza una sola vez** → Un solo registro de usuario

**Ventajas:**
- ✅ Ilimitadas organizaciones
- ✅ Usa el mismo formato simple
- ✅ No requiere formato especial
- ✅ Fácil de entender

**Validación importante:**
```
SI email duplicado ENTONCES
  VALIDAR que coincidan:
    - nombre
    - apellido  
    - tipo_documento
    - numero_documento
    - rol
    - estado
  SI NO coinciden ENTONCES
    ERROR: "Datos inconsistentes para email duplicado en filas X y Y"
  SI coinciden ENTONCES
    CONSOLIDAR organizaciones de ambas filas
FIN
```

#### **Método 2: Columna Especial (AVANZADO)**

Para usuarios avanzados, agregar una columna opcional `organizaciones_adicionales`:

```
organizaciones_adicionales: 20444444444:super4@mail.com,20555555555:super5@mail.com
```

**Formato:** `RUC1:EMAIL_SUPER1,RUC2:EMAIL_SUPER2`
- Si no hay supervisor: `RUC1:,RUC2:super@mail.com`

Esta columna se procesa DESPUÉS de org1-3, agregando organizaciones extras.

**Ventajas:**
- ✅ Todo en una fila
- ✅ Ilimitadas organizaciones

**Desventajas:**
- ❌ Más complejo
- ❌ Propenso a errores de formato
- ❌ Solo para usuarios técnicos

#### **Método 3: Carga Manual Posterior**

1. Cargar usuarios básicos con 1-3 organizaciones
2. Luego en la interfaz, ir a "Editar Usuario"
3. Agregar organizaciones adicionales manualmente

**Ventajas:**
- ✅ Simple y seguro
- ✅ Sin límites

**Desventajas:**
- ❌ Requiere trabajo manual adicional
- ❌ Dos pasos en lugar de uno

#### **Recomendación Final**

**Usar Método 1 (Repetir Filas)** porque:
1. Mantiene simplicidad del Excel
2. No requiere aprender formatos especiales
3. Soporta casos ilimitados
4. Validación robusta de consistencia
5. Fácil de explicar en instrucciones

## 4. Estructura del Template Excel

### Hoja 1: "Usuarios"

| Campo | Tipo | Requerido | Validación | Ejemplo |
|-------|------|-----------|------------|---------|
| nombre | texto | Sí | 2-50 caracteres | Juan |
| apellido | texto | Sí | 2-50 caracteres | Pérez García |
| email | email | Sí | formato email válido | juan.perez@empresa.com |
| tipo_documento | lista | Sí | dni, ce, passport, ruc | dni |
| numero_documento | texto | Sí | según tipo | 12345678 |
| rol | lista | Sí | admin, employee, supervisor, root | employee |
| estado | lista | Sí | active, inactive | active |
| telefono | texto | No | - | +51 999 999 999 |
| org1_ruc | número | No | 11 dígitos | 20123456789 |
| org1_supervisor_email | email | No | formato email | supervisor@empresa.com |
| org2_ruc | número | No | 11 dígitos | 20456789012 |
| org2_supervisor_email | email | No | formato email | super2@empresa.com |
| org3_ruc | número | No | 11 dígitos | 20789012345 |
| org3_supervisor_email | email | No | formato email | super3@empresa.com |

### Hoja 2: "Catálogo_Organizaciones"

Lista de referencia (generada dinámicamente desde BD):

| RUC | Nombre Empresa | Activa | Supervisores Disponibles |
|-----|----------------|--------|-------------------------|
| 20123456789 | Empresa ABC SAC | Sí | 3 |
| 20456789012 | Empresa XYZ EIRL | Sí | 2 |
| 20789012345 | Tech Solutions SAC | Sí | 1 |

### Hoja 3: "Catálogo_Supervisores"

Lista de supervisores por organización (generada dinámicamente):

| RUC_Organizacion | Email_Supervisor | Nombre_Completo | Activo |
|------------------|------------------|-----------------|--------|
| 20123456789 | super1@abc.com | Carlos Supervisor | Sí |
| 20123456789 | super2@abc.com | María Jefa | Sí |
| 20456789012 | super3@xyz.com | Pedro Manager | Sí |
| 20789012345 | admin@tech.com | Ana Admin | Sí |

### Hoja 4: "Validaciones" (Oculta)

Hoja técnica con named ranges para listas desplegables:
- `lista_rucs`: Todos los RUCs disponibles
- `lista_supervisores_org1`: Supervisores de org 20123456789
- `lista_supervisores_org2`: Supervisores de org 20456789012
- etc.

### Hoja 5: "Instrucciones"

Guía paso a paso con capturas y ejemplos.

## 5. Flujo de Trabajo

### 5.1 Generación de Template con Modal de Configuración

```
Usuario Admin → Clic "Descargar Template"
              → Se abre MODAL con:
                 
                 ┌─────────────────────────────────────────────┐
                 │  Configurar Template de Carga Masiva       │
                 ├─────────────────────────────────────────────┤
                 │                                             │
                 │  ¿Cuántas organizaciones por usuario?       │
                 │  [▼ 1 ] (default)  ◯1  ◯2  ◯3  ◯4  ◯5     │
                 │                                             │
                 │  Filtrar por organizaciones específicas:    │
                 │  (opcional - dejar vacío para todas)        │
                 │  ┌───────────────────────────────────────┐  │
                 │  │ ☑ Empresa ABC SAC (20123456789)      │  │
                 │  │ ☐ Empresa XYZ EIRL (20456789012)     │  │
                 │  │ ☑ Tech Solutions SAC (20789012345)   │  │
                 │  └───────────────────────────────────────┘  │
                 │                                             │
                 │  Vista previa del template:                 │
                 │  📄 Columnas: nombre, apellido, email...   │
                 │      + org1_ruc, org1_supervisor (con      │
                 │        listas desplegables)                 │
                 │                                             │
                 │  [ Cancelar ]  [ Generar y Descargar 📥 ]  │
                 └─────────────────────────────────────────────┘
              
              → Usuario configura y clic "Generar"
              → Backend genera Excel personalizado:
                 - Columnas dinámicas (solo las necesarias)
                 - Listas desplegables con RUCs reales
                 - Listas de supervisores por organización
                 - Validaciones de Excel nativas
                 - Formato condicional
              → Descarga "template_usuarios_[orgCount]orgs_[fecha].xlsx"
```

### 5.2 Llenado de Datos (Experiencia Mejorada)

```
Usuario → Abre Excel generado
        → Llena datos de usuarios:
           - Escribe: nombre, apellido, email, documento...
           - Para org_ruc: Clic en celda → Se abre LISTA DESPLEGABLE
             ┌────────────────────────────────────┐
             │ 20123456789 - Empresa ABC SAC      │ ← Selecciona
             │ 20456789012 - Empresa XYZ EIRL     │
             │ 20789012345 - Tech Solutions SAC   │
             └────────────────────────────────────┘
           
           - Para supervisor_email: Clic → LISTA FILTRADA por org
             ┌────────────────────────────────────┐
             │ (vacío - sin supervisor)           │
             │ carlos.super@empresa.com           │ ← Selecciona
             │ maria.jefa@empresa.com             │
             └────────────────────────────────────┘
        
        → Excel valida automáticamente:
           ❌ No permite escribir RUC inválido
           ❌ No permite supervisor que no existe
           ✅ Solo permite valores de las listas
        
        → Guarda archivo "usuarios_carga_[fecha].xlsx"
```

### 5.3 Carga y Preview

```
Usuario → Sube archivo a la plataforma
        → Backend valida estructura del archivo
        → Backend parsea datos
        → Backend valida cada registro:
           - Campos requeridos
           - Formato de datos
           - Email único
           - Organizaciones existen
           - Supervisores existen y pertenecen a la organización
        → Frontend muestra preview con:
           - Tabla editable
           - Errores marcados en rojo
           - Warnings en amarillo
           - Válidos en verde
        → Usuario puede:
           - Editar celdas con errores
           - Eliminar filas
           - Re-validar
```

### 5.4 Confirmación y Procesamiento por Chunks

```
Usuario → Clic "Confirmar Carga"
        → Backend inicia procesamiento por chunks:
        
        ┌─────────────────────────────────────────────┐
        │  Procesando Carga Masiva...                 │
        │                                             │
        │  ████████████░░░░░░░░░░░░░░░░  45%         │
        │                                             │
        │  Chunk 3 de 7 (150/350 usuarios)           │
        │  ✅ Creados: 89                             │
        │  🔄 Actualizados: 45                        │
        │  ❌ Errores: 16                             │
        │                                             │
        │  Tiempo estimado: 2 min                     │
        └─────────────────────────────────────────────┘
        
        → Backend procesa en chunks de 50 usuarios:
           Chunk 1 (usuarios 1-50):
             1. Crear/actualizar usuarios
             2. Asignar organizaciones (tenant_user)
             3. Asignar supervisores
             4. Commit transacción
             5. Retornar progreso
           
           Chunk 2 (usuarios 51-100):
             ... mismo proceso
           
           Chunk N (usuarios 301-350):
             ... mismo proceso
        
        → Frontend actualiza progress bar en tiempo real
        → Al finalizar, muestra resumen completo:
           ✅ Usuarios creados: 189
           🔄 Usuarios actualizados: 145
           ❌ Errores: 16 (con detalle descargable)
           ⏱️ Tiempo total: 4 min 23 seg
```

## 6. Diseño Técnico

### 6.1 Backend (Laravel)

#### Endpoints

```php
// Obtener configuración para el modal
GET /api/users/bulk-upload/config
Response: {
  organizations: [
    { id: 1, ruc: "20123456789", name: "Empresa ABC SAC", supervisors_count: 3 },
    { id: 2, ruc: "20456789012", name: "Empresa XYZ EIRL", supervisors_count: 2 }
  ],
  supervisors_by_org: {
    "1": [
      { id: 5, email: "super1@abc.com", full_name: "Carlos Supervisor" },
      { id: 8, email: "super2@abc.com", full_name: "María Jefa" }
    ],
    "2": [...]
  },
  max_organizations_limit: 5,
  default_organizations: 1
}

// Generar template personalizado
POST /api/users/bulk-upload/template
Request: {
  max_organizations: 2,  // Número de columnas de orgs
  organization_ids: [1, 3]  // Opcional: filtrar solo estas
}
Response: Excel file (binary) con listas desplegables precargadas

// Subir y validar archivo
POST /api/users/bulk-upload/validate
Request: multipart/form-data con archivo Excel
Response: {
  valid: boolean,
  data: [...], // Array de usuarios parseados
  errors: [...], // Errores por fila
  warnings: [...] // Warnings
}

// Confirmar carga masiva (procesa por chunks)
POST /api/users/bulk-upload/confirm
Request: { 
  users: [...], // Array de usuarios validados
  chunk_size: 50  // Opcional, default 50
}
Response (streaming): {
  type: 'progress' | 'complete' | 'error',
  chunk: number,
  total_chunks: number,
  processed: number,
  total: number,
  created: number,
  updated: number,
  errors: [...],
  percentage: number
}

// Alternativa con Job asíncrono
POST /api/users/bulk-upload/confirm-async
Request: { users: [...] }
Response: {
  job_id: "uuid",
  status: "processing"
}

GET /api/users/bulk-upload/status/{job_id}
Response: {
  status: 'processing' | 'completed' | 'failed',
  progress: {
    chunk: 3,
    total_chunks: 7,
    processed: 150,
    total: 350,
    created: 89,
    updated: 45,
    errors: 16,
    percentage: 45
  }
}
```

#### Clases y Servicios

```php
// app/Services/BulkUserUploadService.php
class BulkUserUploadService
{
    public function getConfigData(): array
    public function generateTemplate(int $maxOrgs, ?array $orgIds): BinaryFileResponse
    public function validateFile(UploadedFile $file): array
    public function processUsersInChunks(array $users, int $chunkSize = 50): Generator
    public function consolidateDuplicateUsers(array $users): array
}

// app/Exports/UsersTemplateExport.php
class UsersTemplateExport implements WithMultipleSheets
{
    private int $maxOrganizations;
    private array $organizations;
    private array $supervisors;
    
    public function __construct(int $maxOrgs, array $orgIds = null)
    
    public function sheets(): array
    {
        return [
            new UsersSheetTemplate($this->maxOrganizations),
            new OrganizationsCatalogSheet($this->organizations),
            new SupervisorsCatalogSheet($this->supervisors),
            new ValidationRulesSheet($this->organizations, $this->supervisors), // Listas desplegables
            new InstructionsSheet(),
        ];
    }
}

// app/Imports/UsersImport.php
class UsersImport implements ToCollection, WithHeadingRow, WithValidation, WithChunkReading
{
    public function collection(Collection $rows)
    public function rules(): array
    public function chunkSize(): int { return 100; } // Lee en chunks
}

// app/Jobs/ProcessBulkUserUpload.php (Para procesamiento asíncrono)
class ProcessBulkUserUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    private array $users;
    private string $jobId;
    
    public function handle()
    {
        foreach ($this->service->processUsersInChunks($this->users, 50) as $result) {
            // Actualizar progreso en cache/DB
            Cache::put("bulk_upload:{$this->jobId}:progress", $result);
            
            // Emitir evento para WebSocket (opcional)
            broadcast(new BulkUploadProgress($this->jobId, $result));
        }
    }
}

// app/Http/Controllers/Api/BulkUserUploadController.php
class BulkUserUploadController extends Controller
{
    public function getConfig()
    public function downloadTemplate(Request $request)
    public function validateUpload(Request $request)
    public function confirmUpload(Request $request) // Síncrono con streaming
    public function confirmUploadAsync(Request $request) // Asíncrono con Job
    public function getUploadStatus(string $jobId) // Consultar progreso
}
```

#### Validaciones

```php
class BulkUserValidator
{
    // Validar estructura del archivo
    - Columnas requeridas presentes
    - Tipos de datos correctos
    
    // Validar cada usuario
    - Email único (en archivo y en BD)
    - Número documento único por tipo
    - Organizaciones existen
    - Supervisores existen en las organizaciones correctas
    - Rol válido
    - Estado válido
    
    // Validar relaciones
    - Si org_ruc está lleno, debe existir en BD
    - Si supervisor_email está lleno, debe existir y pertenecer a org_ruc
    - Un usuario no puede ser supervisor de sí mismo
}
```

### 6.2 Frontend (React + TypeScript)

#### Componentes

```typescript
// src/presentation/pages/admin/BulkUserUploadPage.tsx
- Botón "Configurar y Descargar Template"
- Modal de configuración
- Drag & drop para subir archivo
- Preview de datos con tabla editable
- Botón confirmar carga

// src/presentation/components/admin/TemplateConfigModal.tsx
- Selector de número de organizaciones (1-5)
- Multi-selector de organizaciones específicas (opcional)
- Preview de columnas que se generarán
- Botón "Generar y Descargar"
- Loading state durante generación

// src/presentation/components/admin/BulkUploadPreview.tsx
- Tabla con react-table o similar
- Edición inline de celdas
- Indicadores visuales (error, warning, ok)
- Filtros por estado de validación
- Export de errores

// src/presentation/components/admin/BulkUploadStats.tsx
- Resumen de validación
- Total filas
- Válidas / Con errores / Con warnings

// src/presentation/components/admin/BulkUploadProgress.tsx
- Barra de progreso animada
- Chunk actual / Total chunks
- Procesados / Total
- Tiempo estimado restante
- Botón cancelar (si es asíncrono)
```

#### Tipos

```typescript
// Configuración del template
interface TemplateConfig {
  max_organizations: number; // 1-5
  organization_ids?: number[]; // Filtro opcional
}

interface TemplateConfigData {
  organizations: Array<{
    id: number;
    ruc: string;
    name: string;
    supervisors_count: number;
  }>;
  supervisors_by_org: Record<string, Array<{
    id: number;
    email: string;
    full_name: string;
  }>>;
  max_organizations_limit: number;
  default_organizations: number;
}

// Datos del usuario
interface BulkUserData {
  rowNumber: number; // Número de fila en Excel
  nombre: string;
  apellido: string;
  email: string;
  tipo_documento: 'dni' | 'ce' | 'passport' | 'ruc';
  numero_documento: string;
  rol: 'admin' | 'employee' | 'supervisor' | 'root';
  estado: 'active' | 'inactive';
  telefono?: string;
  organizaciones: Array<{
    ruc: string;
    supervisor_email?: string;
  }>;
  validationStatus: 'valid' | 'error' | 'warning';
  validationErrors: string[];
  validationWarnings: string[];
}

interface BulkUploadValidationResponse {
  valid: boolean;
  data: BulkUserData[];
  errors: Array<{
    row: number;
    field: string;
    message: string;
  }>;
  warnings: Array<{
    row: number;
    field: string;
    message: string;
  }>;
  summary: {
    total: number;
    valid: number;
    errors: number;
    warnings: number;
  };
}
```

## 7. Casos de Uso y Ejemplos

### Ejemplo 1: Usuario Simple (1 organización, con supervisor)

```
nombre: Juan
apellido: Pérez García
email: juan.perez@empresa.com
tipo_documento: dni
numero_documento: 12345678
rol: employee
estado: active
telefono: 999888777
org1_ruc: 20123456789
org1_supervisor_email: carlos.super@empresa.com
org2_ruc: (vacío)
org2_supervisor_email: (vacío)
...
```

### Ejemplo 2: Usuario Multi-Tenant (3 organizaciones, 2 con supervisor)

```
nombre: María
apellido: López Sánchez
email: maria.lopez@multi.com
tipo_documento: dni
numero_documento: 87654321
rol: employee
estado: active
telefono: (vacío)
org1_ruc: 20123456789
org1_supervisor_email: super1@empresa1.com
org2_ruc: 20456789012
org2_supervisor_email: super2@empresa2.com
org3_ruc: 20789012345
org3_supervisor_email: (vacío) // Sin supervisor en esta org
```

### Ejemplo 3: Supervisor (pertenece a 1 org, no tiene supervisor)

```
nombre: Carlos
apellido: Supervisor Jefe
email: carlos.super@empresa.com
tipo_documento: dni
numero_documento: 11223344
rol: supervisor
estado: active
telefono: 988776655
org1_ruc: 20123456789
org1_supervisor_email: (vacío) // Los supervisores no tienen supervisor
org2_ruc: (vacío)
...
```

### Ejemplo 4: Usuario con 5 Organizaciones (Filas Repetidas)

**Fila 1:**
```
nombre: Pedro
apellido: García Multi
email: pedro.garcia@multi.com
tipo_documento: dni
numero_documento: 99887766
rol: employee
estado: active
telefono: 987654321
org1_ruc: 20111111111
org1_supervisor_email: super1@empresa1.com
org2_ruc: 20222222222
org2_supervisor_email: super2@empresa2.com
org3_ruc: 20333333333
org3_supervisor_email: (vacío)
```

**Fila 2 (mismo usuario):**
```
nombre: Pedro
apellido: García Multi
email: pedro.garcia@multi.com
tipo_documento: dni
numero_documento: 99887766
rol: employee
estado: active
telefono: 987654321
org1_ruc: 20444444444
org1_supervisor_email: super4@empresa4.com
org2_ruc: 20555555555
org2_supervisor_email: super5@empresa5.com
org3_ruc: (vacío)
```

**Resultado:** Backend detecta email duplicado, valida consistencia y consolida en 1 usuario con 5 organizaciones.

## 8. Validaciones Específicas

### 8.1 Validaciones a Nivel de Archivo

- ✅ Archivo es .xlsx válido
- ✅ Contiene hoja "Usuarios"
- ✅ Tiene todas las columnas requeridas
- ✅ No excede 1000 filas (límite configurable)

### 8.2 Validaciones a Nivel de Fila

#### Errores (bloquean la carga):
- ❌ Email inválido o duplicado
- ❌ Campos requeridos vacíos
- ❌ Tipo documento inválido
- ❌ Rol inválido
- ❌ Estado inválido
- ❌ RUC no existe en sistema
- ❌ Email supervisor no existe
- ❌ Supervisor no pertenece a la organización indicada
- ❌ Formato de RUC inválido (no 11 dígitos)

#### Warnings (permiten carga pero alertan):
- ⚠️ Teléfono en formato no estándar
- ⚠️ Usuario ya existe (se actualizará)
- ⚠️ Organización sin supervisor asignado
- ⚠️ Nombre/apellido con caracteres especiales
- ⚠️ Email duplicado en archivo (se consolidarán organizaciones)

### 8.3 Validaciones para Filas Duplicadas

Cuando se detecta email duplicado en el archivo:

**Validaciones de Consistencia (ERROR si no coinciden):**
- ✅ nombre debe ser idéntico
- ✅ apellido debe ser idéntico
- ✅ tipo_documento debe ser idéntico
- ✅ numero_documento debe ser idéntico
- ✅ rol debe ser idéntico
- ✅ estado debe ser idéntico

**Campos que pueden diferir (se usa el primer valor):**
- 📝 telefono (se toma de la primera fila)

**Consolidación de Organizaciones:**
- 🔄 Se combinan todas las organizaciones de todas las filas
- 🔄 Si una organización se repite → ERROR (org duplicada para mismo usuario)
- 🔄 Se mantienen todos los supervisores respectivos

**Ejemplo de error:**
```
Fila 5: Pedro García, pedro@mail.com, org1_ruc: 20111111111
Fila 12: Pedro García, pedro@mail.com, org1_ruc: 20111111111
ERROR: "Organización 20111111111 duplicada para usuario pedro@mail.com en filas 5 y 12"
```

### 8.4 Validaciones de Negocio

- Usuario root solo puede pertenecer a organización con ID=1
- Admin debe pertenecer al menos a 1 organización
- Employee debe pertenecer al menos a 1 organización
- Supervisor debe pertenecer al menos a 1 organización
- Un usuario no puede ser supervisor de sí mismo
- Email supervisor debe ser de un usuario con rol 'supervisor'

## 9. Mensajes de Error Comunes

```typescript
const ERROR_MESSAGES = {
  REQUIRED_FIELD: 'Campo requerido',
  INVALID_EMAIL: 'Email inválido',
  DUPLICATE_EMAIL: 'Email ya existe en el sistema',
  DUPLICATE_EMAIL_IN_FILE: 'Email duplicado en el archivo',
  INVALID_DOCUMENT_TYPE: 'Tipo de documento inválido (dni, ce, passport, ruc)',
  INVALID_ROLE: 'Rol inválido (admin, employee, supervisor, root)',
  INVALID_STATUS: 'Estado inválido (active, inactive)',
  ORGANIZATION_NOT_FOUND: 'Organización no encontrada (RUC: {ruc})',
  SUPERVISOR_NOT_FOUND: 'Supervisor no encontrado (Email: {email})',
  SUPERVISOR_NOT_IN_ORG: 'Supervisor no pertenece a la organización',
  INVALID_RUC_FORMAT: 'RUC debe tener 11 dígitos',
  SUPERVISOR_EMAIL_WITHOUT_RUC: 'Se especificó supervisor sin organización',
  MAX_ROWS_EXCEEDED: 'El archivo excede el límite de {max} filas',
};
```

## 10. Consideraciones de Seguridad

- ✅ Solo usuarios con rol 'root' o 'admin' pueden usar esta funcionalidad
- ✅ Validar permisos antes de descargar template
- ✅ Validar permisos antes de procesar carga
- ✅ Limitar tamaño de archivo (10 MB máximo)
- ✅ Limitar número de filas (1000 máximo)
- ✅ Sanitizar datos de entrada
- ✅ Log de todas las cargas masivas (quién, cuándo, cuántos)
- ✅ Transacciones para atomicidad (todo o nada)

## 11. Performance y Procesamiento por Chunks

### 11.1 Estrategia de Chunks

#### Lectura del Archivo (Import)
- **Chunk size: 100 filas** usando `WithChunkReading`
- Evita cargar todo el archivo en memoria
- Validación progresiva

#### Procesamiento de Usuarios (Confirm)

**Opción 1: Procesamiento Síncrono con Streaming (< 200 usuarios)**
```php
public function processUsersInChunks(array $users, int $chunkSize = 50): Generator
{
    $chunks = array_chunk($users, $chunkSize);
    $processed = 0;
    
    foreach ($chunks as $index => $chunk) {
        DB::beginTransaction();
        try {
            $result = $this->processChunk($chunk);
            DB::commit();
            
            $processed += count($chunk);
            
            yield [
                'type' => 'progress',
                'chunk' => $index + 1,
                'total_chunks' => count($chunks),
                'processed' => $processed,
                'total' => count($users),
                'percentage' => round(($processed / count($users)) * 100),
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors' => $result['errors'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            yield ['type' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

**Opción 2: Procesamiento Asíncrono con Queue (> 200 usuarios)**
```php
// Despachar Job
$jobId = Str::uuid();
ProcessBulkUserUpload::dispatch($users, $jobId);

// Frontend consulta progreso cada 2 segundos
GET /api/users/bulk-upload/status/{jobId}
```

### 11.2 Límites y Configuración

| Métrica | Valor | Configurable |
|---------|-------|--------------|
| Max filas por archivo | 1000 | Sí (env: BULK_UPLOAD_MAX_ROWS) |
| Chunk size de lectura | 100 | Sí |
| Chunk size de procesamiento | 50 | Sí |
| Timeout síncrono | 5 min | Sí |
| Umbral para Job asíncrono | 200 usuarios | Sí |
| Max tamaño archivo | 10 MB | Sí |

### 11.3 Optimizaciones

- ✅ **Transacciones por chunk**: Si falla un chunk, no afecta anteriores
- ✅ **Cache de catálogos**: Organizaciones y supervisores en memoria
- ✅ **Eager loading**: Pre-cargar relaciones para evitar N+1
- ✅ **Bulk inserts**: `User::insert()` en lugar de loops
- ✅ **Índices de BD**: En email, documento, RUC
- ✅ **Progress tracking**: Cache o DB para consultas de estado
- ✅ **Retry logic**: Reintentar chunks fallidos

### 11.4 Ejemplo de Tiempos Estimados

| Usuarios | Chunks | Tiempo Estimado | Método |
|----------|--------|-----------------|--------|
| 50 | 1 | 5-10 seg | Síncrono |
| 100 | 2 | 15-20 seg | Síncrono |
| 200 | 4 | 40-60 seg | Síncrono |
| 500 | 10 | 2-3 min | Asíncrono (recomendado) |
| 1000 | 20 | 5-7 min | Asíncrono |

## 12. Roadmap de Implementación

### Fase 1: Backend Config & Template Generation ✅
1. Crear BulkUserUploadService
2. Endpoint GET /config (datos para modal)
3. Crear exporters dinámicos (UsersTemplateExport + sheets)
4. Endpoint POST /template con configuración
5. Generar Excel con:
   - Columnas dinámicas
   - Named ranges para listas desplegables
   - Validaciones de Excel con datos de BD
6. Tests unitarios

**Estimado: 3-4 días**

### Fase 2: Backend File Validation ✅
1. Crear UsersImport
2. Implementar validaciones
3. Endpoint POST /validate
4. Tests unitarios de validación

**Estimado: 3-4 días**

### Fase 3: Backend Processing ✅
1. Método processUsers en service
2. Crear/actualizar usuarios en lotes
3. Asignar organizaciones y supervisores
4. Endpoint POST /confirm
5. Tests de integración

**Estimado: 2-3 días**

### Fase 4: Frontend Modal & Template Download ✅
1. Página BulkUserUploadPage
2. Componente TemplateConfigModal:
   - Selector de número de organizaciones
   - Multi-selector de empresas (con datos de /config)
   - Preview de columnas
   - Botón generar y descargar
3. Llamada a POST /template
4. Descarga de archivo

**Estimado: 2-3 días**

### Fase 4.5: Frontend Upload & Preview ✅
1. Componente upload con drag & drop
2. Llamada a /validate
3. Mostrar preview básico

**Estimado: 1-2 días**

### Fase 5: Frontend Editable Table ✅
1. Tabla editable con react-table
2. Validación inline
3. Indicadores visuales
4. Filtros y búsqueda

**Estimado: 3-4 días**

### Fase 6: Procesamiento por Chunks ✅
1. Backend: Implementar processUsersInChunks con Generator
2. Backend: Crear Job asíncrono ProcessBulkUserUpload
3. Backend: Sistema de tracking de progreso (Cache/DB)
4. Endpoints /confirm (síncrono) y /confirm-async + /status
5. Tests de procesamiento en chunks

**Estimado: 2-3 días**

### Fase 7: Frontend Progress & Feedback ✅
1. Componente BulkUploadProgress
2. Llamada a /confirm con streaming o polling
3. Progress bar en tiempo real
4. Resumen de resultados
5. Manejo de errores por chunk
6. Botón cancelar (opcional)

**Estimado: 2-3 días**

### Fase 8: Testing & Refinamiento ✅
1. Tests E2E con archivos grandes (500+ usuarios)
2. Tests de chunks y transacciones
3. Optimizaciones de performance
4. UX improvements
5. Documentación de usuario

**Estimado: 2-3 días**

**Total Estimado: 18-27 días de desarrollo**

## 12.1 Ventajas del Modal de Configuración

### Beneficios para el Usuario:
- ✅ **Template optimizado**: Solo genera columnas que se usarán
- ✅ **Menos errores**: Listas desplegables con datos reales (no escribir RUCs)
- ✅ **Validación nativa**: Excel no permite valores inválidos
- ✅ **Más rápido**: Seleccionar de lista vs escribir
- ✅ **Supervisores correctos**: Lista filtrada por organización automáticamente

### Beneficios Técnicos:
- ✅ **Datos sincronizados**: Listas desde BD en tiempo real
- ✅ **Menos validaciones en backend**: Excel ya valida
- ✅ **Flexible**: Soporta 1-5 organizaciones dinámicamente
- ✅ **Filtrado opcional**: Template para empresas específicas

### Ejemplo de Diferencia:

**Antes (template estático):**
```excel
org1_ruc: [usuario escribe] 20123456789  ← Puede haber errores de tipeo
org1_supervisor: [usuario escribe] super@empresa.com  ← Puede no existir
```

**Después (template dinámico con modal):**
```excel
org1_ruc: [clic en celda]
          ┌────────────────────────────────────┐
          │ 20123456789 - Empresa ABC SAC      │ ← Solo opciones válidas
          │ 20456789012 - Empresa XYZ EIRL     │
          └────────────────────────────────────┘

org1_supervisor: [clic en celda, filtrado por org1]
                 ┌────────────────────────────────┐
                 │ (sin supervisor)               │ ← Supervisores reales
                 │ carlos.super@empresa.com       │   de esa empresa
                 │ maria.jefa@empresa.com         │
                 └────────────────────────────────┘
```

## 13. Preguntas Abiertas

1. **¿Enviar emails de bienvenida automáticamente?**
   - Opción A: Enviar automáticamente a todos
   - Opción B: Checkbox en el modal de confirmación
   - Opción C: No enviar, que el admin envíe manualmente después

2. **¿Qué hacer con usuarios duplicados en BD (no en archivo)?**
   - Opción A: Error y no permitir
   - Opción B: Actualizar datos existentes
   - Opción C: Preguntar al usuario en el preview (recomendado)

3. **¿Procesamiento síncrono o asíncrono?**
   - Opción A: Siempre síncrono (límite bajo de usuarios)
   - Opción B: Siempre asíncrono con Jobs
   - Opción C: Automático según cantidad (< 200 sync, >= 200 async) ⭐

3. **¿Límite de organizaciones por usuario?**
   - Propuesta: 3 (configurable)
   - Cubre más del 95% de casos reales
   - Para casos excepcionales: repetir usuario en múltiples filas

4. **¿Notificaciones al finalizar carga?**
   - Email al admin con resumen
   - Notificación in-app

## 14. Alternativas Consideradas

### Alternativa 1: CSV en lugar de Excel
**Descartada porque:**
- No permite múltiples hojas
- No permite validaciones nativas
- Menos amigable para usuarios no técnicos

### Alternativa 2: Interfaz de formulario múltiple
**Descartada porque:**
- Muy lento para cargas masivas (100+ usuarios)
- No permite preparación offline
- Requiere sesión activa todo el tiempo

### Alternativa 3: API REST directa
**Descartada porque:**
- Requiere conocimientos técnicos
- No es amigable para usuarios de negocio
- Dificulta la revisión previa

## 15. Conclusión

La solución propuesta de **columnas múltiples (hasta 3 organizaciones)** ofrece el mejor balance entre simplicidad, funcionalidad y experiencia de usuario. Este límite cubre más del 95% de casos reales mientras mantiene el template Excel limpio y manejable.

Permite a usuarios no técnicos realizar cargas masivas de manera eficiente, con validaciones robustas y preview editable antes de confirmar.

El flujo completo desde template → llenado → preview → confirmación garantiza que los datos cargados sean correctos y consistentes antes de ser persistidos en la base de datos.

**Para casos excepcionales con más de 3 organizaciones:** El usuario simplemente repite la fila del mismo usuario con las organizaciones adicionales (el sistema detectará y consolidará automáticamente).
