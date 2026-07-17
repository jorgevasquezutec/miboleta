# Modelado de Base de Datos - MiBoleta

## Diagrama Entidad-Relación (Conceptual)

```
┌─────────────┐
│   TENANTS   │ (Organizaciones)
│─────────────│
│ id          │
│ name        │
│ ruc         │
│ address     │
│ status      │
└──────┬──────┘
       │ 1
       │
       │ N
┌──────┴──────────┐
│     USERS       │ (Usuarios = Empleados + Credenciales)
│─────────────────│
│ id              │
│ email           │
│ password        │
│ tenant_id       │
│ document_type   │◄───── dni/ce/passport/ruc
│ document_text   │◄───── NULL solo para root/admin puro
│ name            │◄───── Nombre
│ last_name       │◄───── Apellidos
│ status          │
└──────┬──────────┘
       │ 1          │ N
       │            │
       │ N          │ N
┌──────┴──────────┐ ┌──────┴──────────┐
│   USER_ROLES    │ │    DOCUMENTS    │
│─────────────────│ │─────────────────│
│ id              │ │ id              │
│ user_id         │ │ tenant_id       │
│ role_id         │ │ user_id         │◄── Empleado dueño (NULL=huérfano)
│ tenant_id       │ │ employee_dni    │◄── DNI siempre presente
│ granted_by      │ │ doc_type_id     │◄── FK a document_types
│ granted_at      │ │ period          │
└─────────────────┘ │ file_path       │
       │ N          │ status          │◄── orphan/pending/signed
       │            │ signature       │◄── JSON auditoría
       │            │ signed_at       │◄── Timestamp firma
       │            └──────┬──────────┘
       │ 1                 │ N
┌──────┴──────────┐        │
│     ROLES       │        │ 1
│─────────────────│ ┌──────┴──────────────┐
│ id              │ │   DOCUMENT_TYPES    │
│ name            │ │─────────────────────│
│ display_name    │ │ id                  │
│ permissions     │ │ name                │◄── boleta, cts, etc
└─────────────────┘ │ display_name        │
                    │ is_active           │
                    └─────────────────────┘

       │
       │ N
┌──────┴─────────────────┐
│  VACATION_REQUESTS     │
│────────────────────────│
│ id                     │
│ user_id                │◄── Empleado solicitante
│ tenant_id              │
│ year                   │
│ start_date             │
│ end_date               │
│ days_requested         │
│ reason                 │
│ status                 │◄── pending/approved/rejected
│ approved_by            │◄── Admin aprobador
│ approved_at            │
│ rejected_reason        │
└────────────────────────┘

       │ N
┌──────┴─────────────┐
│  NOTIFICATIONS     │
│────────────────────│
│ id                 │
│ user_id            │◄── Usuario receptor (N:1)
│ tenant_id          │
│ actor_id           │◄── Quién generó (N:1)
│ type               │
│ title              │
│ message            │
│ related_type       │
│ related_id         │
│ action_url         │
│ is_read            │
│ read_at            │
└────────────────────┘

       │ N
┌──────┴─────────────┐
│  AUDIT_LOGS        │
│────────────────────│
│ id                 │
│ user_id            │◄── N:1 con users
│ tenant_id          │◄── N:1 con tenants
│ action             │
│ model              │
│ model_id           │
│ old_values         │ JSON
│ new_values         │ JSON
│ ip_address         │
│ user_agent         │
└────────────────────┘
```

**NOTA IMPORTANTE:** 
- ✅ `document_types` = mantenedor de tipos de documentos (configurable)
- ✅ Firma digital: `signature` JSON + `signed_at` + `status` (auditoría completa)
- ✅ Notificaciones: cada usuario ve sus notificaciones (root/admin/client)
- ✅ Identificación flexible: `document_type` + `document_text` (DNI/CE/Passport/RUC)
- ✅ Documentos huérfanos: soporta subida antes de crear empleado
- ✅ Vacaciones: política fija 30 días/año, calculado dinámicamente

