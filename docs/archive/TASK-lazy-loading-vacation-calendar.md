# Task: Lazy Loading de Rutas + Calendario Visual de Vacaciones

**Fecha:** 2025-12-15  
**Estimación:** 1-2 horas  
**Prioridad:** Media

---

## 📋 Objetivo

Implementar dos mejoras de UX y performance:

1. **Lazy Loading de Rutas** - Cargar páginas bajo demanda para reducir bundle inicial
2. **Calendario Visual de Vacaciones** - Visualización tipo calendario de las solicitudes

---

## 🎯 Parte 1: Lazy Loading de Rutas

### Beneficios:
- Reduce el tamaño del bundle inicial (~1.8MB → chunks más pequeños)
- Mejora el tiempo de carga inicial
- Carga páginas solo cuando se navega a ellas

### Implementación:

```typescript
// Antes (carga estática)
import { DashboardPage } from '@/presentation/pages/admin';

// Después (lazy loading)
const DashboardPage = lazy(() => import('@/presentation/pages/admin/DashboardPage'));
```

### Archivos a Modificar:
- [ ] `src/presentation/routes/index.tsx` - Implementar lazy loading
- [ ] Envolver rutas con `<Suspense fallback={<Loading />}>`

### Páginas a hacer Lazy:

**Admin:**
- [ ] DashboardPage
- [ ] UsersListPage, UserFormPage, UserDetailPage
- [ ] TenantsListPage, TenantFormPage
- [ ] DocumentsListPage, BatchesListPage, BatchDetailPage
- [ ] VacationHistoryPage, VacationApprovalsPage, VacationConfirmationPage, TeamVacationsPage
- [ ] AuditLogsPage, SettingsPage

**Employee:**
- [ ] DashboardPage (employee)
- [ ] DocumentUploadView, DocumentViewerView
- [ ] VacationRequestsListPage, VacationRequestFormPage

**Auth:**
- [ ] LoginView
- [ ] ForgotPasswordPage, ResetPasswordPage, ForceChangePasswordPage

**Shared:**
- [ ] ProfilePage, NotificationsPage

---

## 🗓️ Parte 2: Calendario Visual de Vacaciones

### Objetivo:
Mostrar un calendario mensual con las vacaciones de los empleados para visualizar fácilmente:
- Días de vacaciones aprobadas
- Conflictos de fechas entre empleados
- Estado de las solicitudes por color

### Diseño:

```
┌─────────────────────────────────────────────────────┐
│  ◄  Diciembre 2025  ►                               │
├─────┬─────┬─────┬─────┬─────┬─────┬─────────────────┤
│ Lun │ Mar │ Mié │ Jue │ Vie │ Sáb │ Dom             │
├─────┼─────┼─────┼─────┼─────┼─────┼─────────────────┤
│  1  │  2  │  3  │  4  │  5  │  6  │  7              │
│     │ 🟢  │ 🟢  │ 🟢  │     │     │                 │
├─────┼─────┼─────┼─────┼─────┼─────┼─────────────────┤
│  8  │  9  │ 10  │ 11  │ 12  │ 13  │ 14             │
│ 🟡  │ 🟡  │     │     │     │     │                 │
└─────┴─────┴─────┴─────┴─────┴─────┴─────────────────┘

🟢 Aprobadas  🟡 Pendientes  🔴 Rechazadas
```

### Componentes a Crear:

1. **VacationCalendar.tsx** - Componente principal del calendario
   - Vista mensual con navegación
   - Muestra días con vacaciones marcados
   - Click en día para ver detalles

2. **VacationCalendarDay.tsx** - Celda individual del calendario
   - Indicadores de color por estado
   - Tooltip con nombres de empleados

3. **VacationCalendarLegend.tsx** - Leyenda de colores

### Colores por Estado:
- 🟢 Verde (`#10B981`) - Aprobadas / Tomadas
- 🟡 Amarillo (`#F59E0B`) - Pendientes
- 🔴 Rojo (`#EF4444`) - Rechazadas
- 🔵 Azul (`#3B82F6`) - En confirmación

### Integración:
- [ ] Agregar tab "Calendario" en TeamVacationsPage
- [ ] O crear página separada VacationCalendarPage

### API Backend (si se necesita):
```
GET /api/vacation-requests/calendar?month=12&year=2025
```

Retorna vacaciones agrupadas por día del mes.

---

## 📁 Archivos a Crear/Modificar

### Lazy Loading:
```
src/presentation/routes/index.tsx  (modificar)
src/presentation/components/shared/PageLoader.tsx  (crear - spinner de carga)
```

### Calendario:
```
src/presentation/components/features/vacations/
├── VacationCalendar.tsx
├── VacationCalendarDay.tsx
├── VacationCalendarLegend.tsx
└── index.ts (actualizar exports)
```

---

## ✅ Pasos de Ejecución

### Fase 1: Lazy Loading (30 min)
1. [ ] Crear componente PageLoader (spinner de carga)
2. [ ] Modificar routes/index.tsx para usar lazy()
3. [ ] Envolver rutas con Suspense
4. [ ] Verificar que todas las rutas cargan correctamente
5. [ ] Verificar reducción del bundle inicial

### Fase 2: Calendario de Vacaciones (1 hora)
1. [ ] Crear VacationCalendar.tsx
2. [ ] Crear VacationCalendarDay.tsx
3. [ ] Crear VacationCalendarLegend.tsx
4. [ ] Integrar en TeamVacationsPage como nuevo tab
5. [ ] Probar navegación entre meses
6. [ ] Probar visualización de diferentes estados

### Fase 3: Verificación
1. [ ] `npm run build` - sin errores
2. [ ] Verificar chunks generados (más pequeños)
3. [ ] Probar calendario con datos reales

---

## 📝 Notas

- El calendario debe funcionar para supervisores (ver su equipo)
- Considerar responsive para móviles (scroll horizontal)
- Los días festivos podrían marcarse en gris (futuro)

---

*Creado: 2025-12-15*
