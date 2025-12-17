# 📋 TASK: Carga Masiva de Usuarios (Bulk User Upload)

**Fecha Inicio**: 2025-12-16  
**Prioridad**: Media  
**Complejidad**: Alta  
**Estimado**: 21-27 días  

---

## 🎯 Objetivo

Implementar funcionalidad completa de carga masiva de usuarios mediante archivos Excel, con:
- ✅ Procesamiento **100% asíncrono** con Jobs
- ✅ Persistencia de batches en BD (tabla `user_batches`)
- ✅ Soporte multi-tenant con múltiples organizaciones por usuario
- ✅ Template Excel dinámico con listas desplegables
- ✅ Preview editable antes de confirmar
- ✅ Progress tracking persistente (sobrevive a refresh)
- ✅ Historial completo de cargas

---

## 📐 Arquitectura Decidida

### Decisiones Clave

| Tema | Decisión | Razón |
|------|----------|-------|
| **Procesamiento** | 100% Asíncrono (Queue + Jobs) | Escalabilidad ilimitada + mejor UX |
| **Persistencia** | Tabla `user_batches` en BD | Mismo patrón que `document_batches` |
| **Multi-org** | Columnas múltiples (org1, org2, org3) | Simple + cubre 95% de casos |
| **Más de 3 orgs** | Filas duplicadas (consolidación) | Flexible sin complejidad |
| **Template** | Dinámico con modal configuración | Listas desplegables con datos reales |
| **Emails** | Checkbox en confirmación | Flexible para admin |
| **Duplicados en BD** | Preguntar en preview | Opción de actualizar o crear nuevo |

---

## 🗂️ Estructura de Base de Datos

### Tabla: `user_batches`

```sql
CREATE TABLE user_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    
    -- Archivo
    original_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT UNSIGNED,
    
    -- Progreso
    status ENUM('pending', 'processing', 'completed', 'failed', 'partial'),
    total_rows INT UNSIGNED DEFAULT 0,
    processed_rows INT UNSIGNED DEFAULT 0,
    created_users INT UNSIGNED DEFAULT 0,
    updated_users INT UNSIGNED DEFAULT 0,
    failed_rows INT UNSIGNED DEFAULT 0,
    current_chunk INT UNSIGNED DEFAULT 0,
    total_chunks INT UNSIGNED DEFAULT 0,
    progress_percentage DECIMAL(5,2) DEFAULT 0,
    
    -- Resultados
    error_summary JSON NULL,
    success_summary JSON NULL,
    processing_options JSON NULL,
    
    -- Tiempos
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_uuid (uuid)
);
```

---

## 📊 Formato de Excel (Template)

### Columnas del Template

| Campo | Tipo | Requerido | Ejemplo |
|-------|------|-----------|---------|
| `nombre` | texto | Sí | Juan |
| `apellido` | texto | Sí | Pérez García |
| `email` | email | Sí | juan@empresa.com |
| `tipo_documento` | lista | Sí | dni, ce, passport, ruc |
| `numero_documento` | texto | Sí | 12345678 |
| `rol` | lista | Sí | admin, employee, supervisor |
| `estado` | lista | Sí | active, inactive |
| `telefono` | texto | No | +51 999999999 |
| `org1_ruc` | lista desplegable | No | 20123456789 |
| `org1_supervisor_email` | lista desplegable | No | super@empresa.com |
| `org2_ruc` | lista desplegable | No | 20456789012 |
| `org2_supervisor_email` | lista desplegable | No | super2@empresa.com |
| `org3_ruc` | lista desplegable | No | 20789012345 |
| `org3_supervisor_email` | lista desplegable | No | super3@empresa.com |

### Hojas del Excel

1. **"Usuarios"** - Datos principales
2. **"Catálogo_Organizaciones"** - RUCs y nombres (desde BD)
3. **"Catálogo_Supervisores"** - Supervisores por org (desde BD)
4. **"Validaciones"** - Named ranges (oculta)
5. **"Instrucciones"** - Guía de uso

---

## 🔄 Flujo de Trabajo Completo