---

## Tablas Detalladas

### 1. `tenants` (Organizaciones)

```sql
CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ruc VARCHAR(11) UNIQUE NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    logo_path VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_ruc (ruc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Representa las organizaciones/empresas que usan el sistema.

**Relaciones:**
- `1:N` con `users` (empleados, admins y clients)
- `1:N` con `documents`

---

### 2. `users` (Usuarios = Empleados + Credenciales)

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Credenciales
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    last_login_at TIMESTAMP NULL,
    
    -- Relación organizacional
    tenant_id BIGINT UNSIGNED NULL, -- NULL solo para root
    
    -- Datos de empleado (aplicable para roles client/admin)
    document_type ENUM('dni', 'ce', 'passport', 'ruc') NULL, -- Tipo de documento
    document_text VARCHAR(20) UNIQUE NULL, -- Número de documento (NULL solo para root)
    name VARCHAR(100) NOT NULL, -- Nombre
    last_name VARCHAR(150) NOT NULL, -- Apellidos
    phone VARCHAR(20),
    
    -- Permisos especiales
    is_vacation_approver BOOLEAN DEFAULT FALSE, -- Puede aprobar vacaciones de su tenant
    
    -- Estado
    status ENUM('active', 'inactive', 'terminated', 'pending') DEFAULT 'pending',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_document (document_type, document_text),
    INDEX idx_status (status),
    INDEX idx_name (name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Tabla unificada de usuarios + empleados.

**Relaciones:**
- `N:1` con `tenants` (NULL solo para root)
- `1:N` con `documents` (como empleado)
- `N:N` con `roles` a través de `user_roles`

**Casos de uso:**
```sql
-- Root (super admin - sin datos de empleado)
INSERT INTO users (email, password, tenant_id, document_type, document_text, name, last_name, is_vacation_approver) 
VALUES ('platform@miboleta.com', '$hash', NULL, NULL, NULL, 'Sistema', 'MiBoleta', FALSE);

-- Admin puro (gestiona pero NO es empleado de nómina)
INSERT INTO users (email, password, tenant_id, document_type, document_text, name, last_name, is_vacation_approver) 
VALUES ('admin@empresa.com', '$hash', 1, NULL, NULL, 'María', 'López', FALSE);

-- Client peruano con DNI (empleado estándar - TODOS tienen acceso)
INSERT INTO users (email, password, tenant_id, document_type, document_text, name, last_name, is_vacation_approver) 
VALUES ('juan@empresa.com', '$hash', 1, 'dni', '12345678', 'Juan', 'Pérez', FALSE);

-- Client extranjero con CE
INSERT INTO users (email, password, tenant_id, document_type, document_text, name, last_name, is_vacation_approver) 
VALUES ('maria@empresa.com', '$hash', 1, 'ce', '001234567', 'María', 'González', FALSE);

-- Admin + Client + Aprobador de Vacaciones (gerente que gestiona, recibe nómina Y aprueba vacaciones)
INSERT INTO users (email, password, tenant_id, document_type, document_text, name, last_name, is_vacation_approver) 
VALUES ('gerente@empresa.com', '$hash', 1, 'dni', '87654321', 'Carlos', 'Ruiz', TRUE);
```

**Reglas de negocio:**
- ✅ `tenant_id = NULL` → Solo para root
- ✅ `document_text = NULL` → Solo para root o admins puros (no reciben nómina)
- ✅ `document_text != NULL` → Empleado con nómina (recibe boletas)
- ✅ `document_type` → Soporta: 'dni' (8 dígitos), 'ce' (9 dígitos), 'passport' (alfanumérico), 'ruc' (11 dígitos)
- ✅ Todos los usuarios con `document_text` tienen rol `client` por defecto
- ✅ Al crear un empleado → automáticamente se crea su usuario + rol client
- ✅ `is_vacation_approver = TRUE` → Usuario puede aprobar vacaciones de su organización
- ✅ Solo root puede asignar/remover el flag de aprobador
- ✅ Un usuario puede ser: admin + client + aprobador (todo junto)

---

### 4. `roles` (Roles del Sistema)

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- 'root', 'admin', 'client'
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON NOT NULL, -- {"employees": ["create","read","update","delete"], ...}
    guard_name VARCHAR(50) DEFAULT 'web',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Define los 3 roles fijos del sistema.

**Registros iniciales (Seeders):**

```sql
-- 1. ROOT
INSERT INTO roles (id, name, display_name, description, permissions) VALUES (
    1,
    'root',
    'Super Administrador',
    'Acceso total al sistema sin restricciones',
    '{
        "tenants": ["create","read","update","delete"],
        "users": ["create","read","update","delete"],
        "employees": ["create","read","update","delete"],
        "documents": ["create","read","update","delete"],
        "reports": ["read"],
        "settings": ["read","update"]
    }'
);

