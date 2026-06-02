## 1. Cambio de correo solo root + aviso

- [x] 1.1 Añadir guard que restrinja el cambio de `email` al rol root (en `UserController::update` o `UpdateUserRequest`): si el `email` cambia y el actor no es root, rechazar el cambio
- [x] 1.2 Confirmar que el perfil de auto-servicio (`UpdateProfileRequest`) NO incluye `email` (se mantiene como hoy)
- [x] 1.3 Verificar el envío de `EmailChangedNotificationMail` ya existente en `UserService::updateUser` (al correo anterior y al nuevo, tras commit, con try/catch + log)
- [x] 1.4 Probado e2e (curl): admin no-root → **403** ("Solo el rol root..."); root → **200**; aviso encolado sin fallo (Horizon activo → Mailtrap). Confirmación visual en Mailtrap queda para el usuario

## 2. Dashboard del empleado con totales reales

- [x] 2.1 Exponer totales individuales del usuario en el backend (`ReportsService::getMyDocumentStats` + endpoint `GET /api/reports/my-stats`)
- [x] 2.2 En `src/presentation/pages/employee/DashboardPage.tsx`, reemplazar el cálculo desde la lista paginada por el consumo de los totales reales (`/reports/my-stats`)
- [x] 2.3 Sin cambios en `reportsStore.ts`: se consume vía `apiClient` directo (el dashboard del empleado no usa ese store)
- [x] 2.4 Probado e2e (curl): `/reports/my-stats` devuelve totales reales del usuario (cliente `{0,0,0}`, admin `{1,0,1}`), coincidiendo con la BD

## 3. Dashboard del admin a nivel organización

- [x] 3.1 Endurecer `ReportsController::getTenantId()` para usuarios no-root sin tenant primario (fallback al primer tenant; sentinela `-1` para evitar exponer todos los tenants)
- [x] 3.2 Revisar `ReportsService::getVacationStats()`: se documenta que es anual por diseño (expone `current_year`)
- [x] 3.3 Probado e2e (curl): `/reports/dashboard` admin → `tenant_id=1`, users.total=7 (solo su empresa); root → `tenant_id=null`, users.total=22 (consolidado)
- [x] 3.4 **Fix raíz del "dashboard en 0"**: el default del período pasó a **"Todo el tiempo"** (frontend) y las sumatorias respetan el rango solo cuando se envía (backend). Por defecto muestran totales reales; al elegir rango, se filtran. Probado e2e: sin rango → total=14/firmados=10/pend=2; rango may–jun → 0
- [x] 3.5 El rango controla **tarjetas + gráfico de tendencia** de forma consistente (`getDocumentStats` y `getDocumentsByMonth` reciben `start_date/end_date`). Botón "Todo el tiempo" en `admin/DashboardPage.tsx` para volver a totales reales. TypeScript sin errores en los archivos modificados

## 4. Verificaciones (sin cambio de comportamiento)

- [ ] 4.1 Abrir una boleta en el visor de PDF en línea y confirmar render, paginado, zoom y miniaturas (verificación UI)
- [x] 4.2 Verificado: el LOG **NO** cubre el CRUD de usuarios. `AuditService::logUserCreated/Updated/Deleted` existen pero **no se invocan** en `UserController`/`UserService`; no hay `UserObserver`. Sí se audita login/logout, firma y vacaciones. → Requiere decisión de alcance (ver 4.3)
- [x] 4.3 Cableado `logUserCreated/Updated/Deleted` en `UserController` (store/update/destroy) con autor automático (Auth::user); probado por tinker (registra `user.updated` para User:2)

## 5. Dashboard del empleado — selector de período + filtros combinados

- [x] 5.1 `my-stats` acepta rango+buscador+tipo (no estado): `DocumentService::getMyDocumentStats` reutiliza `applyOptionalFilters` (sin `status`); `ReportsController::myStats` arma los filtros del request
- [x] 5.2 Frontend `employee/DashboardPage.tsx`: selector de período + botón "Todo el tiempo" (default); el rango se pasa a la lista (`dateFrom/dateTo`, plumbing ya existente) y a `my-stats`
- [x] 5.3 Filtros combinados (AND): rango + buscador + estado + tipo en la lista; tarjetas reflejan rango+buscador+tipo
- [x] 5.4 Probado e2e: `my-stats` sin filtros → `{1,0,1}`; rango may–jun → `{0,0,0}`; rango ene–mar → `{1,0,1}`. TS sin errores en los archivos modificados

## 6. Cierre

- [x] 6.1 `openspec validate ajustes-sin-costo --strict` en verde
- [ ] 6.2 Verificación visual del usuario (visor PDF, Mailtrap, dashboards) y, al confirmar, `openspec archive ajustes-sin-costo`