```
┌─────────────────────────────────────────────────────────────┐
│ FASE 1: GENERACIÓN DE TEMPLATE                              │
└─────────────────────────────────────────────────────────────┘
Admin → Clic "Descargar Template"
     → Modal de configuración:
        ├─ Seleccionar número de organizaciones (1-3)
        ├─ [Opcional] Filtrar por organizaciones específicas
        └─ Clic "Generar y Descargar"
     → Backend genera Excel personalizado con:
        ├─ Columnas dinámicas
        ├─ Listas desplegables con datos de BD
        └─ Validaciones nativas de Excel
     → Descarga: "template_usuarios_[config]_[fecha].xlsx"

┌─────────────────────────────────────────────────────────────┐
│ FASE 2: LLENADO DE DATOS (OFFLINE)                          │
└─────────────────────────────────────────────────────────────┘
Usuario → Llena datos en Excel:
       → Campos básicos: nombre, email, teléfono, etc.
       → Selecciona RUCs desde lista desplegable
       → Selecciona supervisores desde lista desplegable
       → Excel valida automáticamente (no permite inválidos)
       → Guarda archivo

┌─────────────────────────────────────────────────────────────┐
│ FASE 3: UPLOAD Y VALIDACIÓN                                 │
└─────────────────────────────────────────────────────────────┘
Usuario → Sube archivo a plataforma
       → Backend valida:
          ├─ Estructura del archivo
          ├─ Campos requeridos
          ├─ Emails únicos
          ├─ RUCs existen
          ├─ Supervisores existen y pertenecen a org
          └─ Consolida duplicados (mismo email)
       → Frontend muestra PREVIEW EDITABLE:
          ├─ ✅ 245 válidos
          ├─ ⚠️ 35 warnings (sin teléfono, actualizaciones)
          └─ ❌ 12 errores (email inválido, RUC no existe)

┌─────────────────────────────────────────────────────────────┐
│ FASE 4: EDICIÓN Y CORRECCIÓN                                │
└─────────────────────────────────────────────────────────────┘
Usuario → Revisa tabla editable:
       → Puede editar celdas con errores
       → Puede eliminar filas problemáticas
       → Re-valida después de editar
       → Exporta errores a Excel (para corregir offline)

┌─────────────────────────────────────────────────────────────┐
│ FASE 5: CONFIRMACIÓN Y PROCESAMIENTO                        │
└─────────────────────────────────────────────────────────────┘
Usuario → Checkboxes de opciones:
       ☑ Enviar emails de bienvenida a nuevos usuarios
       ☑ Actualizar usuarios existentes (si hay duplicados)
       → Clic "Confirmar Carga (245 válidos)"
       → Backend:
          ├─ Guarda archivo en storage
          ├─ Crea registro en user_batches (status: pending)
          ├─ Despacha Job asíncrono
          └─ Retorna UUID del batch
       → Frontend:
          └─ Redirige a página de tracking: /users/batches/{uuid}

┌─────────────────────────────────────────────────────────────┐
│ FASE 6: PROCESAMIENTO ASÍNCRONO (JOB)                       │
└─────────────────────────────────────────────────────────────┘
Job ProcessBulkUserUpload:
  └─ Para cada chunk de 50 usuarios:
     ├─ BEGIN TRANSACTION
     ├─ Crear/actualizar usuarios
     ├─ Asignar organizaciones (user_tenants)
     ├─ Asignar supervisores
     ├─ COMMIT
     ├─ Actualizar batch en BD:
     │  ├─ processed_rows
     │  ├─ created_users
     │  ├─ updated_users
     │  ├─ failed_rows
     │  └─ progress_percentage
     └─ Continue...
  
  Al finalizar:
  ├─ Marcar batch como "completed" o "partial"
  ├─ Notificar al admin (email + in-app)
  └─ Log en audit_logs

┌─────────────────────────────────────────────────────────────┐
│ FASE 7: TRACKING Y RESULTADOS                               │
└─────────────────────────────────────────────────────────────┘
Usuario → Ve página de detalle del batch:
       ├─ Progress bar en tiempo real (polling cada 3seg)
       ├─ Estadísticas:
       │  ├─ Total: 245 usuarios
       │  ├─ Procesados: 150 / 245
       │  ├─ Creados: 89
       │  ├─ Actualizados: 45
       │  ├─ Errores: 16
       │  └─ Progreso: 61%
       ├─ Cuando termina:
       │  ├─ Resumen final
       │  ├─ Botón "Descargar Errores" (si hay)
       │  └─ Botón "Ver Usuarios Creados"
       └─ Historial: Lista de todos los batches pasados
```