-- 2. ADMIN
INSERT INTO roles (id, name, display_name, description, permissions) VALUES (
    2,
    'admin',
    'Administrador de Organización',
    'Gestiona empleados y documentos de su organización',
    '{
        "employees": ["create","read","update","delete"],
        "documents": ["create","read","update","delete"],
        "vacations": ["read","update","approve"],
        "reports": ["read"]
    }'
);

-- 3. CLIENT
INSERT INTO roles (id, name, display_name, description, permissions) VALUES (
    3,
    'client',
    'Cliente / Empleado',
    'Acceso a sus propios documentos y solicitudes',
    '{
        "documents": ["read","download","sign"],
        "vacations": ["create","read"],
        "profile": ["read","update"]
    }'
);
```

---

### 5. `user_roles` (Pivot - Usuarios ↔ Roles)

```sql
CREATE TABLE user_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    granted_by BIGINT UNSIGNED NULL, -- Usuario que otorgó el rol
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Relaciona usuarios con roles y organizaciones.

**Casos de uso:**

```sql
-- Root (sin tenant)
INSERT INTO user_roles (user_id, role_id, granted_by) 
VALUES (1, 1, 1);

-- Admin en Empresa A
INSERT INTO user_roles (user_id, role_id, granted_by) 
VALUES (2, 2, 1);

-- Cliente en Empresa A
INSERT INTO user_roles (user_id, role_id, granted_by) 
VALUES (3, 3, 2);

-- Admin + Client (mismo usuario, diferentes roles)
INSERT INTO user_roles (user_id, role_id, granted_by) 
VALUES (2, 3, 1);
```

---

### 6. `document_types` (Tipos de Documentos - Mantenedor)

```sql
CREATE TABLE document_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- 'boleta', 'liquidacion', 'cts', 'gratificacion', 'otros'
    display_name VARCHAR(100) NOT NULL, -- 'Boleta de Pago', 'Liquidación de Beneficios Sociales'
    description TEXT,
    requires_signature BOOLEAN DEFAULT TRUE, -- Si requiere firma digital
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Mantenedor de tipos de documentos (configurable por root).

**Ventajas:**
- ✅ Tipos configurables sin cambiar código
- ✅ Root puede agregar nuevos tipos desde admin
- ✅ Cada tipo puede tener reglas específicas
- ✅ Se puede activar/desactivar tipos según necesidad

**Datos iniciales (Seeder):**
```sql
INSERT INTO document_types (id, name, display_name, requires_signature) VALUES
(1, 'boleta', 'Boleta de Pago', TRUE),
(2, 'liquidacion', 'Liquidación de Beneficios Sociales', TRUE),
(3, 'cts', 'CTS - Compensación por Tiempo de Servicios', TRUE),
(4, 'gratificacion', 'Gratificación', TRUE),
(5, 'otros', 'Otros Documentos', FALSE);
```

---

### 7. `documents` (Documentos)

```sql
CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL, -- Empleado dueño (NULL si aún no existe el usuario)
    employee_document_number VARCHAR(20) NOT NULL, -- Número de documento del empleado (siempre presente)
    doc_type_id BIGINT UNSIGNED NOT NULL, -- FK a document_types
    period VARCHAR(7) NOT NULL, -- YYYY-MM
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL, -- bytes
    original_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'signed', 'expired', 'orphan') DEFAULT 'orphan',
    uploaded_by BIGINT UNSIGNED, -- Admin que subió el documento
    
    -- Firma digital (auditoría completa)
    signature JSON NULL, -- {"ip_address": "...", "user_agent": "...", "hash": "...", "geolocation": "..."}
    signed_at TIMESTAMP NULL, -- Fecha y hora de la firma digital
    
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (doc_type_id) REFERENCES document_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user_id (user_id),
    INDEX idx_employee_document (employee_document_number),
    INDEX idx_doc_type_id (doc_type_id),
    INDEX idx_period (period),
    INDEX idx_status (status),
    INDEX idx_tenant_document (tenant_id, employee_document_number), -- Para buscar documentos huérfanos
    INDEX idx_user_period (user_id, period) -- Optimización para consultas frecuentes
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Almacena metadatos de documentos y su firma digital.

