# 📋 TASK: Módulo 7 - Sistema de Reportes y Auditoría

**Fecha:** 2025-12-15  
**Estimación:** 4-5 días  
**Prioridad:** Media-Alta

---

## 🎯 Objetivo

Implementar un sistema de reportes que permita a administradores y supervisores:
- Ver métricas y estadísticas en dashboards
- Exportar reportes a Excel/PDF
- Consultar históricos de actividad
- Auditar acciones de usuarios

---

## 📊 Análisis del Estado Actual

### Dashboard Existente (Admin)
El `DashboardPage.tsx` actual tiene:
- ✅ Estructura visual con gráficos (Recharts)
- ❌ Datos hardcodeados (mock)
- ❌ Sin integración con API real

### Datos Disponibles en el Sistema
| Modelo | Métricas Posibles |
|--------|-------------------|
| `Document` | Documentos por período, por tipo, firmados vs pendientes |
| `DocumentBatch` | Lotes por período, procesados vs fallidos |
| `User` | Usuarios activos, por rol, por tenant |
| `VacationRequest` | Solicitudes por estado, por período, días usados |
| `Notification` | Notificaciones por tipo, leídas vs no leídas |
| `Tenant` | Métricas por organización |

---

## 📊 Fases de Implementación

### Fase 1: Backend - Endpoint de Estadísticas (1 día)
- [ ] Crear `ReportsController.php`
- [ ] Crear `ReportsService.php`
- [ ] Endpoint `GET /api/reports/dashboard` - Estadísticas generales
- [ ] Endpoint `GET /api/reports/documents` - Métricas de documentos
- [ ] Endpoint `GET /api/reports/vacations` - Métricas de vacaciones
- [ ] Endpoint `GET /api/reports/users` - Métricas de usuarios

### Fase 2: Backend - Exportación (1 día)
- [ ] Instalar `maatwebsite/excel` para exportar a Excel
- [ ] Endpoint `GET /api/reports/documents/export` - Exportar documentos
- [ ] Endpoint `GET /api/reports/vacations/export` - Exportar vacaciones  
- [ ] Endpoint `GET /api/reports/users/export` - Exportar usuarios
- [ ] Formato PDF opcional (con DomPDF)

### Fase 3: Backend - Auditoría (0.5 días)
- [ ] Crear migración `audit_logs`
- [ ] Crear modelo `AuditLog.php`
- [ ] Crear `AuditService.php` para registrar acciones
- [ ] Endpoint `GET /api/reports/audit` - Consultar logs
- [ ] Integrar con acciones clave (login, logout, firma, aprobar vacaciones)

### Fase 4: Frontend - Store y Repository (0.5 días)
- [ ] Crear `ReportsRepository.ts`
- [ ] Crear `reportsStore.ts` con Zustand
- [ ] Definir tipos en `Report.ts`
- [ ] Funciones para cada tipo de reporte

### Fase 5: Frontend - Dashboard Mejorado (1 día)
- [ ] Actualizar `AdminDashboardPage.tsx` con datos reales
- [ ] Gráfico de documentos por mes (real)
- [ ] Gráfico de estado de documentos (pie chart)
- [ ] Tabla de actividad reciente (real)
- [ ] Cards con métricas dinámicas

### Fase 6: Frontend - Página de Reportes (1 día)
- [ ] Crear `ReportsPage.tsx` - Página principal de reportes
- [ ] Filtros por fecha, tipo, tenant
- [ ] Tablas de datos paginadas
- [ ] Botones de exportación (Excel/PDF)
- [ ] Vista de auditoría

---

