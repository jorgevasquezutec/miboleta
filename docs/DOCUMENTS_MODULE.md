# 📄 MÓDULO 4: Sistema de Gestión Documental

> Plan de implementación para el sistema de carga, gestión y firma de documentos.

**Última actualización:** 2025-12-11  
**Estado:** 📋 Planificado  
**Duración estimada:** 14-16 días

---

## 📋 Índice

1. [Descripción General](#descripción-general)
2. [Flujo de Carga de Documentos](#flujo-de-carga-de-documentos)
3. [Modelo de Datos](#modelo-de-datos)
4. [API Endpoints](#api-endpoints)
5. [Jobs y Procesamiento](#jobs-y-procesamiento)
6. [WebSockets - Notificaciones en Tiempo Real](#websockets---notificaciones-en-tiempo-real)
7. [Frontend - Páginas](#frontend---páginas)
8. [Fases de Implementación](#fases-de-implementación)
9. [Reglas de Negocio](#reglas-de-negocio)

---

## Descripción General

### Características Principales

- ✅ **Carga masiva** de documentos via ZIP
- ✅ **Vista previa del ZIP** antes de procesar (lista de archivos detectados)
- ✅ **Procesamiento por chunks** (50 archivos por job) optimizado
- ✅ **Historial de procesos** - cada carga queda registrada
- ✅ **Documentos huérfanos** - detección automática + asociación automática al crear empleado
- ✅ **Reemplazo automático** - si el documento ya existe (mismo DNI + tipo + período), se reemplaza
- ✅ **Opción "Requiere firma"** por cada carga (no solo por tipo)
- ✅ **Notificación opcional** por email a empleados
- ✅ **Notificaciones en tiempo real** con Laravel Reverb (WebSockets)
- ✅ **Firma digital con 2FA** (código por email) + términos y condiciones
- ✅ **Prevención de doble firma**
- ✅ **Descarga masiva** por categoría/período
- ✅ **Exportar reportes Excel**
- ✅ **Visor PDF** integrado con react-pdf

### Stack Tecnológico

| Componente | Tecnología |
|------------|------------|
| Backend | Laravel 10+ |
| Queues | Laravel Horizon + Redis |
| WebSockets | Laravel Reverb + Echo.js |
| Storage | Laravel Storage (local/S3) |
| Frontend | React + TypeScript + Zustand |
| Visor PDF | react-pdf |
| Emails | Laravel Mail |
| Exports | Laravel Excel (maatwebsite/excel) |


---

## Flujo de Carga de Documentos

```
┌──────────────────────────────────────────────────────────────────┐
│                    FRONTEND - Upload Page                         │
├──────────────────────────────────────────────────────────────────┤
│  1. Seleccionar TIPO de documento (dropdown)                     │
│     - Boleta, CTS, Liquidación, etc.                             │
│                                                                   │
│  2. Seleccionar PERÍODO (mes/año)                                │
│     - Formato: 2024-12                                           │
│                                                                   │
│  3. Drag & Drop de archivo ZIP                                   │
│     - Contenido: 12345678.pdf, 87654321.pdf, ...                │
│     - Nombre = Número de documento del empleado                  │
│                                                                   │
│  4. ☑ Checkbox "Notificar empleados por email"                  │
│                                                                   │
│  5. Click "PROCESAR"                                             │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│              BACKEND - POST /documents/upload-zip                 │
├──────────────────────────────────────────────────────────────────┤
│  1. Validar ZIP y metadata                                        │
│  2. Crear registro en `document_batches` (historial)             │
│  3. Guardar ZIP en storage temporal                              │
│  4. Dispatch Job: ProcessZipFile                                 │
│  5. Return: { batch_id, status: 'processing' }                   │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                    JOBS (Chunks de 50)                            │
├──────────────────────────────────────────────────────────────────┤
│  ProcessZipFile:                                                  │
│    - Extrae archivos del ZIP                                     │
│    - Actualiza batch.total_files                                 │
│    - Divide en chunks de 50                                      │
│    - Dispatch N jobs: ProcessDocumentChunk                       │
│                                                                   │
│  ProcessDocumentChunk (50 archivos):                              │
│    Para cada archivo (ej: 12345678.pdf):                         │
│                                                                   │
│    1. Buscar User donde document_text = "12345678" en tenant     │
│                                                                   │
│    2. Buscar documento existente:                                │
│       - Mismo tenant + tipo + período + employee_document       │
│       - Si existe → REEMPLAZAR (update file, reset firma)       │
│       - Si no existe → CREAR nuevo                              │
│                                                                   │
│    3. Si User NO encontrado → status = 'orphan'                  │
│                                                                   │
│    4. Mover PDF a estructura:                                    │
│       /storage/documents/{tenant}/{type}/{period}/{dni}.pdf     │
│                                                                   │
│    5. Actualizar métricas del batch                              │
│                                                                   │
│  Cuando todos terminan:                                           │
│    - batch.status = 'completed' / 'completed_with_errors'        │
│    - Si notify_employees → Dispatch SendBatchNotifications       │
└──────────────────────────────────────────────────────────────────┘
```

---

## Modelo de Datos

### Tabla: `document_types`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | integer | PK auto-increment |
| name | string | Nombre único (boleta, cts, etc.) |
| display_name | string | Nombre para mostrar |
| description | string? | Descripción opcional |
| requires_signature | boolean | ¿Requiere firma digital? |
| is_active | boolean | ¿Está activo? |
| created_at | timestamp | |
| updated_at | timestamp | |

**Tipos predefinidos:**
1. `boleta` → Boleta de Pago
2. `liquidacion` → Liquidación de Beneficios
3. `cts` → CTS
4. `gratificacion` → Gratificación
5. `utilidades` → Utilidades
6. `vacaciones` → Constancia de Vacaciones
7. `contrato` → Contrato de Trabajo
8. `addendum` → Addendum de Contrato

---

### Tabla: `document_batches` (Historial de Procesos)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | uuid | PK |
| tenant_id | FK | Tenant que realizó la carga |
| uploaded_by | FK | Usuario admin que subió |
| type_id | FK | Tipo de documento |
| period | string | Período (2024-12) |
| original_filename | string | Nombre del ZIP subido |
| total_files | integer | Total de archivos en ZIP |
| processed_files | integer | Archivos procesados hasta ahora |
| success_count | integer | Documentos creados/actualizados OK |
| replaced_count | integer | Documentos reemplazados |
| orphan_count | integer | Documentos huérfanos |
| error_count | integer | Errores de procesamiento |
| errors | JSON | Array de errores [{file, message}] |
| notify_employees | boolean | ¿Notificar por email? |
| notifications_sent | boolean | ¿Ya se enviaron? |
| status | enum | pending, processing, completed, completed_with_errors, failed |
| started_at | timestamp | Inicio del procesamiento |
| completed_at | timestamp? | Fin del procesamiento |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### Tabla: `documents`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | uuid | PK |
| tenant_id | FK | Tenant dueño |
| user_id | FK? | Usuario empleado (null si huérfano) |
| batch_id | FK | Batch que lo creó |
| type_id | FK | Tipo de documento |
| period | string | Período (2024-12) |
| employee_document | string | Nro documento del empleado |
| file_path | string | Ruta al archivo PDF |
| file_size | integer | Tamaño en bytes |
| original_filename | string | Nombre original en ZIP |
| status | enum | pending, signed, orphan, expired |
| signature | JSON? | Datos de firma digital |
| signed_at | timestamp? | Fecha/hora de firma |
| notified | boolean | ¿Se notificó al empleado? |
| notified_at | timestamp? | Fecha de notificación |
| version | integer | Versión del documento (para reemplazos) |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp? | Soft delete |

**Índice único:** `(tenant_id, type_id, period, employee_document)` - Asegura no duplicados

---

## API Endpoints

### Document Types

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/document-types` | Listar tipos de documento activos |

### Documents

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/documents` | Listar con filtros y paginación |
| GET | `/api/documents/{id}` | Detalle de un documento |
| GET | `/api/documents/{id}/download` | Descargar PDF |
| DELETE | `/api/documents/{id}` | Eliminar (soft delete) |
| POST | `/api/documents/{id}/sign` | Firmar documento |
| GET | `/api/documents/orphaned` | Listar documentos huérfanos |
| POST | `/api/documents/{id}/assign` | Asignar huérfano a empleado |

### Document Batches (Procesos)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/documents/upload-zip` | Subir ZIP para procesar |
| GET | `/api/documents/batches` | Historial de procesos |
| GET | `/api/documents/batches/{id}` | Detalle de proceso |
| GET | `/api/documents/batches/{id}/status` | Estado en tiempo real |
| GET | `/api/documents/batches/{id}/documents` | Documentos del batch |
| GET | `/api/documents/batches/{id}/errors` | Errores del batch |

---

## Jobs y Procesamiento

### Arquitectura de Jobs

```
ProcessZipFile (1 job)
    │
    ├── ProcessDocumentChunk (N jobs de 50 archivos cada uno)
    │
    └── [Cuando todos terminan]
            │
            └── SendBatchNotifications (si notify_employees = true)
                    │
                    └── SendDocumentNotification (chunks de 50 emails)
```

### Configuración de Horizon

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-documents' => [
            'connection' => 'redis',
            'queue' => ['documents', 'notifications'],
            'balance' => 'auto',
            'processes' => 4,
            'tries' => 3,
        ],
    ],
],
```

---

## WebSockets - Notificaciones en Tiempo Real

### Configuración Laravel Reverb

```bash
# Instalación
composer require laravel/reverb
php artisan reverb:install
```

```env
# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=miboleta
REVERB_APP_KEY=miboleta-key
REVERB_APP_SECRET=miboleta-secret
REVERB_HOST=localhost
REVERB_PORT=8080
```

### Frontend - Echo.js

```typescript
// src/infrastructure/websockets/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

### Eventos WebSocket

| Evento | Canal | Descripción | Receptor |
|--------|-------|-------------|----------|
| `DocumentProcessed` | `tenant.{id}` | Procesamiento ZIP completado | Admin |
| `ProcessingProgress` | `batch.{id}` | Progreso del procesamiento | Admin |
| `ProcessingError` | `tenant.{id}` | Error crítico en procesamiento | Admin |
| `NewDocumentAvailable` | `user.{id}` | Nuevo documento disponible | Empleado |
| `DocumentSigned` | `tenant.{id}` | Empleado firmó documento | Admin |
| `OrphanAssociated` | `user.{id}` | Documentos huérfanos asociados | Empleado |

### Backend - Broadcasting Events

```php
// app/Events/DocumentProcessed.php
class DocumentProcessed implements ShouldBroadcast
{
    public function __construct(
        public DocumentBatch $batch
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->batch->tenant_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batch->id,
            'status' => $this->batch->status,
            'success_count' => $this->batch->success_count,
            'error_count' => $this->batch->error_count,
            'orphan_count' => $this->batch->orphan_count,
        ];
    }
}
```

### Frontend - Escuchar Eventos

```typescript
// En componente React
useEffect(() => {
    const channel = echo.private(`tenant.${tenantId}`);
    
    channel.listen('DocumentProcessed', (event) => {
        toast.success(`Procesamiento completado: ${event.success_count} documentos`);
        refetchBatches();
    });

    channel.listen('ProcessingError', (event) => {
        toast.error(`Error en procesamiento: ${event.message}`);
    });

    return () => {
        channel.stopListening('DocumentProcessed');
        channel.stopListening('ProcessingError');
    };
}, [tenantId]);
```

### Características de WebSockets

- ✅ **Reconexión automática** si se pierde conexión
- ✅ **Fallback a polling** si WebSocket no disponible
- ✅ **Canales privados** autenticados por usuario/tenant
- ✅ **Indicador de conexión** visible en UI
- ✅ **Sonido opcional** para notificaciones

---


## Frontend - Páginas

### Admin

| Página | Ruta | Descripción |
|--------|------|-------------|
| DocumentsListPage | `/documents` | Lista de documentos con filtros |
| DocumentUploadPage | `/documents/upload` | Subir ZIP |
| DocumentBatchesPage | `/documents/batches` | Historial de procesos |
| DocumentBatchDetailPage | `/documents/batches/:id` | Detalle + errores + documentos |
| OrphanedDocumentsPage | `/documents/orphaned` | Documentos sin empleado |
| DocumentDetailPage | `/documents/:id` | Visor PDF + metadata |

### Empleado

| Página | Ruta | Descripción |
|--------|------|-------------|
| MyDocumentsPage | `/my-documents` | Mis documentos |
| DocumentDetailPage | `/my-documents/:id` | Ver + firmar documento |

---

## Fases de Implementación

| Fase | Descripción | Días |
|------|-------------|------|
| 0 | **Infraestructura** - Docker (Redis, Horizon) | 0.5 |
| 1 | **Base de Datos** - Migraciones, modelos, seeders | 1 |
| 2 | **Storage** - Configuración de disks y estructura | 0.5 |
| 3 | **API Básica** - CRUD de documentos y tipos | 1 |
| 4 | **Vista Previa ZIP** - Extraer y listar antes de procesar | 0.5 |
| 5 | **Carga Masiva** - Jobs de procesamiento por chunks | 2-3 |
| 6 | **Huérfanos** - Detección y asignación automática | 1 |
| 7 | **Firma Digital 2FA** - Con términos y condiciones | 1.5 |
| 8 | **WebSockets** - Laravel Reverb + Echo.js | 1 |
| 9 | **Email Templates** - Notificaciones | 0.5 |
| 10 | **Descarga Masiva** - Por categoría/período | 0.5 |
| 11 | **Exportar Excel** - Reportes de batches | 0.5 |
| 12 | **Frontend Store** - Entities, store, repository | 1 |
| 13 | **Frontend Admin** - Páginas de gestión | 3 |
| 14 | **Frontend Empleado** - Mis documentos + firma | 1 |
| 15 | **Componentes UI** - Uploader, Viewer, Badges | 1 |
| **TOTAL** | | **14-16** |


---

## Reglas de Negocio

### Reemplazo de Documentos

⚠️ **IMPORTANTE**: Si se sube un documento que ya existe (mismo tenant + tipo + período + DNI):

1. Se **reemplaza** el archivo PDF existente
2. Se **incrementa** el campo `version`
3. Se **resetea** la firma (`signature = null`, `signed_at = null`, `status = 'pending'`)
4. Se registra en el batch como "replaced" (no como nuevo)

```php
// Lógica de upsert
$document = Document::updateOrCreate(
    [
        'tenant_id' => $tenantId,
        'type_id' => $typeId,
        'period' => $period,
        'employee_document' => $employeeDocument,
    ],
    [
        'user_id' => $userId,
        'batch_id' => $batchId,
        'file_path' => $filePath,
        'file_size' => $fileSize,
        'original_filename' => $originalFilename,
        'status' => $userId ? 'pending' : 'orphan',
        'signature' => null,
        'signed_at' => null,
        'notified' => false,
        'version' => DB::raw('version + 1'),
    ]
);
```

### Permisos

| Acción | Root | Admin | Client |
|--------|------|-------|--------|
| Subir ZIP | ✅ | ✅ (su tenant) | ❌ |
| Ver historial batches | ✅ | ✅ (su tenant) | ❌ |
| Ver todos los documentos | ✅ | ✅ (su tenant) | ❌ |
| Ver mis documentos | ✅ | ✅ | ✅ |
| Firmar documento | ❌ | ❌ | ✅ (solo propios) |
| Asignar huérfano | ✅ | ✅ (su tenant) | ❌ |
| Eliminar documento | ✅ | ✅ (su tenant) | ❌ |
| Descargar masivo | ✅ | ✅ (su tenant) | ❌ |
| Exportar Excel | ✅ | ✅ (su tenant) | ❌ |

### Vista Previa del ZIP

Antes de procesar el ZIP, el sistema muestra una vista previa:

```
┌────────────────────────────────────────────────────────────────┐
│  📦 Vista Previa: documentos_diciembre.zip                     │
├────────────────────────────────────────────────────────────────┤
│  Archivos detectados: 150                                       │
│                                                                 │
│  ✅ 12345678.pdf (125 KB)                                       │
│  ✅ 87654321.pdf (98 KB)                                        │
│  ✅ 45678912.pdf (102 KB)                                       │
│  ⚠️ documento.pdf (nombre inválido - no es DNI)                │
│  ❌ imagen.jpg (formato no permitido)                          │
│  ... y 145 más                                                  │
│                                                                 │
│  Resumen:                                                       │
│  • PDFs válidos: 148                                           │
│  • Nombres inválidos: 1                                        │
│  • Formatos no permitidos: 1                                   │
│                                                                 │
│  ┌─────────────┐  ┌─────────────────────────────────────────┐  │
│  │  Cancelar   │  │         Continuar Procesamiento         │  │
│  └─────────────┘  └─────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

**Endpoint:**
```
POST /api/documents/preview-zip
Body: { file: ZIP }
Response: {
    total_files: 150,
    valid_pdfs: 148,
    invalid_names: [{ file: "documento.pdf", reason: "No es un DNI válido" }],
    invalid_formats: [{ file: "imagen.jpg", reason: "Formato no permitido" }],
    files: [
        { name: "12345678.pdf", size: 125000, valid: true },
        ...
    ]
}
```

### Opción "Requiere Firma" por Carga

Al subir el ZIP, el admin puede marcar que **esta carga específica** requiere firma:

```
☑ Requiere firma digital

Esto marcará TODOS los documentos de esta carga como pendientes de firma.
Los empleados recibirán un email especial indicando que deben firmar.
```

**Campos adicionales en `document_batches`:**
- `requires_signature` (boolean) - Si esta carga requiere firma
- Los documentos creados heredan este flag

### Asociación Automática de Documentos Huérfanos

Cuando se crea un **nuevo empleado**, el sistema detecta automáticamente documentos huérfanos:

```
┌──────────────────────────────────────────────────────────────────┐
│              FLUJO: Crear Empleado con Huérfanos                  │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. Admin crea empleado con DNI: 12345678                        │
│                                                                   │
│  2. Sistema busca en tabla `documents` donde:                    │
│     - employee_document = '12345678'                             │
│     - status = 'orphan'                                          │
│     - tenant_id = tenant actual                                  │
│                                                                   │
│  3. Si encuentra documentos huérfanos:                           │
│     - Actualiza user_id = nuevo empleado                         │
│     - Cambia status = 'pending' (o 'signed' si ya estaba)       │
│     - Mueve archivos a carpeta del empleado                     │
│                                                                   │
│  4. Notifica al empleado:                                        │
│     - Email: "Tienes X documentos disponibles"                   │
│     - WebSocket: NewDocumentAvailable                            │
│                                                                   │
│  5. Admin ve modal de confirmación:                              │
│     "Se asociaron 5 documentos al nuevo empleado"                │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

**Endpoint (se ejecuta automáticamente en UserController@store):**
```php
// En UserController.php después de crear usuario
$orphanedDocuments = Document::where('tenant_id', $tenantId)
    ->where('employee_document', $user->document_text)
    ->where('status', 'orphan')
    ->get();

if ($orphanedDocuments->count() > 0) {
    foreach ($orphanedDocuments as $doc) {
        $doc->update([
            'user_id' => $user->id,
            'status' => $doc->requires_signature ? 'pending' : 'available',
        ]);
    }
    
    // Notificar al empleado
    event(new OrphanDocumentsAssociated($user, $orphanedDocuments));
}
```

### Descarga Masiva

Admin puede descargar múltiples documentos en un ZIP:

**Opciones de descarga:**
1. Por **categoría/tipo** (ej: todas las boletas)
2. Por **período** (ej: todos los documentos de Dic-2024)
3. Por **empleado** (ej: todos los documentos de Juan Pérez)
4. Por **batch** (ej: todos los documentos de una carga específica)

**Endpoint:**
```
POST /api/documents/download-bulk
Body: {
    type_id: 1,           // opcional
    period: "2024-12",    // opcional
    user_id: "uuid",      // opcional
    batch_id: "uuid",     // opcional
    document_ids: []      // opcional - lista específica
}
Response: ZIP file (streaming download)
```

### Exportar Reportes Excel

Admin puede exportar reportes de procesamiento:

**Tipos de reporte:**
1. **Reporte de Batch** - Detalle de una carga específica
2. **Reporte de Documentos** - Lista filtrada de documentos
3. **Reporte de Firmas** - Estado de firmas pendientes/completadas
4. **Reporte de Huérfanos** - Documentos sin asignar

**Endpoint:**
```
GET /api/documents/export?type=batch&batch_id=uuid
GET /api/documents/export?type=documents&period=2024-12
GET /api/documents/export?type=signatures&status=pending
GET /api/documents/export?type=orphans
Response: Excel file (.xlsx)
```

**Columnas del Excel (Reporte de Batch):**
| Empleado | DNI | Tipo | Período | Estado | Firmado | Fecha Firma |
|----------|-----|------|---------|--------|---------|-------------|
| Juan Pérez | 12345678 | Boleta | 2024-12 | signed | ✅ | 2024-12-10 |
| María López | 87654321 | Boleta | 2024-12 | pending | ❌ | - |
| N/A | 45678912 | Boleta | 2024-12 | orphan | ❌ | - |

### Prevención de Doble Firma

El sistema previene que un documento sea firmado más de una vez:

```php
// En DocumentController@sign
public function sign(Request $request, $id)
{
    $document = Document::findOrFail($id);
    
    // Verificar que no esté ya firmado
    if ($document->status === 'signed') {
        return response()->json([
            'error' => 'Este documento ya fue firmado',
            'signed_at' => $document->signed_at
        ], 400);
    }
    
    // ... continuar con firma
}
```

### Modal de Términos y Condiciones (Primera Firma)

La primera vez que un empleado firma cualquier documento, debe aceptar términos:

```
┌────────────────────────────────────────────────────────────────┐
│              Términos y Condiciones de Firma Digital            │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Al firmar documentos digitalmente en MiBoleta, acepto que:    │
│                                                                 │
│  1. Mi firma electrónica tiene la misma validez legal que      │
│     mi firma manuscrita.                                        │
│                                                                 │
│  2. El sistema registrará mi IP, dispositivo y hora de firma   │
│     como evidencia del acto.                                    │
│                                                                 │
│  3. No podré repudiar los documentos que firme a través de     │
│     esta plataforma.                                            │
│                                                                 │
│  4. Soy responsable de mantener seguras mis credenciales       │
│     de acceso.                                                  │
│                                                                 │
│  ☑ He leído y acepto los términos y condiciones                │
│                                                                 │
│  ┌─────────────┐  ┌─────────────────────────────────────────┐  │
│  │  Cancelar   │  │         Aceptar y Continuar             │  │
│  └─────────────┘  └─────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

**Campo en tabla `users`:**
- `signature_terms_accepted_at` (timestamp, nullable)

Si es null, mostrar modal antes de permitir firma.


⚠️ **IMPORTANTE**: La firma digital requiere verificación por código enviado al email.

#### Flujo de Firma

```
┌──────────────────────────────────────────────────────────────────┐
│                    PASO 1: Solicitar Código                       │
├──────────────────────────────────────────────────────────────────┤
│  Frontend:                                                        │
│    - Usuario hace clic en "Firmar Documento"                     │
│    - Muestra modal de confirmación                               │
│    - Click "Enviar Código de Verificación"                       │
│                                                                   │
│  Backend: POST /api/documents/{id}/request-signature-code        │
│    1. Validar que el documento pertenece al usuario              │
│    2. Generar código de 6 dígitos                                │
│    3. Guardar código hasheado + expiración (5 min) en cache     │
│    4. Enviar email con código                                    │
│    5. Return: { message: "Código enviado", expires_in: 300 }    │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                    PASO 2: Ingresar Código                        │
├──────────────────────────────────────────────────────────────────┤
│  Frontend:                                                        │
│    - Muestra input para código de 6 dígitos                      │
│    - Timer countdown (5 minutos)                                 │
│    - Botón "Reenviar Código" (disponible después de 60s)        │
│    - Usuario ingresa código recibido por email                  │
│    - Click "Confirmar Firma"                                     │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                    PASO 3: Verificar y Firmar                     │
├──────────────────────────────────────────────────────────────────┤
│  Backend: POST /api/documents/{id}/sign                          │
│  Body: { code: "123456" }                                        │
│                                                                   │
│    1. Validar código (no expirado, matches hash)                │
│    2. Capturar datos de firma:                                   │
│       - IP del cliente                                           │
│       - User Agent                                               │
│       - Timestamp UTC                                            │
│       - Geolocalización (opcional)                               │
│    3. Actualizar documento:                                      │
│       - status = 'signed'                                        │
│       - signature = JSON con todos los datos                     │
│       - signed_at = now()                                        │
│    4. Invalidar código usado                                     │
│    5. Return: { success: true, document: {...} }                │
└──────────────────────────────────────────────────────────────────┘
```

#### API Endpoints de Firma

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/documents/{id}/request-signature-code` | Enviar código al email |
| POST | `/api/documents/{id}/sign` | Verificar código y firmar |

#### Tabla: `document_signature_codes` (Cache/Redis)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| document_id | uuid | Documento a firmar |
| user_id | uuid | Usuario que solicita |
| code_hash | string | Hash del código (bcrypt) |
| attempts | integer | Intentos fallidos (max 3) |
| expires_at | timestamp | Expiración (5 minutos) |
| created_at | timestamp | |

**Nota:** Puede usar Redis cache en lugar de tabla para mejor performance:
```php
// Key: signature_code:{document_id}:{user_id}
// Value: { code_hash, attempts, expires_at }
// TTL: 5 minutos
```

#### Email Template: Código de Firma

```
📝 Código de Verificación para Firma Digital

Hola {nombre},

Has solicitado firmar el siguiente documento:
- Tipo: {tipo_documento}
- Período: {periodo}

Tu código de verificación es:

    ┌─────────────────┐
    │     123456      │
    └─────────────────┘

Este código expira en 5 minutos.

⚠️ Si no solicitaste esta firma, ignora este mensaje.
```

#### Datos Capturados en la Firma

```json
{
  "signature": {
    "ip": "190.42.123.45",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)...",
    "timestamp": "2024-12-11T14:30:45.123Z",
    "user_id": "uuid-del-usuario",
    "verification_method": "email_code",
    "code_sent_at": "2024-12-11T14:28:00.000Z",
    "geo": {
      "latitude": -12.0464,
      "longitude": -77.0428
    }
  }
}
```

#### Validaciones de Seguridad

1. **Código válido por 5 minutos** - Después expira
2. **Máximo 3 intentos** - Si falla 3 veces, debe solicitar nuevo código
3. **Cooldown para reenvío** - 60 segundos entre reenvíos
4. **Solo el dueño puede firmar** - Validar user_id = document.user_id
5. **Código de un solo uso** - Se invalida después de usarse
6. **Hash del código** - Se guarda hasheado, no en texto plano

#### Frontend - Componentes

**DocumentSignModal.tsx**
```
┌────────────────────────────────────────────────────────────────┐
│                    Firmar Documento                             │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  📄 Boleta de Pago - Diciembre 2024                           │
│                                                                 │
│  Para firmar este documento, te enviaremos un código           │
│  de verificación a tu correo:                                  │
│                                                                 │
│  📧 j***@empresa.com                                           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │            [Enviar Código de Verificación]              │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ─────────────── o si ya tienes el código ───────────────     │
│                                                                 │
│  Ingresa el código de 6 dígitos:                              │
│                                                                 │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐                         │
│  │ 1 │ │ 2 │ │ 3 │ │ 4 │ │ 5 │ │ 6 │                         │
│  └───┘ └───┘ └───┘ └───┘ └───┘ └───┘                         │
│                                                                 │
│  ⏱️ El código expira en: 4:32                                  │
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────────────────────────┐ │
│  │    Cancelar     │  │         Confirmar Firma             │ │
│  └─────────────────┘  └─────────────────────────────────────┘ │
│                                                                 │
│  ¿No recibiste el código? [Reenviar] (disponible en 45s)      │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
---

## Checklist de Completitud

### Infraestructura
- [ ] Docker Compose con Redis + Horizon + Reverb
- [ ] Laravel Reverb configurado
- [ ] Echo.js configurado en React
- [ ] Migraciones ejecutan sin errores
- [ ] Seeder de tipos de documento

### Carga de Documentos
- [ ] Vista previa del ZIP antes de procesar
- [ ] Admin puede subir ZIP
- [ ] Opción "Requiere firma" por carga
- [ ] Procesamiento por chunks funciona
- [ ] Registro de cada batch con métricas
- [ ] Historial de procesos visible
- [ ] Errores de batch visibles
- [ ] Documentos huérfanos detectados
- [ ] Reemplazo de documentos funciona
- [ ] Notificación por email funciona

### WebSockets
- [ ] Eventos broadcasting funcionan
- [ ] Frontend recibe notificaciones en tiempo real
- [ ] Reconexión automática implementada
- [ ] Indicador de conexión visible

### Vista de Documentos
- [ ] Empleado puede ver sus documentos
- [ ] Visor PDF funciona con react-pdf
- [ ] Descarga individual funciona
- [ ] Descarga masiva (ZIP) funciona

### Documentos Huérfanos
- [ ] Lista de huérfanos visible para admin
- [ ] Asignación manual funciona
- [ ] Asociación automática al crear empleado
- [ ] Notificación al empleado tras asociación

### Firma Digital con 2FA
- [ ] Modal de términos y condiciones (primera vez)
- [ ] Campo signature_terms_accepted_at en users
- [ ] Endpoint solicitar código de firma
- [ ] Email con código se envía correctamente
- [ ] Código expira en 5 minutos
- [ ] Máximo 3 intentos por código
- [ ] Cooldown de 60s para reenvío
- [ ] Firma captura IP, timestamp, user-agent
- [ ] Prevención de doble firma
- [ ] Modal de firma con input de 6 dígitos
- [ ] Timer countdown visible

### Reportes y Exportación
- [ ] Exportar Excel de batch
- [ ] Exportar Excel de documentos
- [ ] Exportar Excel de firmas
- [ ] Exportar Excel de huérfanos

### Testing
- [ ] Tests unitarios pasan
- [ ] Tests de integración pasan

---

## Comandos Útiles

```bash
# Iniciar Horizon (desarrollo)
php artisan horizon

# Ver trabajos fallidos
php artisan horizon:failed

# Reintentar trabajos fallidos
php artisan horizon:retry all

# Limpiar trabajos fallidos
php artisan horizon:clear

# Verificar estado de Redis
redis-cli ping
```