**Relaciones:**
- `N:1` con `tenants`
- `N:1` con `users` (empleado dueño del documento)
- `N:1` con `document_types` (tipo de documento)
- `N:1` con `users` (admin que subió el documento)

**Nota:** 
- `user_id` puede ser NULL si el empleado aún no existe en el sistema (documento huérfano)
- `employee_document_number` siempre está presente (del PDF o archivo fuente)
- Documentos huérfanos: `user_id = NULL` y `status = 'orphan'`
- Al crear un usuario con DNI, se ejecuta proceso automático para vincular documentos huérfanos
- Firma digital: 3 campos trabajando juntos
  - `status='signed'` → Indica que está firmado
  - `signed_at` → Timestamp de la firma
  - `signature` → JSON con auditoría completa: `{"ip_address": "192.168.1.1", "user_agent": "Mozilla/5.0...", "signature_hash": "sha256:abc...", "geolocation": "Lima, Perú"}`

**Proceso de vinculación de documentos huérfanos:**
```php
// Event: UserCreated
// Listener: LinkOrphanDocuments

public function handle(UserCreated $event)
{
    $user = $event->user;
    
    if ($user->document_text) {
        // Buscar documentos huérfanos con el mismo número de documento y tenant
        $orphanDocuments = Document::where('tenant_id', $user->tenant_id)
            ->where('employee_document_number', $user->document_text)
            ->whereNull('user_id')
            ->where('status', 'orphan')
            ->get();
        
        foreach ($orphanDocuments as $doc) {
            $doc->update([
                'user_id' => $user->id,
                'status' => 'pending' // Cambia de orphan a pending para firma
            ]);
            
            // Notificar al empleado sobre documentos disponibles
            $user->notify(new DocumentsAvailable($orphanDocuments->count()));
        }
    }
}
```

---

### 8. `vacation_requests` (Solicitudes de Vacaciones)

**⚠️ SIMPLIFICACIÓN: Sin tablas auxiliares**

**Política fija de vacaciones:**
- Todos los empleados tienen 30 días de vacaciones por año
- No hay días proporcionales ni acumulados
- Cálculo dinámico: `días_disponibles = 30 - SUM(days_requested WHERE status='approved')`
- Más simple, menos tablas, performance aceptable para <10K empleados