---

## 🏗️ Componentes a Implementar

### Backend

#### 1. Base de Datos
- [ ] Migration: `create_user_batches_table`
- [ ] Model: `UserBatch.php` con TenantFilterScope
- [ ] Relaciones: `tenant()`, `createdBy()`

#### 2. Servicios
- [ ] `BulkUserUploadService.php`:
  - [ ] `getConfigData()` - Datos para modal
  - [ ] `generateTemplate($maxOrgs, $orgIds)` - Excel dinámico
  - [ ] `validateFile($file)` - Parsear y validar
  - [ ] `consolidateDuplicates($users)` - Consolidar filas repetidas
  - [ ] `processUsersInChunks($users, $chunkSize)` - Generator

#### 3. Exports
- [ ] `UsersTemplateExport.php` - Template multi-sheet
  - [ ] `UsersSheetTemplate` - Hoja principal
  - [ ] `OrganizationsCatalogSheet` - Catálogo RUCs
  - [ ] `SupervisorsCatalogSheet` - Catálogo supervisores
  - [ ] `ValidationRulesSheet` - Named ranges
  - [ ] `InstructionsSheet` - Guía de uso
- [ ] `UserBatchErrorsExport.php` - Errores descargables

#### 4. Imports
- [ ] `UsersImport.php` con:
  - [ ] `ToCollection` interface
  - [ ] `WithHeadingRow` interface
  - [ ] `WithValidation` interface
  - [ ] `WithChunkReading` (100 filas)

#### 5. Jobs
- [ ] `ProcessBulkUserUpload.php`:
  - [ ] Procesar por chunks de 50
  - [ ] Actualizar progress en BD
  - [ ] Manejo de errores por chunk
  - [ ] Notificaciones al finalizar

#### 6. Notifications
- [ ] `BulkUploadCompleted.php` (email + database)
- [ ] `BulkUploadFailed.php` (email)

#### 7. Controllers
- [ ] `UserBatchController.php`:
  - [ ] `GET /config` - Datos para modal
  - [ ] `POST /template` - Generar Excel
  - [ ] `GET /` - Listar batches (historial)
  - [ ] `POST /` - Upload y validar
  - [ ] `GET /{uuid}` - Ver detalle y progress
  - [ ] `GET /{uuid}/errors` - Descargar errores

#### 8. Resources
- [ ] `UserBatchResource.php` - Transformar batch para API
- [ ] `UserBatchListResource.php` - Para listado

#### 9. Validaciones
- [ ] `BulkUserValidator.php`:
  - [ ] Validar estructura archivo
  - [ ] Validar cada usuario
  - [ ] Validar relaciones (org-supervisor)
  - [ ] Validar duplicados y consolidación

---

### Frontend

#### 1. Páginas
- [ ] `UserBatchUploadPage.tsx` - Upload inicial
- [ ] `UserBatchPreviewPage.tsx` - Preview editable
- [ ] `UserBatchDetailPage.tsx` - Detalle de batch
- [ ] `UserBatchesListPage.tsx` - Historial completo

#### 2. Componentes
- [ ] `TemplateConfigModal.tsx`:
  - [ ] Selector número de orgs (1-3)
  - [ ] Multi-selector de empresas
  - [ ] Preview de columnas
  - [ ] Botón generar
- [ ] `BulkUploadPreviewTable.tsx`:
  - [ ] Tabla editable (react-table)
  - [ ] Validación inline
  - [ ] Indicadores visuales (✅⚠️❌)
  - [ ] Filtros por estado
  - [ ] Acciones (editar, eliminar fila)
- [ ] `BulkUploadStats.tsx`:
  - [ ] Resumen de validación
  - [ ] Gráfico de estados