## 📝 Modelo de Datos - Auditoría

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    tenant_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,  -- 'user.login', 'document.signed', 'vacation.approved'
    entity_type VARCHAR(100) NULL, -- 'Document', 'VacationRequest', 'User'
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at DESC)
);
```

---

## 🔧 Endpoints API

### Dashboard Stats
```
GET /api/reports/dashboard
Response: {
    "documents": {
        "total": 1250,
        "signed": 980,
        "pending": 270,
        "by_month": [{ "month": "2024-01", "count": 45 }, ...]
    },
    "vacations": {
        "pending": 5,
        "approved": 42,
        "total_days_used": 156
    },
    "users": {
        "total": 85,
        "active": 78,
        "by_role": { "admin": 5, "client": 80 }
    }
}
```

### Exportación
```
GET /api/reports/documents/export?format=xlsx&start_date=2024-01-01&end_date=2024-12-31
GET /api/reports/vacations/export?format=xlsx&year=2024
GET /api/reports/audit/export?format=xlsx&last_days=30
```

### Auditoría
```
GET /api/reports/audit
Query params: user_id, action, entity_type, start_date, end_date, page, per_page
```

---

## 📦 Tipos de Reportes

| Reporte | Descripción | Filtros |
|---------|-------------|---------|
| Documentos | Lista de documentos con estado | Fecha, tipo, estado, usuario |
| Vacaciones | Solicitudes y días usados | Año, estado, empleado |
| Usuarios | Actividad de usuarios | Rol, estado, tenant |
| Auditoría | Log de acciones del sistema | Usuario, acción, fecha |

---

## 🎨 UI Design

### Dashboard Mejorado
```
┌─────────────────────────────────────────────────────────────────┐
│  Dashboard                                                       │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐               │
│  │  1,250  │ │   980   │ │   270   │ │    85   │               │
│  │ Docs    │ │ Firmados│ │Pendiente│ │ Usuarios│               │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘               │
│                                                                  │
│  ┌──────────────────────────┐ ┌──────────────────────────────┐ │
│  │     Documentos/Mes       │ │      Estado Documentos       │ │
│  │  📊 Bar Chart            │ │      🥧 Pie Chart           │ │
│  └──────────────────────────┘ └──────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │               Actividad Reciente                          │  │
│  │  User    │ Acción              │ Fecha     │ Estado      │  │
│  │  Juan    │ Firmó documento     │ Hace 5min │ ✅          │  │
│  │  María   │ Solicitó vacaciones │ Hace 1h   │ ⏳          │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Página de Reportes
```
┌─────────────────────────────────────────────────────────────────┐
│  Reportes                                          [📥 Exportar]│
├─────────────────────────────────────────────────────────────────┤
│  Tipo: [Documentos ▼]  Desde: [01/01/2024]  Hasta: [31/12/2024]│
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Documento        │ Usuario  │ Fecha    │ Estado         │  │
│  │  Boleta-001.pdf   │ Juan P.  │ 15/12/24 │ ✅ Firmado     │  │
│  │  Contrato-002.pdf │ María G. │ 14/12/24 │ ⏳ Pendiente   │  │
│  │  ...                                                      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  Mostrando 1-20 de 1,250          [< 1 2 3 ... 63 >]           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📁 Archivos a Crear

### Backend
```
backend/
├── app/
│   ├── Http/Controllers/Api/
│   │   └── ReportsController.php
│   ├── Services/
│   │   ├── ReportsService.php
│   │   └── AuditService.php
│   ├── Models/
│   │   └── AuditLog.php
│   └── Exports/
│       ├── DocumentsExport.php
│       ├── VacationsExport.php
│       └── UsersExport.php
└── database/migrations/
    └── YYYY_MM_DD_create_audit_logs_table.php
```

### Frontend
```
src/
├── core/domain/entities/
│   ├── Report.ts
│   └── AuditLog.ts
├── infrastructure/persistence/repositories/
│   └── ReportsRepository.ts
├── presentation/
│   ├── stores/
│   │   └── reportsStore.ts
│   └── pages/admin/
│       └── ReportsPage.tsx (nuevo)
```

---

## 🚀 Orden de Ejecución Sugerido

1. **Backend primero** (Fases 1-3)
   - Crear endpoints de estadísticas
   - Implementar exportación
   - Agregar auditoría básica

2. **Frontend después** (Fases 4-6)
   - Store y repository
   - Actualizar Dashboard con datos reales
   - Crear página de reportes

---

## ⚙️ Dependencias Necesarias

### Backend
```bash
composer require maatwebsite/excel  # Para exportar a Excel
composer require barryvdh/laravel-dompdf  # Para exportar a PDF (opcional)
```

### Frontend
- `recharts` - Ya instalado (para gráficos)
- No se necesitan nuevas dependencias

---

## 📋 Checklist de Inicio

Antes de comenzar:
- [x] Módulo 6 (Notificaciones) completado
- [x] Dashboard existente con estructura visual
- [x] Recharts ya instalado
- [ ] Decidir: ¿Incluir auditoría completa o básica?
- [ ] Decidir: ¿Exportar a PDF además de Excel?

---

*Creado: 2025-12-15 01:18*