```sql
CREATE TABLE vacation_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL, -- Empleado solicitante
    tenant_id BIGINT UNSIGNED NOT NULL, -- Para filtrado rápido
    year INT NOT NULL, -- Año de la solicitud (2024, 2025, etc)
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested DECIMAL(5,2) NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejected_at TIMESTAMP NULL,
    rejected_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_year (year),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_user_year_status (user_id, year, status) -- Para cálculo de días usados
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Almacena solicitudes de vacaciones con cálculo dinámico de días disponibles.

**Relaciones:**
- `N:1` con `users` (empleado solicitante)
- `N:1` con `tenants`
- `N:1` con `users` (aprobador/rechazador)

**Lógica de negocio:**
- Política fija: 30 días/año por empleado
- Días usados = SUM(days_requested) WHERE status='approved' AND year=?
- Días disponibles = 30 - días usados
- No se permiten días acumulados ni proporcionales (simplificado)
---

### 9. `notifications` (Notificaciones)

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL, -- Usuario que RECIBE la notificación
    tenant_id BIGINT UNSIGNED NOT NULL, -- Organización (para multi-tenancy)
    
    -- Quién generó la acción (opcional)
    actor_id BIGINT UNSIGNED NULL, -- Usuario que generó la notificación
    
    -- Qué objeto está relacionado (polimórfico simple)
    related_type VARCHAR(50) NULL, -- 'document', 'vacation_request', etc.
    related_id BIGINT UNSIGNED NULL,
    
    -- Contenido
    type VARCHAR(50) NOT NULL, -- 'document_uploaded', 'vacation_approved', 'document_signed'
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) NULL, -- URL para abrir el objeto relacionado
    
    -- Estado de lectura
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_user_unread (user_id, is_read, created_at), -- Para queries de notificaciones no leídas
    INDEX idx_tenant (tenant_id),
    INDEX idx_related (related_type, related_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Sistema de notificaciones simplificado donde cada usuario ve sus propias notificaciones.

**Relaciones:**
- `N:1` con `users` (usuario receptor)
- `N:1` con `tenants` (organización)
- `N:1` con `users` (actor que generó la acción)

**Ejemplos de notificaciones:**

```sql
-- Notificación: Admin subió documento para empleado
INSERT INTO notifications (user_id, tenant_id, actor_id, related_type, related_id, type, title, message, action_url)
VALUES (
    123, -- ID del empleado
    1,   -- Tenant
    456, -- ID del admin que subió
    'document',
    789, -- ID del documento
    'document_uploaded',
    'Nueva Boleta de Pago',
    'María López subió tu boleta de Enero 2024. Por favor firma el documento.',
    '/documents/789'
);

-- Notificación: Vacaciones aprobadas
INSERT INTO notifications (user_id, tenant_id, actor_id, related_type, related_id, type, title, message, action_url)
VALUES (
    123, -- ID del empleado
    1,   -- Tenant
    456, -- ID del aprobador
    'vacation_request',
    50,  -- ID de la solicitud
    'vacation_approved',
    'Vacaciones Aprobadas',
    'Carlos Ruiz aprobó tu solicitud de vacaciones del 15 al 20 de Enero.',
    '/vacations/50'
);

-- Notificación para admin: Empleado firmó documento
INSERT INTO notifications (user_id, tenant_id, actor_id, related_type, related_id, type, title, message, action_url)
VALUES (
    456, -- ID del admin
    1,   -- Tenant
    123, -- ID del empleado que firmó
    'document',
    789,
    'document_signed',
    'Documento Firmado',
    'Juan Pérez firmó su boleta de Enero 2024.',
    '/documents/789'
);
```

---

### 12. `audit_logs` (Auditoría)

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    tenant_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL, -- 'created', 'updated', 'deleted'
    model VARCHAR(255) NOT NULL, -- 'App\Models\Document'
    model_id BIGINT UNSIGNED NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_model (model, model_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Registro completo de cambios en el sistema.

---

## Índices y Optimizaciones

### Índices Compuestos Adicionales

```sql
-- Búsqueda de documentos por empleado y período
CREATE INDEX idx_user_period ON documents(user_id, period);

-- Búsqueda de vacaciones por empleado y estado
CREATE INDEX idx_vacation_user_status ON vacation_requests(user_id, status);

-- Búsqueda de notificaciones no leídas por usuario
CREATE INDEX idx_user_unread ON notifications(user_id, is_read, created_at);

-- Auditoría por tenant y fecha
CREATE INDEX idx_audit_tenant_date ON audit_logs(tenant_id, created_at);