- [ ] `BulkUploadProgress.tsx`:
  - [ ] Progress bar
  - [ ] Estadísticas en tiempo real
  - [ ] Estimación de tiempo
- [ ] `UserBatchCard.tsx`:
  - [ ] Card para historial
  - [ ] Status badge
  - [ ] Progress mini

#### 3. Hooks
- [ ] `useBulkUpload.ts`:
  - [ ] Estado de upload
  - [ ] Validación
  - [ ] Confirmación
- [ ] `useBatchProgress.ts`:
  - [ ] Polling automático
  - [ ] Estado de progreso
  - [ ] Auto-refresh cada 3seg

#### 4. Services
- [ ] `bulkUserUploadService.ts`:
  - [ ] `getConfig()`
  - [ ] `downloadTemplate(config)`
  - [ ] `validateFile(file)`
  - [ ] `confirmUpload(users, options)`
  - [ ] `getBatchProgress(uuid)`
  - [ ] `downloadErrors(uuid)`

#### 5. Types
- [ ] `BulkUserTypes.ts`:
  - [ ] `TemplateConfig`
  - [ ] `BulkUserData`
  - [ ] `ValidationResult`
  - [ ] `BatchProgress`
  - [ ] `UserBatch`

---

## 📝 Validaciones Completas

### A Nivel de Archivo
- ✅ Formato .xlsx válido
- ✅ Contiene hoja "Usuarios"
- ✅ Columnas requeridas presentes
- ✅ No excede 1000 filas (configurable)
- ✅ Tamaño máximo 10 MB

### A Nivel de Fila

#### Errores (bloquean carga):
- ❌ Email inválido o duplicado
- ❌ Campos requeridos vacíos
- ❌ Tipo documento inválido
- ❌ Rol inválido
- ❌ RUC no existe en sistema
- ❌ Email supervisor no existe
- ❌ Supervisor no pertenece a org indicada
- ❌ Usuario intenta ser supervisor de sí mismo

#### Warnings (permiten carga):
- ⚠️ Teléfono en formato no estándar
- ⚠️ Usuario ya existe (se actualizará)
- ⚠️ Organización sin supervisor
- ⚠️ Email duplicado en archivo (se consolidarán)

### Validación de Filas Duplicadas

Cuando `email` aparece en múltiples filas:

**DEBE coincidir**:
- nombre
- apellido
- tipo_documento
- numero_documento
- rol
- estado

**Se consolidan**:
- organizaciones de todas las filas
- supervisores respectivos

**Error si**:
- Misma org repetida para mismo usuario
- Datos básicos no coinciden

---

## 🎯 Configuraciones

### Límites
```php
// config/bulk_upload.php
return [
    'max_file_size' => env('BULK_UPLOAD_MAX_SIZE', 10240), // KB
    'max_rows' => env('BULK_UPLOAD_MAX_ROWS', 1000),
    'max_organizations_per_user' => 3,
    'chunk_size_read' => 100,
    'chunk_size_process' => 50,
    'polling_interval' => 3000, // ms
];
```

### Queue
```php
// config/queue.php
'connections' => [
    'bulk_uploads' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'bulk-uploads',
        'retry_after' => 600,
    ],
],
```

---

## 📅 Plan de Implementación

### Fase 1: Backend - Base (5-6 días)
- [ ] **Día 1-2**: Migration + Model + Scopes
- [ ] **Día 3-4**: BulkUserUploadService básico
- [ ] **Día 4-5**: Template generation (Excel + listas)
- [ ] **Día 5-6**: File validation + parsing

### Fase 2: Backend - Processing (4-5 días)
- [ ] **Día 7-8**: Job ProcessBulkUserUpload
- [ ] **Día 8-9**: Chunks processing + transactions
- [ ] **Día 9-10**: Error handling + retry logic
- [ ] **Día 10-11**: Notifications + emails

### Fase 3: Backend - API (2-3 días)
- [ ] **Día 12**: UserBatchController endpoints
- [ ] **Día 12-13**: Resources + pagination
- [ ] **Día 13-14**: Tests unitarios

### Fase 4: Frontend - Upload (3-4 días)
- [ ] **Día 15**: TemplateConfigModal
- [ ] **Día 15-16**: Drag & drop upload
- [ ] **Día 16-17**: File validation UI
- [ ] **Día 17-18**: Preview básico

