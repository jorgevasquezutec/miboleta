# 📋 TASK: Módulo 6 - Sistema de Notificaciones

**Fecha:** 2025-12-14  
**Estimación:** 5-6 días  
**Prioridad:** Alta

---

## 🎯 Objetivo

Implementar un sistema de notificaciones en tiempo real que permita a los usuarios recibir alertas sobre:
- Nuevos documentos disponibles
- Documentos firmados
- Solicitudes de vacaciones (creadas, aprobadas, rechazadas)
- Vacaciones pendientes de confirmar

---

## 📊 Fases de Implementación

### Fase 1: Backend - Modelo y Base de Datos (1 día) ✅
- [x] Crear migración `notifications`
- [x] Crear modelo `Notification.php`
- [x] Campos: id, user_id, tenant_id, type, title, message, data (JSON), read_at, created_at
- [x] Crear `NotificationResource.php`
- [x] Crear `NotificationService.php`

### Fase 2: Backend - API Endpoints (0.5 días) ✅
- [x] `GET /api/notifications` - Listar notificaciones del usuario
- [x] `GET /api/notifications/unread-count` - Contador de no leídas
- [x] `PUT /api/notifications/{id}/read` - Marcar como leída
- [x] `PUT /api/notifications/read-all` - Marcar todas como leídas
- [x] `DELETE /api/notifications/{id}` - Eliminar notificación

### Fase 3: Backend - Eventos y Listeners (1 día) ✅
- [x] Crear evento `NewDocumentAvailable`
- [x] Crear evento `NotificationCreated` (broadcast WebSocket)
- [ ] ~~Crear evento `DocumentSigned`~~ (integrado en NotificationService)
- [ ] ~~Crear evento `VacationRequestCreated`~~ (integrado en NotificationService)
- [ ] ~~Crear evento `VacationRequestApproved`~~ (integrado en NotificationService)
- [ ] ~~Crear evento `VacationRequestRejected`~~ (integrado en NotificationService)
- [ ] ~~Crear evento `VacationPendingConfirmation`~~ (integrado en NotificationService)
- [x] ~~Registrar listeners~~ → Llamadas directas desde servicios
- [x] Integrar con servicios existentes (VacationService, SendBatchNotifications)

### Fase 4: Backend - WebSockets (1 día) ✅
- [x] Configurar Laravel Reverb (configuración lista para uso)
- [x] Configurar broadcasting (`NotificationCreated` implementa `ShouldBroadcast`)
- [x] Broadcast eventos a canales privados por usuario (`private-user.{userId}`)
- [x] Configurar autenticación de canales (`routes/channels.php` ✅)

### Fase 5: Frontend - Store y Repository (0.5 días) ✅
- [x] Crear `NotificationRepository.ts`
- [x] Crear `notificationsStore.ts` con Zustand
- [x] Definir tipos en `Notification.ts`
- [x] Funciones: fetchNotifications, markAsRead, markAllAsRead, getUnreadCount

### Fase 6: Frontend - UI Components (1 día) ✅
- [x] `NotificationBell.tsx` - Icono con badge contador
- [x] ~~`NotificationDropdown.tsx`~~ (está integrado en NotificationBell)
- [x] ~~`NotificationItem.tsx`~~ (está integrado en NotificationBell)
- [x] `NotificationsPage.tsx` - Página con historial completo
- [x] Integrar en Navbar

### Fase 7: Frontend - WebSockets ✅
- [x] Instalar Laravel Echo y Pusher-js
- [x] Crear `echo.ts` con configuración Reverb
- [x] `connectWebSocket()` en store
- [x] Escuchar eventos en tiempo real
- [x] Actualizar badge al recibir notificación
- [x] Fallback a polling si WebSocket falla

### Fase 8: Testing e Integración (1 día) 🔄 EN PROGRESO
- [ ] Probar flujo completo de documentos con notificaciones
- [ ] Probar flujo completo de vacaciones con notificaciones
- [x] Verificar notificaciones en navegador (polling funciona)
- [ ] Probar WebSocket en tiempo real (requiere Reverb activo)
- [x] UI pulida con estilos Facebook-like

---

## 📝 Modelo de Datos

```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,  -- 'document.new', 'document.signed', 'vacation.approved', etc.
    title VARCHAR(255) NOT NULL,
    message TEXT NULL,
    data JSON NULL,  -- Datos adicionales (document_id, vacation_id, etc.)
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, read_at),
    INDEX idx_user_created (user_id, created_at DESC)
);
```

---

## 📦 Tipos de Notificación

| Tipo | Título | Destinatario |
|------|--------|--------------|
| `document.new` | Nuevo documento disponible | Empleado |
| `document.signed` | Documento firmado | Admin |
| `vacation.created` | Nueva solicitud de vacaciones | Supervisor |
| `vacation.approved` | Vacaciones aprobadas | Empleado |
| `vacation.rejected` | Vacaciones rechazadas | Empleado |
| `vacation.pending_confirmation` | Vacaciones por confirmar | Supervisor |