-- Búsqueda de aprobadores por tenant
CREATE INDEX idx_approvers ON users(tenant_id, is_vacation_approver, status);
```

---

## Consideraciones de Diseño

### 1. **Soft Deletes**
Todas las tablas principales tienen `deleted_at` para eliminación lógica:
- ✅ Mantiene integridad referencial
- ✅ Permite auditoría histórica
- ✅ Recuperación de datos eliminados

### 2. **Multi-tenancy**
- Cada registro está asociado a un `tenant_id`
- Middleware `TenantScope` filtra automáticamente queries
- Root puede acceder a todos los tenants

### 3. **Seguridad**
- Passwords hasheados con bcrypt
- Tokens de Sanctum en tabla `personal_access_tokens`
- Auditoría completa de acciones
- IP y user-agent en firmas digitales

### 4. **Escalabilidad**
- Índices optimizados para queries frecuentes
- JSON para datos flexibles (permissions, metadata)
- Storage de archivos fuera de BD (S3, local)

### 5. **Integridad Referencial**
```sql
ON DELETE CASCADE   -- Elimina registros dependientes
ON DELETE SET NULL  -- Mantiene registro pero limpia FK
```

---

## Migraciones Laravel (Orden de Ejecución)

```php
// 1. Base
2024_01_01_000001_create_tenants_table.php
2024_01_01_000002_create_users_table.php
2024_01_01_000003_create_roles_table.php
2024_01_01_000004_create_user_roles_table.php

// 2. Documentos
2024_01_01_000010_create_document_types_table.php
2024_01_01_000011_create_documents_table.php

// 3. Vacaciones
2024_01_01_000020_create_vacation_requests_table.php

// 4. Sistema
2024_01_01_000030_create_notifications_table.php
2024_01_01_000031_create_audit_logs_table.php
2024_01_01_000032_create_personal_access_tokens_table.php // Sanctum
```

---

## Seeders Iniciales

### 1. `RoleSeeder`
```php
DB::table('roles')->insert([
    ['id' => 1, 'name' => 'root', 'display_name' => 'Super Administrador'],
    ['id' => 2, 'name' => 'admin', 'display_name' => 'Administrador'],
    ['id' => 3, 'name' => 'client', 'display_name' => 'Cliente'],
]);
```

### 2. `DocumentTypeSeeder`
```php
DB::table('document_types')->insert([
    ['id' => 1, 'name' => 'boleta', 'display_name' => 'Boleta de Pago', 'requires_signature' => true],
    ['id' => 2, 'name' => 'liquidacion', 'display_name' => 'Liquidación de Beneficios Sociales', 'requires_signature' => true],
    ['id' => 3, 'name' => 'cts', 'display_name' => 'CTS', 'requires_signature' => true],
    ['id' => 4, 'name' => 'gratificacion', 'display_name' => 'Gratificación', 'requires_signature' => true],
    ['id' => 5, 'name' => 'otros', 'display_name' => 'Otros Documentos', 'requires_signature' => false],
]);
```

### 3. `RootUserSeeder`
```php
$user = User::create([
    'email' => 'platform@miboleta.com',
    'password' => Hash::make('super_secret_password'),
    'status' => 'active',
]);