### Fase 5: Frontend - Preview Editable (4-5 días)
- [ ] **Día 18-19**: BulkUploadPreviewTable con react-table
- [ ] **Día 19-20**: Inline editing + validación
- [ ] **Día 20-21**: Filtros + búsqueda
- [ ] **Día 21-22**: Export errores

### Fase 6: Frontend - Progress (2-3 días)
- [ ] **Día 22**: useBatchProgress hook + polling
- [ ] **Día 22-23**: BulkUploadProgress component
- [ ] **Día 23**: UserBatchDetailPage

### Fase 7: Frontend - Historial (1-2 días)
- [ ] **Día 24**: UserBatchesListPage
- [ ] **Día 24-25**: UserBatchCard components

### Fase 8: Testing & Polish (2-3 días)
- [ ] **Día 25-26**: Tests E2E
- [ ] **Día 26-27**: UX improvements
- [ ] **Día 27**: Documentación

**Total: 27 días de desarrollo**

---

## 🧪 Testing Checklist

### Backend
- [ ] Test validación de archivo Excel
- [ ] Test consolidación de duplicados
- [ ] Test procesamiento por chunks
- [ ] Test transacciones (rollback en error)
- [ ] Test Job con diferentes cargas (10, 100, 500, 1000 usuarios)
- [ ] Test notificaciones
- [ ] Test multi-tenant isolation

### Frontend
- [ ] Test upload de archivo
- [ ] Test preview editable
- [ ] Test polling de progreso
- [ ] Test refresh durante procesamiento
- [ ] Test UX con diferentes estados de batch
- [ ] Test responsive design

### Integración
- [ ] Test flujo completo end-to-end
- [ ] Test con archivos grandes (1000 usuarios)
- [ ] Test con errores de red
- [ ] Test con Queue worker caído

---

## 📚 Documentación Necesaria

- [ ] README de carga masiva para admins
- [ ] Guía de template Excel (con capturas)
- [ ] API documentation (Swagger)
- [ ] Troubleshooting guide
- [ ] Video tutorial (opcional)

---

## ⚠️ Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Excel con formato incorrecto | Alta | Medio | Validación exhaustiva + mensajes claros |
| Timeout en procesamiento | Baja | Alto | Job asíncrono + chunks |
| Queue worker caído | Media | Alto | Monitoring + alertas + retry logic |
| Duplicados no detectados | Media | Alto | Validación robusta + tests |
| Error en chunk = pérdida total | Media | Alto | Transacciones por chunk |
| Memoria insuficiente | Baja | Alto | Chunk reading + streaming |

---

## ✅ Criterios de Aceptación

### Must Have
- [x] Generar template Excel con listas desplegables
- [x] Validar archivo y mostrar errores
- [x] Preview editable de datos
- [x] Procesamiento asíncrono 100%
- [x] Progress tracking persistente
- [x] Historial de batches
- [x] Multi-tenant support
- [x] Hasta 3 organizaciones por usuario
- [x] Consolidación de filas duplicadas
- [x] Notificaciones al finalizar

### Should Have
- [ ] Export de errores a Excel
- [ ] Estimación de tiempo restante
- [ ] Cancelación de batch en progreso
- [ ] Retry automático de chunks fallidos
- [ ] WebSocket para updates en tiempo real

### Nice to Have
- [ ] Modal de configuración con preview
- [ ] Drag & drop con preview
- [ ] Bulk edit en preview table
- [ ] Gráficos de progreso
- [ ] Email con resumen adjunto

---

## 🚀 Próximos Pasos

1. **Crear branch**: `git checkout -b feature/bulk-user-upload`
2. **Empezar con Backend Fase 1**: Migration + Model
3. **PR incremental** por cada fase completada
4. **Deploy a staging** para testing con datos reales
5. **User Acceptance Testing** con admin
6. **Deploy a producción**

---

**Estado**: 🟡 Diseño Completo - Listo para Implementación  
**Siguiente Acción**: Crear migration `user_batches`  
**Asignado**: Development Team  
**Deadline**: TBD (27 días)