---

## 🔧 Endpoints API

```
GET    /api/notifications                    # Lista paginada
GET    /api/notifications/unread-count       # { count: 5 }
PUT    /api/notifications/{id}/read          # Marcar como leída
PUT    /api/notifications/read-all           # Marcar todas como leídas
DELETE /api/notifications/{id}               # Eliminar
```

---

## 🎨 UI Design

### Navbar Bell Icon
```
┌─────────────────────────────────────────────────────────┐
│  [Logo]    [Tenant Switcher]         [🔔(3)] [Avatar]  │
└─────────────────────────────────────────────────────────┘
                                          │
                                          ▼
                              ┌───────────────────────┐
                              │ Notificaciones        │
                              ├───────────────────────┤
                              │ 🔵 Nuevo documento    │
                              │    Boleta Dic 2024    │
                              │    hace 5 minutos     │
                              ├───────────────────────┤
                              │ ⚪ Vacaciones aprobadas│
                              │    15-20 Dic 2024     │
                              │    hace 1 hora        │
                              ├───────────────────────┤
                              │ [Ver todas]           │
                              └───────────────────────┘
```

---

## 📋 Checklist de Inicio ✅ COMPLETADO

Antes de comenzar, verificar:
- [x] Docker corriendo
- [x] Laravel API funcionando
- [x] Frontend funcionando
- [x] Decidir: ¿Con o sin WebSockets? → **Ambos implementados** (WebSocket + Polling fallback)

---

## 🚀 Orden de Ejecución ✅ COMPLETADO

1. **Backend primero** (Fases 1-3) ✅
   - Modelo, migración, endpoints
   - Eventos y NotificationService
   
2. **Frontend después** (Fases 5-6) ✅
   - Store y repository
   - Componentes UI

3. **WebSockets** (Fases 4 y 7) ✅
   - Implementado con Laravel Reverb
   - Fallback automático a polling

---

## ⚡ Decisión Tomada: ¡Ambos!

### Implementación Actual:
- ✅ **WebSockets (Laravel Reverb)** como método primario
- ✅ **Polling (30s)** como fallback automático si WebSocket falla
- ✅ Conexión automática al autenticarse el usuario
- ✅ Desconexión automática al hacer logout

### Flujo:
1. Usuario inicia sesión → `connectWebSocket(userId)`
2. Si WebSocket conecta → Tiempo real, polling se detiene
3. Si WebSocket falla → Fallback automático a polling cada 30s
4. Usuario cierra sesión → `disconnectWebSocket()`

---

## 📁 Archivos a Crear

### Backend (Implementado ✅)
```
backend/
├── app/
│   ├── Models/Notification.php
│   ├── Http/Controllers/Api/NotificationController.php
│   ├── Http/Resources/NotificationResource.php
│   ├── Services/NotificationService.php
│   └── Events/
│       ├── NewDocumentAvailable.php
│       ├── NotificationCreated.php (broadcast WebSocket)
│       ├── BatchCompleted.php
│       └── BatchProgress.php
└── database/migrations/
    └── 2025_12_15_044322_create_notifications_table.php
```

### Frontend (Implementado ✅)
```
src/
├── core/domain/entities/Notification.ts
├── infrastructure/
│   ├── persistence/repositories/NotificationRepository.ts
│   └── realtime/echo.ts (Laravel Echo config)
├── presentation/
│   ├── stores/notificationsStore.ts
│   ├── components/notifications/
│   │   ├── NotificationBell.tsx (bell + dropdown integrado)
│   │   └── index.ts
│   └── pages/shared/
│       └── NotificationsPage.tsx
```

---

## ✅ Estado Final: COMPLETADO

### Backend ✅ 100%
- ✅ Migración, modelo, controller, service, resource
- ✅ Evento `NotificationCreated` con broadcast
- ✅ Autenticación de canales en `routes/channels.php`
- ✅ Integración con VacationService y SendBatchNotifications

### Frontend ✅ 100%
- ✅ Repository, Store, Entity
- ✅ Laravel Echo configurado
- ✅ WebSocket + Polling fallback
- ✅ Componentes UI completos

### 💡 Para activar WebSockets en desarrollo/producción:
```bash
php artisan reverb:start
```

### Testing Recomendado
1. **Probar flujo documentos → notificación** completo
2. **Probar flujo vacaciones → notificación** completo  
3. **Verificar WebSocket funciona** con Reverb activo

### Mejoras Futuras (Opcional)
- [ ] Comando artisan para limpiar notificaciones viejas
- [ ] Preferencias de notificación por usuario
- [ ] Notificaciones push móviles (Firebase)
- [ ] Emails para notificaciones importantes

---

*Creado: 2025-12-14 23:35*  
*Última actualización: 2025-12-15 01:11*