UserRole::create([
    'user_id' => $user->id,
    'role_id' => 1, // root
]);
```

---

## Tamaño Estimado de Base de Datos

### Escenario: 100 empresas, 50 empleados c/u, 5 años de datos

| Tabla | Registros | Tamaño Aprox |
|-------|-----------|--------------|
| tenants | 100 | 50 KB |
| users | 5,100 | 5 MB |
| roles | 3 | 1 KB |
| user_roles | 5,100 | 100 KB |
| document_types | 10 | 5 KB |
| documents | 300,000 | 180 MB ← Incluye firma JSON |
| vacation_requests | 25,000 | 10 MB |
| notifications | 500,000 | 250 MB ← Incluye actor + related |
| audit_logs | 1,000,000 | 500 MB |
| **TOTAL** | | **~945 MB** |

**Storage de archivos (fuera de BD):** ~150 GB (300k PDFs × 500KB promedio)

---

## Queries Frecuentes Optimizadas

### 1. Documentos de un empleado en un período
```sql
SELECT * FROM documents
WHERE user_id = ? AND period = ?
ORDER BY created_at DESC;
-- ✅ Usa índice idx_user_period
```

### 2. Solicitudes pendientes de vacaciones por tenant
```sql
SELECT vr.*, CONCAT(u.name, ' ', u.last_name) as full_name
FROM vacation_requests vr
JOIN users u ON vr.user_id = u.id
WHERE vr.tenant_id = ? AND vr.status = 'pending'
ORDER BY vr.created_at ASC;
-- ✅ Usa índice idx_tenant_id
```

### 3. Calcular días disponibles de un empleado en un año
```sql
SELECT 
    30 - COALESCE(SUM(days_requested), 0) as dias_disponibles
FROM vacation_requests
WHERE user_id = ? 
  AND year = 2024 
  AND status = 'approved';
-- ✅ Usa índice idx_user_year_status
```

### 4. Notificaciones no leídas de un usuario
```sql
SELECT 
    n.*,
    CONCAT(u_actor.name, ' ', u_actor.last_name) as actor_name
FROM notifications n
LEFT JOIN users u_actor ON n.actor_id = u_actor.id
WHERE n.user_id = ? 
  AND n.is_read = FALSE
ORDER BY n.created_at DESC
LIMIT 10;
-- ✅ Usa índice idx_user_unread
```

### 5. Marcar notificación como leída
```sql
UPDATE notifications
SET is_read = TRUE, read_at = NOW()
WHERE id = ? AND user_id = ?;
```

### 6. Contador de notificaciones no leídas
```sql
SELECT COUNT(*) as unread_count
FROM notifications
WHERE user_id = ? AND is_read = FALSE;
-- ✅ Usa índice idx_user_unread
```

---

## Backup y Mantenimiento

### Estrategia de Backup
```bash
# Diario: Full backup
mysqldump --single-transaction miboleta > backup_$(date +%Y%m%d).sql

# Semanal: Backup + archivos
tar -czf miboleta_full_$(date +%Y%m%d).tar.gz \
  backup.sql \
  storage/app/documents/
```sql
SELECT * FROM documents
WHERE user_id = ? AND period = ?
ORDER BY created_at DESC;
-- ✅ Usa índice idx_user_period
```

### 2. Solicitudes pendientes de vacaciones por tenant
```sql
SELECT vr.*, CONCAT(u.name, ' ', u.last_name) as full_name
FROM vacation_requests vr
JOIN users u ON vr.user_id = u.id
WHERE vr.tenant_id = ? AND vr.status = 'pending'
ORDER BY vr.created_at ASC;
-- ✅ Usa índice idx_tenant_id
```

### 3. Calcular días disponibles de un empleado en un año
```sql
SELECT 
    30 - COALESCE(SUM(days_requested), 0) as dias_disponibles
FROM vacation_requests
WHERE user_id = ? 
  AND year = 2024 
  AND status = 'approved';
-- ✅ Usa índice idx_user_year_status
```

### 4. Notificaciones no leídas de un usuario
```sql
SELECT 
    n.*,
    CONCAT(u_actor.name, ' ', u_actor.last_name) as actor_name
FROM notifications n
LEFT JOIN users u_actor ON n.actor_id = u_actor.id
WHERE n.user_id = ? 
  AND n.is_read = FALSE
ORDER BY n.created_at DESC
LIMIT 10;
-- ✅ Usa índice idx_user_unread
```

### 5. Marcar notificación como leída
```sql
UPDATE notifications
SET is_read = TRUE, read_at = NOW()
WHERE id = ? AND user_id = ?;
```

### 6. Contador de notificaciones no leídas
```sql
SELECT COUNT(*) as unread_count
FROM notifications
WHERE user_id = ? AND is_read = FALSE;
-- ✅ Usa índice idx_user_unread
```

