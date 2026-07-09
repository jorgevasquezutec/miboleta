# Pruebas QA — Sprint Fix: Listado de Cambios (MiBoleta)

| Campo | Valor |
|---|---|
| Versión | 1.0 |
| Fecha | 2026-07-09 |
| Rama | main |
| Alcance | Ítems del Excel "Listado de Cambios" con estado **Terminado** en este sprint |
| Fuente | `docs/sprintfix/Listado de Cambios.xlsx` · `Matriz de Accesos.xlsx` · `MAPEO-CARGA-MASIVA.md` |
| Audiencia | QA manual (no requiere conocimiento del código) |

> **Cómo usar este documento:** cada ítem es autocontenido y sigue la misma plantilla.
> Empieza por la Matriz Resumen (§0) para orientarte, ejecuta la Preparación (§1) una sola vez,
> y luego sigue el orden de los módulos A→G, pensado como flujo end-to-end.
> Convenciones: ✅ resultado esperado · ❌ debe fallar/bloquearse · 🔎 verificar en BD (ver §G).

## Índice
- [§0. Matriz resumen de ítems](#0-matriz-resumen-de-ítems)
- [§1. Preparación del entorno](#1-preparación-del-entorno)
- [A. Acceso, Login y RBAC](#a-acceso-login-y-rbac)
- [B. Carga masiva de usuarios](#b-carga-masiva-de-usuarios)
- [C. Vacaciones y Perfil](#c-vacaciones-y-perfil)
- [D. Menús de configuración](#d-menús-de-configuración)
- [E. Selector de empresa unificado (root)](#e-selector-de-empresa-unificado-root)
- [F. Seguridad / regresión negativa](#f-seguridad--regresión-negativa)
- [G. Verificación en base de datos](#g-verificación-en-base-de-datos)
- [Anexo: puntos abiertos a confirmar con negocio](#anexo-puntos-abiertos-a-confirmar-con-negocio)

---

## §0. Matriz resumen de ítems

| # | Ítem | Módulo | Rol(es) que prueban | Pantalla / Ruta | Resultado clave | Prioridad | ⚠ |
|---|------|--------|---------------------|-----------------|-----------------|-----------|---|
| 14 | Login con DNI | A | todos | `/login` | Autentica con DNI **o** correo + password | Alta | |
| 25 | Login único + RoleSwitcher | A | no-root con ≥2 roles | Navbar | Cambia el rol activo sin re-login | Alta | |
| 15 | Root crea admin por empresa | A | root | `/users/new` | Usuario con rol admin en la empresa elegida | Alta | |
| 21 | Rol jerárquico admin_tenant | A | root, admin_tenant | BD / badges | Rol `admin_tenant` (superior a admin) | Media | |
| 27 | Botón crear / carga masiva | A | root (crear), root/admin/admin_tenant (masiva) | `/users` | Botones visibles | Media | ⚠️ |
| 16 | Importar / Exportar usuarios | A | root/admin/admin_tenant | `/users`, sidebar "Carga Masiva" | Navegación y export Excel | Media | |
| 28 | Solo root elimina usuarios | A | root (sí), admin (no) | `/users` | admin recibe 403 al borrar | Alta | |
| 29 | Admin asigna Aprobador/Usuario | A | admin | `/users/new` | admin no puede asignar admin/admin_tenant | Alta | |
| 30 | Admin Tenant asigna Admin/Aprob/Usuario | A | admin_tenant | `/users/new` | admin_tenant sí asigna admin | Alta | |
| 31 | Aprobador como supervisor | A | root/admin/admin_tenant | `/users/new` (Supervisor) | aprobador seleccionable como jefe | Media | |
| 17/18 | Carga masiva de usuarios (flujo) | B | root/admin/admin_tenant | `/users/batch-upload` | Batch procesa y crea/actualiza usuarios | Alta | ⚠️ |
| — | Campos editables del grid | B | root/admin/admin_tenant | Grid de carga | Todas las columnas + multi-empresa editables | Alta | |
| — | Toggles correos / actualizar | B | root/admin/admin_tenant | Grid de carga | Se respetan los switches | Alta | |
| 19 | Contadores de empleados | B | root | `/tenants` | Carga inicial + total actual | Media | |
| 34 | Registro de inicio laboral | C | root/admin/admin_tenant | `/users/new` | `hire_date` por empresa | Alta | |
| 35 | Cálculo de saldo de vacaciones | C | todos | `/profile` / API | Saldo = inicial + devengo − tomados (30/15) | Alta | |
| 37 | Datos personales en Mi Perfil | C | todos | `/profile` | Fecha nacimiento visible (bug corregido) | Media | |
| 38 | Cuadro Vacaciones Disponibles | C | todos | `/profile` | Tarjeta con saldo de la empresa activa | Media | |
| 39 | Solicitud valida contra saldo | C | client/aprobador/admin | `/vacations/new` | Rechaza si excede saldo | Alta | |
| 40 | Aprobador visible en solicitud | C | client/aprobador/admin | `/vacations/new` | Muestra el supervisor asignado | Media | |
| 22 | Firma Digital (certificado) | D | root | `/signature-settings` | Sube/activa certificado .pfx | Alta | |
| 36 | Tamaño de boleta antes de carga | D | admin/client/admin_tenant | `/upload` | Firma posicionada por tamaño | **Crítica** | 🛑 |
| 24 | SMTP por empresa + Probar conexión | D | root | `/tenants/:id` | Config SMTP + test | Media | |
| 23 | IP Pública | D | root | `/platform-settings` | Guarda IP del servicio | Baja | |
| — | Selector de empresa unificado | E | root | Navbar | Un solo control filtra todo | Media | |

> Ítems **3 y 4** del Excel quedan fuera: son externos (plantilla legal y publicación a internet), no de código.

---

## §1. Preparación del entorno

### 1.1 Levantar el entorno
1. `pnpm run dev:local` — levanta Docker (backend, MySQL, Redis, nginx, Horizon, Reverb) y Vite. **Las migraciones corren automáticamente** al arrancar el contenedor `app`.
2. Accesos por defecto (con los puertos de dev local): Frontend `http://localhost:5173`, API `http://localhost:8090/api`, Adminer `http://localhost:8091`.
3. Si agregas migraciones nuevas y el contenedor ya estaba arriba: `docker compose up -d --force-recreate app` o `docker compose exec app php artisan migrate`.

> ⚠️ **BLOQUEANTE — Worker de colas (obligatorio para Carga Masiva):** la carga masiva de usuarios
> se procesa de forma asíncrona en la cola `bulk-uploads`. Si el worker/Horizon no está corriendo,
> **el batch queda en estado `processing` para siempre**. Verificar antes de probar el Módulo B.
> El contenedor `miboleta_horizon` debe estar `running` (`docker ps`). Si no, dentro del contenedor:
> `php artisan horizon` o `php artisan queue:work --queue=bulk-uploads`.

### 1.2 Usuarios de prueba por rol
Preparar (vía seeders o creándolos como root) al menos un usuario por rol. Todos con `document_text` (DNI) y `status = active`:

| Rol | Sugerencia usuario/DNI | Empresa(s) | Se usa en |
|-----|------------------------|------------|-----------|
| root | root@... / DNI root | (global, sin empresa) | 15, 21, 22, 23, 27, 28, 36, E |
| admin | admin@empresaA / DNI | Empresa A | 27, 29, 31, F |
| admin_tenant | admtenant@empresaA / DNI | Empresa A | 21, 30, callout Vacaciones |
| aprobador | aprob@empresaA / DNI | Empresa A | 31, 39, 40 |
| client (empleado) | user@empresaA / DNI | Empresa A | 14, 34–39 |

Para probar 25 (RoleSwitcher) se necesita **un usuario con 2 roles en la misma empresa** (p.ej. `admin` + `aprobador` en Empresa A).

### 1.3 Crear empresas de prueba
Como root: `Empresas` (`/tenants`) → "Nueva Empresa" → completar nombre, RUC, razón social, **Régimen Laboral** (General 30d / Micro 15d / Pequeña 15d — necesario para vacaciones). Crear **al menos 2 empresas** (A y B) para las pruebas multi-tenant y las negativas de seguridad (§F).

### 1.4 Alternar rol y empresa
- **RoleSwitcher** (rol activo): en el navbar, junto al selector de empresa. Solo aparece para no-root con ≥2 roles en la empresa activa. Cambia el menú/permisos al instante, sin recargar.
- **TenantSwitcher** (empresa activa, root): en el navbar. Para root muestra "Todas las empresas" (modo global) o una empresa específica; filtra Dashboard, Documentos, Usuarios y Auditoría.

### 1.5 Plantilla de ítem (referencia)
Cada ítem del documento usa esta estructura:
```
### [ID-nn] Título
Qué cambió · Dónde probar · Roles · Precondiciones · Pasos · Resultado esperado · Casos negativos · Notas/Endpoints
```

---

## A. Acceso, Login y RBAC

### [ID-14] Login con DNI (además de correo)

**Qué cambió:** El login acepta indistintamente el **número de documento (DNI)** o el **correo** en un único campo. El sistema detecta el formato y busca por email o por documento.

**Dónde probar:** Pantalla `/login` ("Iniciar Sesión") — campo con label **"DNI o correo electrónico"**.

**Roles:** Todos (cualquier usuario con `document_text` cargado y `status = active`).

**Precondiciones:**
- [ ] Usuario existente con DNI único y password conocido.
- [ ] Usuario en estado `active`.

**Pasos:**
1. Ir a `/login`.
2. Ingresar el **DNI** (ej. `43459099`) en el campo "DNI o correo electrónico" + password → Entrar.
3. Cerrar sesión y repetir el login con el **correo** del mismo usuario + password.

**Resultado esperado:**
- ✅ Login exitoso en **ambos** casos (DNI y correo), con la misma redirección según rol.
- 🔎 §G.1 para confirmar el usuario.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | DNI inexistente + password | ❌ 422 "Las credenciales proporcionadas son incorrectas." |
| N2 | DNI correcto + password incorrecto | ❌ 422 mismo mensaje |
| N3 | Usuario con `status != active` | ❌ Mensaje "Tu cuenta se encuentra inactiva…" |

**Notas / Endpoints:** `POST /api/login` (campo `login`). Test automatizado: `AuthenticationTest::test_user_can_login_with_document_number`.

---

### [ID-25] Login único + RoleSwitcher (rol activo en el navbar)

**Qué cambió:** Ya no hay login por tipo de usuario: todos entran por `/login`. Tras autenticar, el navbar muestra un **selector de rol activo** cuando el usuario tiene más de un rol en la empresa activa; el menú y los permisos se recalculan al instante (sin volver a autenticar).

**Dónde probar:** Navbar superior (junto al selector de empresa). No tiene ruta propia.

**Roles:** admin, admin_tenant, aprobador, client con ≥2 roles en la misma empresa. **root nunca ve el RoleSwitcher.**

**Precondiciones:**
- [ ] Un usuario con 2+ roles en la MISMA empresa (ej. `admin` + `aprobador` en Empresa A).

**Pasos:**
1. Login con ese usuario (por DNI o correo).
2. Verificar que el sidebar inicial corresponde al rol de mayor prioridad (orden: admin_tenant > admin > aprobador > client).
3. Abrir el RoleSwitcher del navbar → cambiar a "Aprobador".
4. Verificar que el menú lateral cambia al de Aprobador (Vacaciones, Mi Equipo…) **sin recargar** ni pedir credenciales.
5. Login como root → confirmar que el RoleSwitcher **no** aparece.

**Resultado esperado:**
- ✅ Cambio de rol activo instantáneo; sidebar y permisos reaccionan al nuevo rol.
- ✅ El cambio es solo del lado del cliente (no genera nuevo token ni cambia el rol en BD).

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Usuario con un solo rol | El selector se ve como etiqueta estática, sin dropdown |

**Notas / Endpoints:** `GET /api/me` arma `tenants[].roles[]` por empresa; el switch es client-side.

---

### [ID-15] Root crea cuentas Admin por empresa

**Qué cambió:** No hay pantalla dedicada; se usa el formulario genérico de usuario. Root selecciona una empresa y marca el rol "Administrador" en el checklist de esa empresa.

**Dónde probar:** `Usuarios` → "Nuevo Usuario" (`/users/new`) → sección **"Organizaciones, Roles y Supervisores"**.

**Roles:** root.

**Precondiciones:**
- [ ] Empresa ya creada (§1.3).

**Pasos:**
1. Login como root → `/users/new`.
2. Completar datos personales.
3. En "Organizaciones…", buscar y seleccionar la empresa.
4. Marcar el checkbox **"Administrador"**; opcionalmente fecha de inicio y saldo de vacaciones.
5. Guardar.

**Resultado esperado:**
- ✅ Usuario creado (201).
- 🔎 §G.1: `user_tenant_roles` tiene la fila `(user_id, tenant_id, role=admin)`.
- ✅ Al loguear ese usuario, aparece como "Administrador" de esa empresa.

**Casos negativos:** N/A (cubierto en §F la creación indebida de roles).

**Notas / Endpoints:** `POST /api/users` con `tenants_config[].role_ids`.

---

### [ID-21] Rol jerárquico admin_tenant (reemplaza "administrador_clientes")

**Qué cambió:** El rol `administrador_clientes` fue renombrado/elevado a **`admin_tenant`** ("Administrador de Empresa (Tenant)"), con permisos **superiores** a `admin` (puede gestionar admin, aprobador y usuario). Una migración de datos conserva a los usuarios ya asignados.

**Dónde probar:** No hay pantalla directa; se valida en la gestión de Usuarios (badges de rol) y en BD.

**Roles:** root (asigna), admin_tenant (afectado).

**Precondiciones:**
- [ ] BD migrada (`php artisan migrate`) y roles sembrados.

**Pasos:**
1. En Adminer/BD: `SELECT id,name,display_name FROM roles;` → debe existir `admin_tenant` y **no** `administrador_clientes`.
2. Login con un usuario que tenía el rol viejo → debe ver el label "Administrador de Empresa (Tenant)" y el menú de admin_tenant.
3. (Opcional, staging) probar `php artisan migrate:rollback` de ese step → revierte a `administrador_clientes`.

**Resultado esperado:**
- ✅ Un solo rol `admin_tenant` en `roles`, sin duplicados; usuarios previos preservan su `role_id` (rename in-place).

**Casos negativos:** N/A.

**Notas / Endpoints:** Migración `2026_07_04_000001_rename_administrador_clientes_to_admin_tenant`.

---

### [ID-27] Botón crear usuario unitario + botón Carga Masiva

**Qué cambió:** En la lista de usuarios se habilitaron los botones "Exportar", "Carga Masiva" y "Nuevo Usuario".

**Dónde probar:** `Usuarios` (`/users`), cabecera de la tarjeta.

**Roles:** "Carga Masiva" → root, admin, admin_tenant. "Nuevo Usuario" → **hoy solo root** (ver callout).

> ⚠️ **PUNTO ABIERTO — CONFIRMAR CON NEGOCIO:** el botón **"Nuevo Usuario"** hoy solo lo ve **root**;
> admin y admin_tenant **no** lo ven en la UI (aunque la ruta `/users/new` sí los deja entrar tecleando
> la URL, y el backend permite la creación). Igual pasa con los íconos Editar/Eliminar de la tabla.
> **No reportar como bug** hasta que negocio defina si admin/admin_tenant deben poder dar altas
> individuales por formulario o solo por Carga Masiva.

**Precondiciones:**
- [ ] Usuarios de prueba con rol admin y admin_tenant (no root).

**Pasos:**
1. Login como root → `/users` → verificar los 3 botones (Exportar, Carga Masiva, Nuevo Usuario).
2. Login como admin → `/users` → verificar que aparecen Exportar + Carga Masiva; **confirmar que NO aparece "Nuevo Usuario"** (comportamiento actual).
3. Repetir con admin_tenant.

**Resultado esperado:**
- ✅ root ve los 3 botones; admin/admin_tenant ven Exportar + Carga Masiva.

**Casos negativos:** ver §F (creación por API).

**Notas / Endpoints:** `POST /api/users` (backend permite root/admin/admin_tenant).

---

### [ID-16] Importar / Exportar usuarios

**Qué cambió:** El sidebar expone "Carga Masiva" y la lista de Usuarios tiene botón de exportar a Excel y de ir a la carga masiva.

**Dónde probar:** Sidebar → **"Carga Masiva"** (`/users/batch`, historial). En `/users`: botón **"Exportar"** y botón **"Carga Masiva"** (→ `/users/batch-upload`).

**Roles:** root, admin, admin_tenant. `client`/`aprobador` no lo ven.

**Precondiciones:**
- [ ] Al menos algunos usuarios cargados para exportar.

**Pasos:**
1. Login como admin → verificar "Carga Masiva" en el sidebar y en `/users`.
2. Sidebar "Carga Masiva" → debe ir a `/users/batch` (historial).
3. Botón "Carga Masiva" en `/users` → debe ir a `/users/batch-upload` (nueva carga).
4. Botón "Exportar" en `/users` → descarga `usuarios_<fecha>.xlsx`.

**Resultado esperado:**
- ✅ Navegación correcta; exportación descarga el Excel filtrado por la empresa activa.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Login como client → `GET /api/user-batches` | ❌ 403 |

**Notas / Endpoints:** rutas `/api/user-batches/*` (gate root/admin/admin_tenant).

---

### [ID-28] Solo Root puede eliminar usuarios

**Qué cambió:** Se reforzó en el **backend** que solo `root` puede eliminar usuarios (antes un admin del mismo tenant podía).

**Dónde probar:** `/users`, ícono de papelera por fila (visible solo para root) + diálogo "Eliminar Usuario".

**Roles:** root (positivo); admin, admin_tenant (deben ser rechazados).

**Precondiciones:**
- [ ] Un usuario "cliente" desechable en una empresa administrada por el admin de prueba.

**Pasos:**
1. Login como root → `/users` → eliminar un usuario cliente → confirmar.
2. Login como admin → intentar `DELETE /api/users/{id}` por API (el botón no está visible).
3. Login como admin_tenant → repetir por API.
4. Como root, intentar auto-eliminarse por API.

**Resultado esperado:**
- ✅ Paso 1: 200; 🔎 §G.1 `deleted_at` no nulo (soft delete); queda registro en Auditoría.
- ❌ Pasos 2–3: **403**, usuario NO eliminado.
- ❌ Paso 4: 403 (autoeliminación bloqueada).

**Casos negativos:** los pasos 2–4 son los negativos.

**Notas / Endpoints:** `DELETE /api/users/{id}` → `UserPolicy::delete` (root-only, no self-delete). Tests: `test_admin_cannot_delete_tenant_user`, `test_root_can_delete_user`.

---

### [ID-29] Admin solo puede asignar Aprobador / Usuario

**Qué cambió:** Un usuario con rol `admin` en una empresa solo puede asignar en esa empresa los roles **Aprobador** y **Usuario (client)**; no puede asignar Administrador ni Administrador de Empresa.

**Dónde probar:** `/users/new` o `/users/:id/edit` → tarjeta "Organizaciones, Roles y Supervisores" → checklist de roles (aparece filtrado).

**Roles:** admin (actuando en la empresa que gestiona).

**Precondiciones:**
- [ ] Usuario admin de Empresa A (para llegar a `/users/new`, hoy hay que teclear la URL o probar por API — ver callout ID-27).

**Pasos:**
1. Como admin de Empresa A, abrir el alta/edición de un usuario en Empresa A.
2. Verificar que el checklist de roles **solo** ofrece "Aprobador" y "Usuario" (no Administrador ni Administrador de Empresa).
3. (API) Enviar `tenants_config: [{tenant_id: A, role_ids: [id_admin]}]` → esperar 422.
4. (API) `role_ids: [id_admin_tenant]` → esperar 422.
5. Crear con `role_ids: [id_aprobador]` o `[id_client]` → 201.
6. Editar un usuario que **ya** tenía rol admin en esa empresa sin tocar ese campo → **200** (no se re-valida un rol que ya tenía).

**Resultado esperado:**
- ❌ 422 "No tienes permisos para asignar el rol seleccionado en esa empresa." al intentar elevar.
- ✅ 201/200 en los permitidos.

**Casos negativos:** pasos 3–4.

**Notas / Endpoints:** `POST/PUT /api/users` con `tenants_config.*.role_ids`.

---

### [ID-30] Admin Tenant puede asignar Admin / Aprobador / Usuario

**Qué cambió:** Un usuario con rol `admin_tenant` en una empresa puede asignar **Administrador, Aprobador y Usuario** (no admin_tenant ni root).

**Dónde probar:** Igual pantalla que ID-29; el checklist ofrece más opciones.

**Roles:** admin_tenant.

**Precondiciones:**
- [ ] Usuario admin_tenant de Empresa A.

**Pasos:**
1. Como admin_tenant de Empresa A, crear un usuario en Empresa A marcando "Administrador" → **201**.
2. Intentar marcar "Administrador de Empresa (Tenant)" → la opción **no** debe listarse; por API → 422.
3. (API) `role_ids: [id_root]` en `tenants_config` → 422 "No se puede asignar el rol root dentro de una empresa."

**Resultado esperado:**
- ✅ admin_tenant da de alta admin/aprobador/client.
- ❌ Cualquier intento de asignar admin_tenant o root es rechazado (422).

**Casos negativos:** pasos 2–3.

**Notas / Endpoints:** mismos que ID-29.

---

### [ID-31] Aprobador seleccionable como supervisor de vacaciones

**Qué cambió:** El selector "Supervisor / Jefe inmediato" ahora acepta usuarios con rol **admin, admin_tenant o aprobador** en la empresa (antes solo admin). Así un Aprobador puede ser el jefe que aprueba vacaciones del equipo.

**Dónde probar:** En `TenantAssignmentCard` (alta/edición de usuario), campo **"Supervisor / Jefe inmediato"**, visible al elegir una empresa.

**Roles:** root/admin/admin_tenant (quienes editan la ficha); el "supervisor" debe tener rol admin/admin_tenant/aprobador.

**Precondiciones:**
- [ ] Un usuario con rol `aprobador` (activo) en la empresa.

**Pasos:**
1. Precondición: "Ana Aprobadora" con rol aprobador en Empresa X.
2. Abrir alta/edición de otro usuario, seleccionar Empresa X, abrir el combo de Supervisor.
3. Verificar que "Ana Aprobadora" aparece **seleccionable** (badge "Aprobador").
4. Seleccionarla y guardar.
5. (Negativo) Un usuario `client` como candidato → aparece deshabilitado ("No supervisor").

**Resultado esperado:**
- ✅ El aprobador se puede seleccionar y persistir como supervisor. 🔎 §G.1 `user_tenants.supervisor_id`.
- ❌ Un `client` no puede; por API con `supervisor_id` de un client → 422.

**Casos negativos:** paso 5.

**Notas / Endpoints:** validación `tenants_config.*.supervisor_id` (roles válidos: admin, admin_tenant, aprobador).

---

## B. Carga masiva de usuarios

> ⚠️ **BLOQUEANTE:** el worker de colas (`miboleta_horizon`) debe estar corriendo (§1.1); si no,
> los batches quedan en `processing`. Verificar con `docker ps` antes de todo este módulo.

### [B-00] Flujo completo de carga masiva (ítems 17 y 18)

**Qué cambió:** Flujo end-to-end: descargar plantilla → subir Excel → previsualizar → **editar en grid** → confirmar. Procesamiento asíncrono por colas. Los toggles de "Enviar correo de bienvenida" y "Actualizar usuarios existentes" ahora se respetan de verdad.

**Dónde probar:** `Usuarios` → **"Carga Masiva"** → `/users/batch-upload` ("Nueva Carga Masiva de Usuarios"). Historial en `/users/batch`; detalle/progreso en `/users/batch/:id`.

**Roles:** root, admin, admin_tenant. (`client`/`aprobador` → 403).

**Precondiciones:**
- [ ] Worker de colas corriendo.
- [ ] Al menos una empresa creada (para asignar organizaciones).
- [ ] Un Excel de prueba (usar la plantilla descargable, o `docs/sprintfix/ESTRUCTURA DE CARGA MI BOLETA.xlsx`).

**Pasos:**
1. Ir a `/users/batch-upload` (o `/users/batch` → "Nueva Carga").
2. Clic **"Descargar Template Excel"**.
3. Llenar 2–3 filas válidas (nombre, apellido, email, tipo_doc, numero_doc, rol, estado…).
4. Arrastrar/seleccionar el archivo → aparece tarjeta verde con el nombre.
5. Clic **"Validar y Editar"** → se muestra el grid con contadores Total/Válidos/Errores/Advertencias.
6. (Opcional) Editar celdas en el grid (ver [B-01]).
7. Configurar los switches "Enviar correo de bienvenida" y "Actualizar usuarios existentes".
8. Clic **"Procesar N Usuarios"** → primero revalida, luego dispara el batch.
9. Redirige a `/users/batch/{id}` con el progreso en vivo (se refresca cada ~3s).

**Resultado esperado:**
- ✅ Toast "Carga iniciada: N usuarios"; el estado pasa `pending → processing → completed`/`partial`.
- ✅ La tarjeta muestra Creados / Actualizados / Errores y duración.
- 🔎 §G.2: fila en `user_batches` con `status='completed'` y contadores correctos; filas en `users` y `user_tenants`.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Login como client → entrar al flujo | ❌ 403 en los endpoints de batch |
| N2 | Worker de colas apagado | Batch queda en `processing` (verifica la precondición) |

**Notas / Endpoints:** `POST /api/user-batches/{template,validate,validate-data,upload-data}`; procesa en chunks de 50 en la cola `bulk-uploads`.

---

### [B-01] Campos editables del grid (todas las columnas + multi-empresa)

**Qué cambió:** Todas las columnas de la fila son editables inline, más un bloque por **empresa (organización)** con selector de "empresa activa" por fila y botones **+ / −** para agregar/quitar varias empresas por usuario. Se agregó la columna **fecha_nac** (fecha de nacimiento) de punta a punta.

**Dónde probar:** Grid dentro de `/users/batch-upload` en modo editor.

**Roles:** root, admin, admin_tenant.

**Precondiciones:**
- [ ] Haber validado un archivo (modo editor activo).

**Pasos:**
1. Editar celdas de la fila: nombre, apellido, email, **tipo_doc** (select), numero_doc, **rol** (select), **estado** (select), telefono, **fecha_nac** (selector de fecha).
2. En el bloque azul de empresa: **Organización** (RUC), **Supervisor**, **Roles empresa**, **Fecha ingreso**, **Saldo vacac.**, **Departamento**, **Cargo**.
3. Clic en **+** (columna "Empresa") → aparece "Org 2" con los campos vacíos → llenarla.
4. Cambiar el selector "Empresa" de vuelta a "Org 1" → los valores de Org 1 se conservan (no se pisan).
5. Clic en **×** estando en "Org 2" → se elimina esa organización.

**Resultado esperado:**
- ✅ Todas las columnas del Excel tienen su control editable en la web (paridad 1:1).
- ✅ Al corregir una celda inválida desaparece el error rojo y suben los contadores de "válidos".
- 🔎 §G.2: `user_tenants` refleja las organizaciones que quedaron, con `department`, `position`, `hire_date`, `vacation_balance_initial`; `users.birth_date` poblado.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Seleccionar rol "root" en el grid | La opción **no existe** en el select (client/admin/admin_tenant/aprobador) |
| N2 | Agregar orgs más allá del límite | El botón **+** se deshabilita al llegar al máximo configurado |

**Notas / Endpoints:** el grid envía todo al confirmar (`validate-data`/`upload-data`).

---

### [B-02] Toggles: enviar correos / actualizar existentes

**Qué cambió:** Los switches "Enviar correo de bienvenida" (default **ON**) y "Actualizar usuarios existentes" (default **OFF**) se guardan en el batch y el worker los respeta (antes se ignoraban).

**Dónde probar:** Panel "Opciones de procesamiento" (azul) en el grid de `/users/batch-upload`.

**Roles:** root, admin, admin_tenant.

**Precondiciones:**
- [ ] Para "actualizar existentes": una fila cuyo email/documento **ya exista** en BD.

**Pasos:**
1. Cargar 2 usuarios nuevos con "Enviar correo de bienvenida" **ON** → confirmar que se despacha el mail de bienvenida a cada uno.
2. Repetir con el switch **OFF** → los usuarios se crean pero **no** se envía correo.
3. Subir una fila con un usuario existente con "Actualizar usuarios existentes" **OFF** → la fila se **omite** (no cambia el usuario, `updated_users` no sube).
4. Repetir con **ON** → el usuario existente se **actualiza** (datos y organizaciones se mergean; la **contraseña nunca se toca**; `birth_date` solo se pisa si la fila trae valor).

**Resultado esperado:**
- ✅ El comportamiento depende solo de los switches. 🔎 §G.2: `SELECT processing_options FROM user_batches` refleja lo elegido; `updated_users` sube solo con "actualizar" ON.

**Casos negativos:** ver §F (un admin no puede cambiar email ni elevar rol de un existente vía "actualizar").

**Notas / Endpoints:** `processing_options.{send_welcome_emails, update_existing}` leídos en `ProcessUserChunk`.

---

### [B-03] Formato de plantilla y mapeo del Excel del cliente

**Qué cambió:** El importador acepta, además del formato canónico, los encabezados de la **plantilla real del cliente** (APEPAT, APEMAT, TIPDOC, DOCIDEN, ESTADO, ORGANIZACIÓN, DEPARTAMENTO, CARGO, SALDO/PERIODO DE VACACIONES) mediante una capa de alias. Se agregaron **Departamento** y **Cargo** al modelo. Ver `docs/sprintfix/MAPEO-CARGA-MASIVA.md`.

**Dónde probar:** Subir `docs/sprintfix/ESTRUCTURA DE CARGA MI BOLETA.xlsx` en `/users/batch-upload`.

**Roles:** root, admin, admin_tenant.

**Precondiciones:**
- [ ] Una empresa cuyo nombre/RUC coincida con la columna ORGANIZACIÓN del Excel de prueba.

**Pasos:**
1. Subir la plantilla del cliente → validar.
2. Verificar en el preview que los datos aparecen mapeados a las columnas internas.
3. Fila con ORGANIZACIÓN = RUC de 11 dígitos existente → sin warning.
4. Fila con ORGANIZACIÓN = nombre de empresa inexistente → **warning** "organizacion".
5. Fila con DEPARTAMENTO/CARGO → verificar que se guardan.
6. Fila con PERIODO DE VACACIONES = "2024" y sin fecha de ingreso → `hire_date` = 2024-01-01.

**Resultado esperado:**
- ✅ La plantilla del cliente carga; los alias mapean TIPDOC (DNI/CE/PAS), ESTADO (ACTIVO/INACTIVO), ORGANIZACIÓN, DEPARTAMENTO/CARGO.
- 🔎 §G.2: `user_tenants.department` y `.position` poblados.

**Casos negativos:** paso 4 (warning por empresa no resuelta).

**Notas / Endpoints:** alias en `UsersImport::normalizeRow`. Los alias aplican a la 1ª organización (`org1_*`).

---

### [ID-19] Contadores de empleados en Empresas

**Qué cambió:** Dos indicadores nuevos en Empresas: **Carga inicial** (empleados de la primera carga masiva, se fija una sola vez) y **Total actual** (conteo en vivo), más "Agregados después".

**Dónde probar:**
- **Lista** `/tenants`: columna **"Empleados"** (badge con total + "+N desde carga inicial").
- **Editar empresa** `/tenants/:id`: tarjeta **"Contador de empleados"** (Carga inicial / Total actual / Agregados después, solo lectura).

**Roles:** root (Empresas es solo root).

**Precondiciones:**
- [ ] Una empresa nueva sin cargas previas (`initial_employee_count = 0`).

**Pasos:**
1. Crear empresa nueva → en `/tenants` la columna "Empleados" muestra `0`.
2. Hacer una carga masiva que cree 3 usuarios para esa empresa → esperar `completed`.
3. Recargar `/tenants` → "Empleados" = `3` (sin "+N").
4. En `/tenants/:id` (editar empresa): Carga inicial=3, Total actual=3, Agregados después=0.
5. Crear un 4º usuario para esa empresa → lista: `4` con "+1 desde carga inicial"; form: inicial=3, actual=4, después=1.
6. Segunda carga masiva sobre la misma empresa → **Carga inicial NO cambia** (sigue 3), solo sube el total.

**Resultado esperado:**
- ✅ `initial_employee_count` se escribe una sola vez; el total siempre refleja los usuarios reales.
- 🔎 §G.4.

**Casos negativos:** N/A.

**Notas / Endpoints:** `GET /api/tenants` (vía `TenantResource`); fijación en `UserBatch::syncInitialEmployeeCounts`.

---

## C. Vacaciones y Perfil

> ℹ️ **COMPORTAMIENTO ACTUAL (control de acceso):** el rol **admin_tenant NO tiene navegación a
> Vacaciones** (ni menú ni ruta), aunque su rol incluya el permiso `approve_vacations`. Verificar
> que un admin_tenant no puede entrar a `/vacations` aunque conozca la URL. No es bug; es intencional.

### [ID-34] Registro de inicio laboral (fecha de ingreso)

**Qué cambió:** Se agregó "Fecha de inicio laboral" (`hire_date`) **por empresa**, junto con "Saldo inicial de vacaciones". Es la base del cálculo de vacaciones.

**Dónde probar:** `Usuarios` → Nuevo/Editar Usuario → tarjeta de la empresa: campos **"Fecha de inicio laboral"** y **"Saldo inicial de vacaciones (días)"**. También por carga masiva (columna `org1_fecha_ingreso`).

**Roles:** root, admin, admin_tenant.

**Precondiciones:**
- [ ] Empresa con Régimen Laboral configurado.

**Pasos:**
1. Alta/edición de usuario → seleccionar empresa → ingresar "Fecha de inicio laboral" (ej. 2023-01-15).
2. Guardar y reabrir la edición → el valor persiste.

**Resultado esperado:**
- ✅ 🔎 §G.3: `user_tenants.hire_date` = fecha ingresada.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Fecha inválida en carga masiva | ❌ Fila rechazada: "Fecha de ingreso inválida en org1_fecha_ingreso" |

**Notas / Endpoints:** `tenants_config.*.hire_date`.

---

### [ID-35] Cálculo de saldo de vacaciones disponible

**Qué cambió:** El saldo se calcula automáticamente según el **régimen** de la empresa (30 días general / 15 días MYPE) y el **devengo por aniversarios** cumplidos desde `hire_date`.
Fórmula: `disponible = saldo_inicial + devengo − días_tomados`, con `devengo = años_completos × días_por_año`.

**Dónde probar:** Indirecto, vía "Vacaciones Disponibles" en Mi Perfil (ID-38) o directo al endpoint.

**Roles:** todos consultan su propio saldo; root puede consultar el de cualquier empresa.

**Precondiciones:**
- [ ] Empresa con `labor_regime`. Usuario con `hire_date` (sin él, devengo = 0).

**Pasos:**
1. Empresa Régimen General; usuario con `hire_date` = hoy − 2 años y 1 mes, saldo inicial 0.
2. Consultar `GET /api/vacation-requests/balance?tenant_id={id}`.
3. Verificar `years_of_service = 2`, `accrued = 60` (2×30), `available = 60`.
4. Repetir con empresa Micro/Pequeña → `days_per_year = 15`.
5. Caso sin `hire_date` → `accrued = 0`, `available = saldo_inicial`.

**Resultado esperado:**
- ✅ JSON con `labor_regime`, `days_per_year`, `initial`, `accrued`, `taken`, `available`, `hire_date`, `years_of_service`.

**Casos negativos:** N/A (casos borde incluidos arriba).

**Notas / Endpoints:** `GET /api/vacation-requests/balance` (`VacationBalanceService`).

---

### [ID-37] Mi Perfil: datos personales (fecha de nacimiento corregida)

**Qué cambió:** El campo **fecha de nacimiento** (`birth_date`) no llegaba a la app y siempre se veía "No registrada"; ahora sí aparece. Mi Perfil muestra nombre, correo, teléfono, fecha de nacimiento y fecha de ingreso.

**Dónde probar:** `Mi Perfil` (`/profile`), tarjeta "Editar Información Personal".

**Roles:** todos.

**Precondiciones:**
- [ ] Usuario con `birth_date` cargado en BD.

**Pasos:**
1. Asegurar `birth_date` no nulo para el usuario de prueba.
2. Ir a `/profile` → verificar que "Fecha de Nacimiento" muestra la fecha (no "No registrada").
3. Verificar "Fecha de Ingreso ({empresa activa})" con el `hire_date` de la empresa activa.
4. (API) `GET /api/me` y `GET /api/profile` → el JSON incluye `"birth_date"`.

**Resultado esperado:**
- ✅ Fecha de nacimiento visible; Nombre/Apellido/Teléfono editables; Correo/Fecha Nacimiento/Fecha Ingreso solo lectura.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Usuario sin `birth_date` | Muestra "No registrada" (no falla) |

**Notas / Endpoints:** `GET /api/me`, `GET /api/profile` (ambos incluyen `birth_date`).

---

### [ID-38] Cuadro "Vacaciones Disponibles" en Mi Perfil

**Qué cambió:** Tarjeta en Mi Perfil con el saldo de vacaciones de la empresa activa.

**Dónde probar:** `/profile`, tarjeta **"Vacaciones Disponibles"** (al pie).

**Roles:** todos (con empresa activa).

**Precondiciones:**
- [ ] Usuario con `hire_date` en al menos una empresa.

**Pasos:**
1. Login con ese usuario → `/profile`.
2. Verificar la tarjeta: Días disponibles (grande), Saldo inicial, Devengadas, Tomadas, Días/año (con etiqueta de régimen).
3. Si es multi-empresa, cambiar la empresa activa → el saldo se recalcula.

**Resultado esperado:**
- ✅ Valores coinciden con la fórmula de ID-35. Sin empresa activa → la tarjeta no se muestra.

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Error de red | Mensaje "No se pudo cargar el saldo de vacaciones para esta empresa." |

**Notas / Endpoints:** `GET /api/vacation-requests/balance?tenant_id={currentTenant}`.

---

### [ID-39] Registrar solicitud validando contra el saldo

**Qué cambió:** Al crear una solicitud, se valida que los días pedidos no excedan el saldo disponible; si excede, se rechaza.

**Dónde probar:** `Vacaciones` → "Mis Vacaciones" (`/vacations`) → **"Nueva Solicitud"** (`/vacations/new`).

**Roles:** root, admin, client, aprobador. (**admin_tenant no tiene acceso** — ver callout del módulo.)

**Precondiciones:**
- [ ] Usuario con **supervisor asignado** en la empresa (si no, no puede solicitar).
- [ ] Saldo disponible conocido.

**Pasos:**
1. `/vacations/new` → (si multi-empresa) seleccionar empresa → se carga el saldo.
2. Rango de fechas con días ≤ saldo → "Enviar Solicitud".
3. (Negativo frontend) Rango > saldo → el botón se **deshabilita** con "No puedes solicitar más de N días disponibles."
4. (Negativo backend/API) `POST /api/vacation-requests` con días > saldo → **400** "No tienes saldo de vacaciones suficiente…".
5. (Solapamiento) Intentar fechas que se cruzan con otra solicitud → error "Ya tienes vacaciones solicitadas o aprobadas en esas fechas."

**Resultado esperado:**
- ✅ Solicitud creada (`status=pending`), notificación/email al supervisor.
- ❌ Excede saldo → 400, no se crea.

**Casos negativos:** pasos 3–5.

**Notas / Endpoints:** `POST /api/vacation-requests`.

---

### [ID-40] Aprobador asignado visible al registrar la solicitud

**Qué cambió:** El formulario de nueva solicitud muestra automáticamente el **supervisor/aprobador** que la revisará.

**Dónde probar:** `/vacations/new`, caja azul superior.

**Roles:** client, aprobador, admin, root.

**Precondiciones:**
- [ ] Usuario con `supervisor_id` asignado para la empresa.

**Pasos:**
1. `/vacations/new` con un usuario que tiene supervisor → verificar la línea "Tu solicitud será revisada por **{nombre}**".
2. (Negativo) Usuario/empresa **sin** supervisor → alerta roja "No tienes un aprobador asignado…" y el botón "Enviar Solicitud" deshabilitado.
3. (E2E) El supervisor entra a `Vacaciones` → "Mi Equipo" (`/team-vacations`) y aprueba/rechaza.

**Resultado esperado:**
- ✅ Nombre del aprobador visible antes de enviar; sin aprobador → envío bloqueado.

**Casos negativos:** paso 2.

**Notas / Endpoints:** `GET /api/vacation-requests/balance` devuelve `approver`; aprobación `PUT /api/vacation-requests/{id}/approve|reject`.

---

## D. Menús de configuración

### [ID-22] Firma Digital (carga del certificado)

**Qué cambió:** Nueva pantalla para gestionar el **certificado único de la plataforma** (firma electrónica legal, PAdES).

**Dónde probar:** Sidebar (solo root) → **"Firma Digital"** → `/signature-settings`.

**Roles:** **root únicamente.**

**Precondiciones:**
- [ ] Certificado `.pfx`/`.p12` + su contraseña. (Opcional) URL TSA.

**Pasos:**
1. Login como root → "Firma Digital" → verificar estado inicial "Desactivada".
2. Subir el `.pfx`, ingresar contraseña, (opcional) URL TSA → **"Cargar Certificado"**.
3. Verificar "Certificado cargado: Sí" con titular y fecha.
4. Activar el switch "Activar firma digital".
5. (Negativo) Intentar activar **sin** certificado → bloqueado con toast.
6. "Eliminar Certificado" → confirmar → firma queda desactivada.

**Resultado esperado:**
- ✅ Un solo certificado por plataforma; activar/desactivar/eliminar funcionan.
- ❌ No-root en `/signature-settings` → redirigido.

**Casos negativos:** pasos 5 y el acceso no-root.

**Notas / Endpoints:** `GET/PUT /api/signature/settings`, `POST/DELETE /api/signature/certificate`.

---

### [ID-36] Tamaño de boleta antes de la carga de documentos

**Qué cambió:** Se agregó un selector de **"Tamaño de página de la boleta"** en el Paso 3 de la carga masiva de **documentos**, para calibrar dónde se estampa la firma según el tamaño real del PDF.

**Dónde probar:** `Documentos` → **"Cargar Documentos"** (`/upload`) → Paso 3 "Configuración de Carga" → campo **"Tamaño de página de la boleta *"**.

**Roles:** admin, client, admin_tenant.

> 🛑 **PRUEBA VISUAL OBLIGATORIA — PRIORIDAD CRÍTICA:** las coordenadas de firma para **A4, A5 y
> Carta** son **derivadas matemáticamente** (nunca calibradas contra una boleta real en esos formatos);
> **solo A10 está probado en producción**. QA **debe** subir una boleta real en cada tamaño, firmarla
> (flujo 2FA) y verificar **a ojo** que el nombre/fecha de firma caen **dentro** del recuadro "RECIBÍ
> CONFORME / TRABAJADOR", sin salirse ni superponerse a otro texto. No basta con que "funcione".

**Precondiciones:**
- [ ] ZIP con PDFs de boletas reales de los tamaños a probar.
- [ ] Certificado de firma configurado y activo (ID-22) para probar la firma.

**Pasos:**
1. `/upload` → subir el ZIP (pasos 1/2).
2. Paso 3: elegir Tipo de Documento, Período y **Tamaño de página** (probar A10, A4, A5, Carta).
3. Marcar "Requiere firma digital".
4. Confirmar la carga.
5. 🔎 §G.4: `document_batches.page_size` = valor elegido.
6. Firmar un documento del batch (2FA) y **verificar visualmente** la posición de la firma en el PDF.

**Resultado esperado:**
- ✅ Campo obligatorio, default "A10"; el tamaño se persiste en el batch.
- ✅ (A10) firma en la posición correcta, como en producción.
- 🛑 (A4/A5/Carta) firma **dentro** del recuadro — reportar si se sale (calibración a ajustar).

**Casos negativos:** posición de firma fuera del recuadro en A4/A5/Carta (esperable hasta calibrar → reportar).

**Notas / Endpoints:** `POST /api/document-batches/upload` (campo `page_size`).

---

### [ID-24] Servidor de correo (SMTP) por empresa + Probar conexión

**Qué cambió:** Configuración SMTP propia por empresa (opcional, con fallback al correo de la plataforma) y botón para **probar la conexión** ya guardada.

**Dónde probar:** `Empresas` → Editar Empresa (`/tenants/:id`) → sección **"Servidor de correo (SMTP)"** con badge "Correo propio"/"Correo de la plataforma" y botón **"Probar conexión"**.

**Roles:** root (frontend y backend refuerzan root-only).

**Precondiciones:**
- [ ] Empresa existente. Para probar, debe tener host + correo remitente **guardados**.

**Pasos:**
1. Como root, Editar Empresa → completar Host, Puerto, Usuario, Contraseña, Cifrado.
2. **Guardar** (botón general) — el test prueba la config **guardada**, no la recién tipeada.
3. Verificar badge "Correo propio" y que "Probar conexión" se habilita.
4. Clic "Probar conexión" → éxito: toast verde; fallo: toast rojo con detalle.
5. (Negativo) Sin host/correo → botón deshabilitado (tooltip); por API responde `{success:false,...}`.
6. (Negativo) No-root llamando `POST /api/tenants/{id}/smtp/test` → **403**.

**Resultado esperado:**
- ✅ Config SMTP persistida; dejar la contraseña vacía al editar **mantiene** la anterior; el test hace un handshake real sin persistir nada.

**Casos negativos:** pasos 5–6.

**Notas / Endpoints:** `PUT /api/tenants/{id}`, `POST /api/tenants/{id}/smtp/test`.

---

### [ID-23] IP Pública (registro manual)

**Qué cambió:** Nueva pantalla para registrar manualmente la **IP pública del servicio** (informativa, para compartir con terceros; no se detecta automáticamente).

**Dónde probar:** Sidebar (solo root) → **"IP Pública"** → `/platform-settings`.

**Roles:** **root únicamente.**

**Precondiciones:** ninguna.

**Pasos:**
1. Login como root → "IP Pública".
2. Ingresar una IP (ej. `200.10.20.30`) → "Guardar".
3. Verificar toast "IP pública actualizada exitosamente" y "Última actualización".
4. Recargar la página → el valor persiste.
5. (Opcional) Vaciar y guardar → persiste como vacío.

**Resultado esperado:**
- ✅ Valor persistido y visible tras recarga; es solo informativo (no cambia el comportamiento).
- ❌ No-root → bloqueado por la ruta.

**Casos negativos:** acceso no-root.

**Notas / Endpoints:** `GET/PUT /api/platform/settings`.

---

## E. Selector de empresa unificado (root)

### [E-00] Un solo control de empresa para root

**Qué cambió:** Para root, el selector de empresa del **navbar** es ahora el **único** control; filtra Dashboard, Documentos, Usuarios y Auditoría a la vez. Se quitaron los dropdowns de empresa locales que había en esas pantallas. En móvil el selector sigue visible y el dropdown ahora es **blanco** (antes se veía oscuro).

**Dónde probar:** Navbar (arriba), afecta `/admin` (Dashboard), `/documents`, `/users`, `/audit-logs`.

**Roles:** root.

**Precondiciones:**
- [ ] Al menos 2 empresas activas.

**Pasos:**
1. Login como root.
2. Verificar que en Dashboard, Documentos, Usuarios y Auditoría **no** hay un segundo selector de empresa (solo el del navbar).
3. Cambiar de empresa en el navbar → las 4 pantallas reflejan el filtro sin recargar.
4. Elegir "Todas las empresas" → datos globales.
5. Cambiar de empresa estando en la **página 2+** de una lista → la lista **vuelve a la página 1** (no queda vacía).
6. Abrir el dropdown → fondo **blanco** (consistente con el resto de la plataforma).
7. Reducir a móvil (<768px) → el selector sigue visible.

**Resultado esperado:**

| Pantalla | Al elegir empresa X | Al elegir "Todas" |
|---|---|---|
| Dashboard | métricas de X | agregado global |
| Documentos | documentos de X | todos |
| Usuarios | usuarios de X | todos |
| Auditoría | auditoría de X | toda |

**Casos negativos:**
| # | Acción | Resultado esperado |
|---|--------|--------------------|
| N1 | Login como no-root | Ve su selector multi-empresa normal (sin cambios) |

**Notas / Endpoints:** el navbar escribe el filtro global (header `X-Tenant-Ids`) y la empresa activa.

---

## F. Seguridad / regresión negativa

Estas pruebas confirman que las restricciones de seguridad reforzadas en el sprint funcionan. Ejecutar con Postman/DevTools donde diga "vía API". Ninguna debe dejar registros en BD (verificar con §G.5).

| # | Escenario | Cómo ejecutar | Rol atacante | Esperado |
|---|-----------|---------------|--------------|----------|
| S1 | Crear usuario con rol **root** | `POST /api/users` con `role_id` = id de root | admin | ❌ 422 "No tienes permisos para asignar el rol root." |
| S2 | Elevar a **admin_tenant** por API | `POST /api/users` con `tenants_config.role_ids=[admin_tenant]` | admin | ❌ 422 |
| S3 | Elevar a **root/admin_tenant** por **carga masiva** | Excel con fila `rol=root` (o `admin_tenant` en empresa ajena) | admin | ❌ Fila rechazada; nunca crea root |
| S4 | **Eliminar** un usuario | `DELETE /api/users/{id}` (UI + API) | admin / admin_tenant | ❌ 403 (solo root) |
| S5 | Adjuntar usuario a **empresa ajena** | `POST /api/users` con `tenant_id` de una empresa que no administra | admin | ❌ 422 "No tienes permisos para asignar usuarios a esa empresa." |
| S6 | Adjuntar a empresa ajena por **carga masiva** | Excel con `org1_ruc` de empresa ajena | admin | ❌ Fila rechazada |
| S7 | Cambiar **email** de un existente vía "actualizar existentes" | Carga masiva `update_existing=ON` con email nuevo | admin (no-root) | ❌ El email NO cambia |
| S8 | **admin_tenant** entra a Vacaciones | Navegar a `/vacations` por URL | admin_tenant | ❌ Sin acceso (no hay ruta) |
| S9 | Rol **root** en columna del grid de carga | Grid de carga masiva | admin | ❌ La opción "root" no existe en el select |

**Resultado esperado global:** todos ❌ (bloqueados). 🔎 §G.5 para confirmar que no quedó ningún registro creado/elevado.

---

## G. Verificación en base de datos

Conexión: Adminer en `http://localhost:8091` (servidor `db`, usuario `root`, password `root`, base `miboleta`), o `docker compose exec db mysql -uroot -proot miboleta`.

### G.1 Usuarios y roles (módulos A, F)
```sql
-- Datos y roles de un usuario (por DNI)
SELECT u.id, u.name, u.last_name, u.email, u.document_text, u.birth_date, u.status, u.deleted_at
FROM users u WHERE u.document_text = '{DNI}';

-- Roles por empresa del usuario
SELECT utr.user_id, utr.tenant_id, r.name AS rol
FROM user_tenant_roles utr JOIN roles r ON r.id = utr.role_id
WHERE utr.user_id = {id};

-- Catálogo de roles (ítem 21)
SELECT id, name, display_name FROM roles;
```

### G.2 Carga masiva (módulo B)
```sql
-- Estado y contadores del último batch (detectar 'processing' colgado)
SELECT id, uuid, status, total_rows, processed_rows, created_users, updated_users, failed_rows,
       processing_options
FROM user_batches ORDER BY id DESC LIMIT 1;

-- Pivote empresa-usuario con los campos nuevos
SELECT user_id, tenant_id, department, position, hire_date, vacation_balance_initial, supervisor_id, is_primary
FROM user_tenants WHERE user_id = {id};
```

### G.3 Vacaciones (módulo C)
```sql
-- hire_date por empresa y régimen de la empresa
SELECT ut.user_id, ut.tenant_id, ut.hire_date, ut.vacation_balance_initial, t.labor_regime
FROM user_tenants ut JOIN tenants t ON t.id = ut.tenant_id WHERE ut.user_id = {id};

-- Solicitudes de vacaciones
SELECT id, user_id, tenant_id, start_date, end_date, days_requested, status FROM vacation_requests
WHERE user_id = {id} ORDER BY id DESC;
```

### G.4 Configuración (módulo D)
```sql
-- Contadores de empleados (ítem 19)
SELECT id, name, initial_employee_count,
       (SELECT COUNT(*) FROM user_tenants ut WHERE ut.tenant_id = tenants.id) AS total_actual
FROM tenants WHERE id = {id};

-- SMTP por empresa (ítem 24) y tamaño de boleta del batch (ítem 36)
SELECT id, name, mail_host, mail_port, mail_username, mail_encryption FROM tenants WHERE id = {id};
SELECT id, page_size, status FROM document_batches ORDER BY id DESC LIMIT 5;
```

### G.5 Seguridad (módulo F)
```sql
-- Confirmar que NO se creó un usuario root/admin_tenant indebido tras un intento negativo
SELECT u.id, u.email, r.name FROM users u
JOIN user_tenant_roles utr ON utr.user_id = u.id
JOIN roles r ON r.id = utr.role_id
WHERE r.name IN ('root','admin_tenant') ORDER BY u.id DESC LIMIT 10;

-- Confirmar que un usuario objetivo de intento de borrado por admin sigue vivo
SELECT id, email, deleted_at FROM users WHERE id = {id};
```

---

## Anexo: puntos abiertos a confirmar con negocio

| # | Punto | Detalle | Acción sugerida |
|---|-------|---------|-----------------|
| A1 | Botón "Nuevo Usuario" (ítem 27) | Hoy solo lo ve root; admin/admin_tenant no (aunque backend/ruta lo permiten). | Definir si admin/admin_tenant deben crear altas individuales por formulario o solo por Carga Masiva. |
| A2 | admin_tenant y Vacaciones | Su rol tiene `approve_vacations` pero no tiene navegación a Vacaciones. | Confirmar si debe poder aprobar vacaciones (habilitar menú/ruta) o no. |
| A3 | Firma A4/A5/Carta (ítem 36) | Coordenadas derivadas, no validadas visualmente. | QA valida visualmente y, si hace falta, ajustar la calibración por tamaño antes de producción. |
| A4 | Auditoría por empresa (root) | El modelo de Auditoría no filtra por header; se filtra por parámetro. | Evaluar a futuro darle el mismo scope que Documentos para consistencia. |

---

*Fin del documento. Trazabilidad de IDs contra `docs/sprintfix/Listado de Cambios.xlsx`.*
