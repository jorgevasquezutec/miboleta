# Cotización - Módulo de Gestión de Vacaciones

**Fecha:** 1 de diciembre de 2025  
**Proyecto:** MiBoleta - Sistema de Gestión Documental  
**Módulo:** Sistema de Vacaciones

---

## Precio Total: S/ 3,500

**Horas totales:** 50 horas  
**Tarifa por hora:** S/ 70

---

## Desglose Detallado

### Backend Laravel

| Componente | Horas | Tarifa | Subtotal |
|------------|-------|--------|----------|
| Modelos + Migraciones (VacationRequest, Approver, Notification) | 4h | S/ 70 | S/ 280 |
| API Controllers + Validaciones | 5h | S/ 70 | S/ 350 |
| Sistema de Emails con plantillas | 5h | S/ 70 | S/ 350 |
| Jobs y Queues | 3h | S/ 70 | S/ 210 |
| Notificaciones WebSocket (Laravel Echo) | 6h | S/ 70 | S/ 420 |
| **Subtotal Backend** | **23h** | | **S/ 1,610** |

### Frontend React

| Componente | Horas | Tarifa | Subtotal |
|------------|-------|--------|----------|
| Vista Configuración de Aprobadores | 4h | S/ 70 | S/ 280 |
| Vista Solicitudes (Crear/Listar) | 6h | S/ 70 | S/ 420 |
| Bandeja de Aprobación con filtros | 5h | S/ 70 | S/ 350 |
| Componente de Notificaciones en tiempo real | 4h | S/ 70 | S/ 280 |
| Módulo de Reportería + Exportación Excel | 7h | S/ 70 | S/ 490 |
| **Subtotal Frontend** | **26h** | | **S/ 1,820** |

### Testing & Deploy

| Componente | Horas | Tarifa | Subtotal |
|------------|-------|--------|----------|
| Testing funcional y ajustes | 5h | S/ 70 | S/ 350 |
| Integración y documentación | 2h | S/ 70 | S/ 140 |
| **Subtotal Testing** | **7h** | | **S/ 490** |

---
<!-- 
## Funcionalidades Incluidas

### 1. Configuración de Aprobadores (Admin Plataforma)
- CRUD completo de aprobadores por organización
- Interfaz de asignación de aprobadores
- Validaciones de permisos por rol

### 2. Solicitudes de Vacaciones (Usuarios)
- Formulario de solicitud con:
  - Selección de fechas (inicio/fin)
  - Cálculo automático de días
  - Campo de motivo/observaciones
- Dashboard de mis solicitudes con estados
- Validación de días disponibles
- Historial de solicitudes

### 3. Sistema de Aprobación (Admin Organización)
- Bandeja de solicitudes pendientes
- Detalle completo de cada solicitud
- Aprobación/Rechazo con comentarios
- Filtros por estado y usuario
- Contador de notificaciones

### 4. Notificaciones Dual
- **Email:**
  - Email a aprobadores cuando se crea solicitud
  - Link directo para aprobar/rechazar
  - Email al empleado cuando se aprueba/rechaza
  - Email al admin plataforma de cada aprobación
  - Plantillas HTML personalizadas
  
- **WebSocket (Tiempo Real):**
  - Notificación en bandeja al crear solicitud
  - Notificación al aprobar/rechazar
  - Contador de notificaciones sin leer
  - Badge visual de nuevas notificaciones

### 5. Reportería Completa
- **Filtros:**
  - Rango de fechas
  - Estados (pendiente, aprobado, rechazado)
  - Usuarios (búsqueda)
  - Organización (solo admin plataforma)
  
- **Características:**
  - Tabla paginada optimizada
  - Exportación a Excel (.xlsx)
  - Vista Admin Plataforma: todos los registros
  - Vista Admin Organización: filtrado por organización
  - Métricas y estadísticas

### 6. Backend (API RESTful)
- **Modelos:**
  - `VacationRequest` (solicitudes)
  - `Approver` (aprobadores)
  - `Notification` (notificaciones)
  