---

## Backup y Mantenimiento

### Estrategia de Backup
```bash
# Diario: Full backup
mysqldump --single-transaction miboleta > backup_$(date +%Y%m%d).sql

# Semanal: Backup + archivos
tar -czf miboleta_full_$(date +%Y%m%d).tar.gz \
  backup.sql \
  storage/app/documents/

# Mensual: Archivado S3
aws s3 cp miboleta_full_*.tar.gz s3://backups/miboleta/
```

### Mantenimiento
```sql
-- Limpiar notificaciones leídas >30 días
DELETE FROM notifications 
WHERE read_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Limpiar logs de auditoría >1 año
DELETE FROM audit_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Optimizar tablas
OPTIMIZE TABLE documents, audit_logs, notifications;
```

---

## Consideraciones de Diseño

### 1. **Soft Deletes**
Todas las tablas principales tienen `deleted_at` para eliminación lógica:
- ✅ Mantiene integridad referencial
- ✅ Permite auditoría histórica
- ✅ Recuperación de datos eliminados

### 2. **Multi-tenancy**
- Cada registro está asociado a un `tenant_id`
- Middleware `TenantScope` filtra automáticamente queries
- Root puede acceder a todos los tenants

### 3. **Seguridad**
- Passwords hasheados con bcrypt
- Tokens de Sanctum en tabla `personal_access_tokens`
- Auditoría completa de acciones
- IP y user-agent en firmas digitales

### 4. **Escalabilidad**
- Índices optimizados para queries frecuentes
- JSON para datos flexibles (permissions, metadata)
- Storage de archivos fuera de BD (S3, local)

### 5. **Integridad Referencial**
```sql
ON DELETE CASCADE   -- Elimina registros dependientes
ON DELETE SET NULL  -- Mantiene registro pero limpia FK
```

---

## Resumen

**Total de tablas:** 9 principales
**Total de migraciones:** 11 archivos
**Relaciones:** 18 foreign keys
**Índices:** 25+ índices optimizados

**Tablas eliminadas por simplicidad:**
- ❌ `employees` → Unificado en `users`
- ❌ `vacation_approvers` → Flag `is_vacation_approver` en `users`
- ❌ `vacation_periods` → Política fija de 30 días/año
- ❌ `vacation_balances` → Cálculo dinámico con queries
- ❌ `document_signatures` → Campos `signature` JSON + `signed_at` + `status` en `documents`

**Arquitectura final:**

1. **Multi-tenancy**: `tenants` → `users` → `documents/vacations/notifications`
2. **Roles flexibles**: `users` ↔ `roles` (N:N) vía `user_roles`
3. **Documentos inteligentes**: Soporta huérfanos + tipos configurables + firma digital JSON
4. **Identificación flexible**: `document_type` + `document_text` (DNI/CE/Passport/RUC)
5. **Notificaciones modernas**: Estilo Facebook (user_id + actor_id + related_type/id)
6. **Vacaciones simplificadas**: 30 días/año fijos, cálculo dinámico sin tablas auxiliares
7. **Auditoría completa**: `audit_logs` + campos `signature` JSON en documentos

**Características clave:**
- ✅ Todos los empleados tienen acceso automático a la plataforma
- ✅ Documentos huérfanos: Soporta subida antes de crear empleado
- ✅ Notificaciones: Cada usuario (root/admin/client) ve sus propias notificaciones
- ✅ Firma digital: Auditoría completa con IP, user_agent, geolocalización, hash
- ✅ Tipos de documentos: Mantenedor configurable por root
- ✅ Aprobadores de vacaciones: Flag asignable por root
- ✅ Escalabilidad: Hasta 1M+ registros con índices optimizados
- ✅ Seguridad: Multi-tenancy + soft deletes + auditoría + tokens Sanctum
- ✅ Seguridad: Multi-tenancy + soft deletes + auditoría + tokens Sanctum