- **Endpoints:**
  - `POST /api/vacation-requests` - Crear solicitud
  - `GET /api/vacation-requests` - Listar solicitudes
  - `PUT /api/vacation-requests/{id}` - Actualizar estado
  - `GET /api/vacation-requests/report` - Reporte con filtros
  - `POST /api/approvers` - Configurar aprobadores
  - `GET /api/approvers` - Listar aprobadores
  
- **Características:**
  - Validaciones robustas
  - Políticas de acceso por rol
  - Jobs para emails asíncronos
  - Queue system configurado
  - Middlewares de autenticación

---

## Jobs y Queues Incluidos

### 1. SendVacationRequestEmail Job
- Envío de email a aprobadores cuando se crea solicitud
- Incluye datos del empleado, fechas y link de acción
- Token único para aprobación/rechazo directo

### 2. SendVacationStatusEmail Job
- Email al empleado notificando resultado
- Email al admin plataforma por cada aprobación
- Incluye comentarios del aprobador

### 3. SendReminderEmail Job (Opcional)
- Recordatorio a aprobadores con solicitudes pendientes >48h
- Ejecutado por Laravel Scheduler

### 4. Configuración de Queue
- Setup de queue driver (database/Redis)
- Migraciones de tabla jobs
- Configuración de reintentos y timeouts

---

## Resumen del Proyecto Completo

```
┌──────────────────────────────────────────────┐
│ SISTEMA COMPLETO - MIBOLETA                  │
├──────────────────────────────────────────────┤
│ Sistema Base (Documentos + Multi-tenant)     │
│ • Arquitectura completa                      │
│ • Gestión de usuarios y organizaciones       │
│ • CRUD de documentos                         │
│ • Docker + Deploy                            │
│ Precio: S/ 10,000                            │
├──────────────────────────────────────────────┤
│ Módulo de Vacaciones                         │
│ • Solicitudes y aprobaciones                 │
│ • Notificaciones (Email + WebSocket)         │
│ • Reportería con exportación Excel           │
│ • 50 horas de desarrollo                     │
│ Precio: S/ 3,500                             │
├──────────────────────────────────────────────┤
│ TOTAL INVERSIÓN:           S/ 13,500         │
└──────────────────────────────────────────────┘
```

---

## Entregables

1. **Código Backend:**
   - Modelos y migraciones Laravel
   - Controllers con validaciones
   - Jobs y configuración de queues
   - Sistema de notificaciones

2. **Código Frontend:**
   - Componentes React con TypeScript
   - Vistas completas y responsivas
   - Integración con WebSocket
   - Exportación a Excel

3. **Testing:**
   - Unit tests de modelos
   - Integration tests de API
   - Testing funcional completo

4. **Documentación:**
   - Documentación técnica
   - Guía de uso
   - API documentation

5. **Deploy:**
   - Configuración en producción
   - Migraciones ejecutadas
   - Variables de entorno configuradas

---

## Tecnologías Utilizadas

**Backend:**
- Laravel 11.x
- MySQL 8.0
- Laravel Echo + Pusher/Socket.io
- Queue System (Database/Redis)
- Mailtrap/SMTP para emails

**Frontend:**
- React 18 + TypeScript
- Zustand (state management)
- React Router v7
- Tailwind CSS + shadcn/ui
- Laravel Echo (WebSocket client)
- XLSX para exportación Excel

---

## Notas Importantes

1. **Aprovecha infraestructura existente:**
   - Sistema multi-tenant ya configurado
   - Gestión de usuarios y roles funcionando
   - Backend Laravel + Frontend React operativos
   - Docker y deploy ya configurados

2. **Sistema escalable:**
   - Queue system para alta concurrencia
   - WebSocket para tiempo real
   - Optimizado para múltiples organizaciones

3. **Mantenible:**
   - Código limpio siguiendo Clean Architecture
   - Documentación completa
   - Tests incluidos

---

## Condiciones

- **Forma de pago:** A acordar con el cliente
- **Tiempo de desarrollo:** 2-3 semanas
- **Garantía:** 30 días de soporte post-entrega
- **Mantenimiento:** A cotizar por separado

---

## Contacto

Para más información o ajustes a la cotización, contactar al equipo de desarrollo.

---

**Documento generado:** 1 de diciembre de 2025  
**Válido por:** 30 días -->
