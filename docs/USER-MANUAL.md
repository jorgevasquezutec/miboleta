# MiBoleta - Manual de Usuario

## Información General

| Atributo                          | Valor                                       |
| --------------------------------- | ------------------------------------------- |
| **Nombre del Sistema**      | MiBoleta                                    |
| **Versión**                | 1.0.0                                       |
| **Fecha de Documentación** | Enero 2026                                  |
| **Tipo**                    | Sistema de Gestión Documental y Vacaciones |

---

## Descripción General

**MiBoleta** es una plataforma empresarial diseñada para la gestión integral de documentos laborales y solicitudes de vacaciones. El sistema permite a las organizaciones:

- **Cargar y distribuir documentos** (boletas de pago, contratos, liquidaciones) a sus empleados
- **Firma digital con verificación en dos pasos** para garantizar la autenticidad
- **Gestión de solicitudes de vacaciones** con flujo de aprobación por supervisores
- **Auditoría completa** de todas las acciones del sistema

---

## Roles del Sistema

| Rol              | Descripción                   | Permisos Principales                         |
| ---------------- | ------------------------------ | -------------------------------------------- |
| **Root**   | Super administrador            | Acceso total, gestión multi-tenant          |
| **Admin**  | Administrador de organización | Gestión de usuarios, documentos, vacaciones |
| **Client** | Empleado                       | Ver documentos, firmar, solicitar vacaciones |

---

## 1. Autenticación

### 1.1 Página de Inicio de Sesión

![Login Page](images/01_login_page.png)

**Descripción:**
La página de login proporciona acceso seguro al sistema mediante credenciales de usuario.

**Elementos de la interfaz:**

| Elemento                                       | Descripción                                 |
| ---------------------------------------------- | -------------------------------------------- |
| **Logo MiBoleta**                        | Identidad visual del sistema                 |
| **Campo Email**                          | Entrada para correo electrónico corporativo |
| **Campo Contraseña**                    | Entrada segura con opción mostrar/ocultar   |
| **Botón "Iniciar Sesión"**             | Ejecuta la autenticación                    |
| **Enlace "¿Olvidaste tu contraseña?"** | Inicia flujo de recuperación                |
| **Pie de página**                       | Información legal y copyright               |

**Flujo de autenticación:**

1. Usuario ingresa email y contraseña
2. Sistema valida credenciales contra base de datos
3. Si es válido: genera token JWT y redirige al dashboard correspondiente
4. Si es inválido: muestra mensaje de error

---

# PARTE I: ROL ROOT (Super Administrador)

---

## 2. Panel de Root (Super Administrador)

### 2.1 Dashboard de Root

![Root Dashboard](images/15_root_dashboard.png)

**Descripción:**
El dashboard de Root proporciona una vista global de todas las organizaciones del sistema, con acceso a funciones de administración multi-tenant.

**Diferencias con Admin:**

| Característica                    | Root                     | Admin                 |
| ---------------------------------- | ------------------------ | --------------------- |
| **Alcance**                  | Todas las organizaciones | Solo su organización |
| **Crear empresas**           | Sí                      | No                    |
| **Crear usuarios globales**  | Sí                      | Solo de su empresa    |
| **Filtro por organización** | Sí                      | No                    |

**Secciones del sidebar exclusivas de Root:**

| Sección             | Función                         |
| -------------------- | -------------------------------- |
| **Empresas**   | Crear y gestionar organizaciones |
| **Usuarios**   | Gestión global de usuarios      |
| **Documentos** | Vista global de documentos       |
| **Auditoría** | Logs de todo el sistema          |

**Filtro de organización:**
Root puede filtrar toda la información por organización específica o ver "Todas las organizaciones" simultáneamente.

---

## 3. Gestión de Empresas (Rol: Root)

### 3.1 Lista de Empresas

![Companies List](images/16_root_companies_list.png)

**Descripción:**
Permite al Root visualizar y gestionar todas las organizaciones registradas en el sistema.

**Cards de métricas:**

| Métrica            | Descripción                   |
| ------------------- | ------------------------------ |
| **Total**     | Organizaciones registradas     |
| **Activas**   | Empresas con acceso habilitado |
| **Inactivas** | Empresas deshabilitadas        |

**Columnas de la tabla:**

| Columna                 | Contenido                          |
| ----------------------- | ---------------------------------- |
| **RUC**           | Identificador fiscal de la empresa |
| **Razón Social** | Nombre legal de la empresa         |
| **Teléfono**     | Contacto de la empresa             |
| **Estado**        | Badge (Activo/Inactivo)            |
| **Acciones**      | Editar, Desactivar                 |

### 3.2 Formulario de Nueva Empresa

![Company Creation Form](images/17_root_company_form.png)

**Descripción:**
Formulario para crear una nueva organización en el sistema.

**Campos del formulario:**

| Campo                | Tipo   | Requerido | Descripción                       |
| -------------------- | ------ | --------- | ---------------------------------- |
| **Logo**       | Imagen | No        | Logo de la empresa (drag-and-drop) |
| **Nombre**     | Text   | Sí       | Razón social de la empresa        |
| **RUC**        | Text   | Sí       | Identificador fiscal único        |
| **Teléfono**  | Text   | No        | Número de contacto                |
| **Dirección** | Text   | No        | Dirección física                 |
| **Estado**     | Toggle | Sí       | Activo / Inactivo                  |

**Flujo de creación:**

1. Root accede a Empresas > "+ Organización"
2. Completa los datos requeridos
3. Opcionalmente sube logo de la empresa
4. Guarda la nueva organización
5. La empresa queda disponible para asignar usuarios

---

## 4. Gestión Global de Usuarios (Rol: Root)

### 4.1 Lista de Usuarios

![Root Users List](images/18_root_users_list.png)

**Descripción:**
Vista global de todos los usuarios del sistema, con capacidad de filtrar por organización.

**Filtros disponibles:**

| Filtro                  | Función                |
| ----------------------- | ----------------------- |
| **Buscar**        | Por nombre, email o DNI |
| **Organización** | Filtrar por empresa     |
| **Rol**           | Root, Admin, Usuario    |
| **Estado**        | Activo / Inactivo       |

**Columnas de la tabla:**

| Columna              | Contenido                |
| -------------------- | ------------------------ |
| **Nombre**     | Nombre completo + email  |
| **Documento**  | DNI del usuario          |
| **Rol**        | Badge de rol             |
| **Tenants**    | Organizaciones asignadas |
| **Supervisor** | Jefe inmediato asignado  |
| **Estado**     | Activo / Inactivo        |
| **Acciones**   | Editar, Ver              |

### 4.2 Formulario de Nuevo Usuario (Parte Superior)

![User Creation Form Top](images/19_root_user_form_top.png)

**Descripción:**
Sección superior del formulario para crear un nuevo usuario.

**Campos de información personal:**

| Campo                          | Tipo   | Requerido | Descripción                |
| ------------------------------ | ------ | --------- | --------------------------- |
| **Nombre**               | Text   | Sí       | Nombre del usuario          |
| **Apellido**             | Text   | Sí       | Apellido del usuario        |
| **Email**                | Email  | Sí       | Correo electrónico (login) |
| **Tipo de Documento**    | Select | Sí       | DNI, Pasaporte, etc.        |
| **Número de Documento** | Text   | Sí       | Número del documento       |
| **Teléfono**            | Text   | No        | Número de contacto         |

### 4.3 Formulario de Nuevo Usuario (Parte Inferior)

![User Creation Form Bottom](images/20_root_user_form_bottom.png)

**Descripción:**
Sección inferior del formulario con configuración de rol, estado y asignación a organizaciones.

**Campos de configuración:**

| Campo                    | Tipo         | Descripción                 |
| ------------------------ | ------------ | ---------------------------- |
| **Rol**            | Select       | Root, Admin, Usuario         |
| **Estado**         | Select       | Activo / Inactivo            |
| **Organizaciones** | Multi-select | Empresas a las que pertenece |

**Asignación de Supervisor por Empresa:**

Al seleccionar una organización para el usuario, aparece un campo adicional para asignar el **supervisor** de ese usuario **específicamente para esa empresa**. Esto permite:

- Un usuario puede pertenecer a múltiples empresas
- Cada empresa puede tener un supervisor diferente para el mismo usuario
- El supervisor aprueba las vacaciones del usuario en esa organización

**Proceso de creación de usuario:**

1. Root accede a Usuarios > "+ Nuevo Usuario"
2. Completa información personal (nombre, email, DNI)
3. Selecciona el rol (Admin o Usuario)
4. Establece el estado (Activo por defecto)
5. Busca y selecciona las organizaciones
6. Para cada organización, asigna el supervisor correspondiente
7. Guarda el usuario
8. El usuario recibe email para establecer su contraseña

**Gestión de contraseñas:**

El sistema utiliza un flujo de invitación por email:

- Al crear un usuario, se envía automáticamente un correo
- El usuario establece su contraseña mediante el enlace recibido
- Para restablecer contraseña: el usuario usa "¿Olvidaste tu contraseña?"
- Root/Admin puede forzar un restablecimiento desde el panel

**Desactivar un usuario:**

1. Ir a la lista de usuarios
2. Clic en "Editar" del usuario
3. Cambiar Estado a "Inactivo"
4. Guardar cambios
5. El usuario no podrá iniciar sesión

---

# PARTE II: ROL ADMIN (Administrador de Organización)

---

## 5. Panel de Administrador

### 5.1 Dashboard de Administrador

![Admin Dashboard](images/08_admin_dashboard.png)

**Descripción:**
Vista principal del administrador con métricas globales de la organización.

**Secciones del sidebar:**

| Sección                     | Función                   |
| ---------------------------- | -------------------------- |
| **Dashboard**          | Vista principal            |
| **Cargar Documentos**  | Carga masiva ZIP           |
| **Usuarios**           | Gestión de empleados      |
| **Lotes de Carga**     | Historial de cargas        |
| **Documentos**         | Buscador de documentos     |
| **Auditoría**         | Registro de actividad      |
| **Mi Equipo**          | Vacaciones de subordinados |
| **Histórico General** | Todas las vacaciones       |

**Métricas principales:**

| Métrica                      | Descripción                    |
| ----------------------------- | ------------------------------- |
| **Total Documentos**    | Cantidad total cargados         |
| **Usuarios Activos**    | Empleados en la organización   |
| **Documentos Firmados** | Porcentaje de cumplimiento      |
| **Pendientes**          | Documentos sin firmar           |
| **Huérfanos**          | Documentos sin usuario asignado |

**Gráficos:**

- **Documentos Cargados:** Barras por mes (últimos 6 meses)
- **Estado de Documentos:** Gráfico circular (firmados vs pendientes)

**Panel de Vacaciones:**

- Solicitudes pendientes, aprobadas, rechazadas
- Días consumidos por la organización

---

## 6. Carga Masiva de Documentos

### 6.1 Interfaz de Carga

![Admin Upload](images/09_admin_upload.png)

**Descripción:**
Permite al administrador cargar múltiples documentos simultáneamente mediante un archivo ZIP.

**Flujo de carga:**

**Paso 1 - Seleccionar Archivo ZIP:**

- Zona de drag-and-drop
- Requisito: archivo ZIP conteniendo PDFs
- Los PDFs deben estar nombrados con el DNI del empleado

**Paso 2 - Resultado del Análisis:**

- Preview de documentos encontrados
- Identificación automática de empleados por DNI
- Alertas de documentos sin coincidencia

**Estructura esperada del ZIP:**

```
carga_documentos.zip
├── 12345678.pdf    → Empleado con DNI 12345678
├── 87654321.pdf    → Empleado con DNI 87654321
└── 55667788.pdf    → Empleado con DNI 55667788
```

---

## 7. Gestión de Usuarios (Admin)

### 7.1 Lista de Usuarios

![Admin Users](images/10_admin_users.png)

**Descripción:**
Permite visualizar, buscar y gestionar los usuarios de la organización.

**Filtros disponibles:**

| Filtro                  | Función                 |
| ----------------------- | ------------------------ |
| **Buscador**      | Por nombre, email o DNI  |
| **Organización** | Filtrar por tenant       |
| **Estado**        | Activo / Inactivo        |
| **Exportar**      | Descargar lista en Excel |

**Columnas de la tabla:**

| Columna              | Contenido                    |
| -------------------- | ---------------------------- |
| **Nombre**     | Nombre completo + email      |
| **Documento**  | DNI del empleado             |
| **Rol**        | Badge de rol (admin, client) |
| **Tenants**    | Organización asignada       |
| **Supervisor** | Supervisor directo           |
| **Estado**     | Badge de estado              |
| **Acciones**   | Ver perfil                   |

---

## 8. Buscador de Documentos

### 8.1 Lista de Documentos

![Admin Documents](images/11_admin_documents.png)

**Descripción:**
Permite al administrador buscar y gestionar todos los documentos de la organización.

**Filtros disponibles:**

| Filtro                      | Función            |
| --------------------------- | ------------------- |
| **Rango de fechas**   | Fecha de carga      |
| **Tipo de documento** | Categoría          |
| **Estado**            | Firmado / Pendiente |
| **Buscar usuario**    | Por nombre o DNI    |

**Columnas de la tabla:**

| Columna                   | Contenido               |
| ------------------------- | ----------------------- |
| **Apellido/Nombre** | Empleado asignado + DNI |
| **Tipo/Período**   | Categoría y mes/año   |
| **Fecha de subida** | Timestamp de carga      |
| **Estado**          | Badge de firma          |
| **Acciones**        | Ver, Eliminar           |

**Funcionalidades:**

- Exportar listado
- Limpiar filtros
- Paginación configurable

---

## 9. Registro de Auditoría

### 9.1 Log de Actividad

![Admin Audit](images/12_admin_audit.png)

**Descripción:**
Registro completo de todas las acciones realizadas en el sistema para cumplimiento y seguridad.

**Eventos rastreados:**

| Evento                                 | Descripción             |
| -------------------------------------- | ------------------------ |
| **Inició sesión**              | Login de usuario         |
| **Cerró sesión**               | Logout de usuario        |
| **Documento firmado**            | Firma completada         |
| **Documento cargado**            | Nueva carga de documento |
| **Usuario creado**               | Alta de empleado         |
| **Vacación solicitada**         | Nueva solicitud          |
| **Vacación aprobada/rechazada** | Decisión de supervisor  |

**Columnas del log:**

| Columna              | Contenido                        |
| -------------------- | -------------------------------- |
| **Usuario**    | Nombre + email                   |
| **Acción**    | Tipo de evento                   |
| **Detalle**    | Información adicional           |
| **IP**         | Dirección de origen             |
| **Fecha**      | Timestamp exacto                 |
| **Categoría** | Usuario / Documento / Vacaciones |

**Filtros:**

- Rango de fechas
- Categoría
- Tipo de acción
- Usuario específico

---

## 10. Gestión de Vacaciones del Equipo

### 10.1 Vacaciones del Equipo (Supervisor)

![Admin Team Vacations](images/13_admin_team_vacations.png)

**Descripción:**
Permite al supervisor gestionar las solicitudes de vacaciones de sus subordinados directos.

**Cards de métricas:**

| Métrica                | Descripción            |
| ----------------------- | ----------------------- |
| **Pendientes**    | Solicitudes por aprobar |
| **Por Confirmar** | En estado intermedio    |
| **Mi Historial**  | Solicitudes procesadas  |
| **Calendario**    | Eventos de vacaciones   |

**Pestañas:**

| Pestaña             | Contenido                         |
| -------------------- | --------------------------------- |
| **Pendientes** | Solicitudes que requieren acción |
| **Confirmar**  | Solicitudes pre-aprobadas         |
| **Historial**  | Solicitudes ya procesadas         |
| **Calendario** | Vista de calendario               |

**Acciones por solicitud:**

- **Aprobar:** Concede las vacaciones
- **Rechazar:** Deniega la solicitud (requiere motivo)
- **Ver detalle:** Información completa

---

## 11. Histórico General de Vacaciones

### 11.1 Histórico Completo

![Admin Vacation History](images/14_admin_vacation_history.png)

**Descripción:**
Vista centralizada de todas las solicitudes de vacaciones de la organización.

**Cards de métricas:**

| Métrica                    | Descripción           |
| --------------------------- | ---------------------- |
| **Total Solicitudes** | Todas las solicitudes  |
| **Aprobadas**         | Solicitudes concedidas |
| **Tomadas**           | Vacaciones ya gozadas  |

**Columnas de la tabla:**

| Columna                | Contenido                  |
| ---------------------- | -------------------------- |
| **Empleado**     | Nombre + email + iniciales |
| **Fechas**       | Rango de vacaciones        |
| **Días**        | Cantidad de días          |
| **Estado**       | Badge de estado            |
| **Tomada**       | Si ya fue gozada           |
| **Solicitado**   | Fecha de creación         |
| **Aprobado por** | Supervisor + fecha         |

**Funcionalidades:**

- Exportar a Excel
- Filtrar por fechas, estado, empleado
- Actualizar listado

---

# PARTE III: ROL CLIENT (Empleado)

---

## 12. Dashboard de Empleado (Rol: Client)

### 12.1 Vista Principal del Empleado

![Employee Dashboard](images/02_employee_dashboard.png)

**Descripción:**
El dashboard del empleado presenta un resumen de sus documentos pendientes y firmados, con acceso directo a visualización y firma.

**Secciones principales:**

| Sección                      | Contenido                                           |
| ----------------------------- | --------------------------------------------------- |
| **Sidebar**             | Navegación: Inicio, Mis Documentos, Mis Vacaciones |
| **Encabezado**          | Bienvenida personalizada con nombre del usuario     |
| **Cards de Métricas**  | Total documentos, Firmados, Pendientes              |
| **Filtros**             | Buscador, tipo de documento, estado                 |
| **Lista de Documentos** | Tarjetas con información de cada documento         |

**Información por documento:**

| Campo                          | Descripción                                |
| ------------------------------ | ------------------------------------------- |
| **Tipo**                 | Categoría (Contrato, Boleta, Liquidación) |
| **Período**             | Mes y año correspondiente                  |
| **Estado**               | Badge visual (Pendiente Firma / Firmado)    |
| **Fecha de asignación** | Cuándo fue cargado                         |
| **Fecha de firma**       | Cuándo fue firmado (si aplica)             |
| **Acciones**             | Ver documento, Firmar (si pendiente)        |

---

## 13. Visor de Documentos

### 13.1 Visualización de Documento PDF

![Document Viewer](images/03_document_viewer.png)

**Descripción:**
El visor de documentos permite al empleado revisar el contenido completo del documento en formato PDF antes de firmarlo.

**Componentes:**

| Componente                           | Función                                               |
| ------------------------------------ | ------------------------------------------------------ |
| **Breadcrumb**                 | Navegación: Inicio > Documento                        |
| **Área de visualización**    | Renderizado del PDF embebido                           |
| **Información del documento** | Tipo, período, fecha de carga                         |
| **Estado**                     | Indicador visual del estado de firma                   |
| **Botón "Firmar Documento"**  | Inicia el proceso de firma (visible solo si pendiente) |
| **Botón "Descargar"**         | Obtiene copia del documento                            |
| **Botón "Volver"**            | Regresa al dashboard                                   |

---

## 14. Proceso de Firma Digital

### 14.1 Modal de Verificación en Dos Pasos (Paso 1)

![Signature Modal Step 1](images/04_signature_modal_step1.png)

**Descripción:**
El primer paso del proceso de firma solicita al usuario confirmar que desea firmar y envía un código de verificación a su correo electrónico.

**Contenido del modal:**

| Elemento                          | Descripción                           |
| --------------------------------- | -------------------------------------- |
| **Título**                 | "Verificación en Dos Pasos"           |
| **Documento a firmar**      | Nombre y período del documento        |
| **Descripción**            | Explicación del proceso               |
| **Botón "Enviar Código"** | Dispara el envío del código al email |
| **Botón cancelar**         | Cierra el modal sin firmar             |

**Flujo:**

1. Usuario hace clic en "Firmar Documento"
2. Aparece modal de verificación
3. Usuario confirma enviando código
4. Sistema envía código de 6 dígitos al email registrado

### 14.2 Modal de Ingreso de Código (Paso 2)

![Signature Modal Step 2](images/05_signature_modal_step2.png)

**Descripción:**
El segundo paso solicita al usuario ingresar el código de 6 dígitos recibido por correo electrónico.

**Contenido del modal:**

| Elemento                              | Descripción                       |
| ------------------------------------- | ---------------------------------- |
| **Título**                     | "Ingresa el Código"               |
| **Campo de código**            | 6 inputs numéricos independientes |
| **Email parcial**               | Muestra email oculto parcialmente  |
| **Temporizador**                | Tiempo restante (5 minutos)        |
| **Botón "Nuevo Código"**      | Solicita reenvío del código      |
| **Botón "Verificar y Firmar"** | Completa el proceso de firma       |

**Flujo:**

1. Usuario recibe código por email
2. Ingresa los 6 dígitos en el formulario
3. Sistema valida el código
4. Si es válido: documento queda firmado con timestamp
5. Si es inválido: muestra error y permite reintentar

---

## 15. Gestión de Vacaciones (Empleado)

### 15.1 Lista de Mis Vacaciones

![Employee Vacations](images/06_employee_vacations.png)

**Descripción:**
Vista que permite al empleado consultar el estado de sus solicitudes de vacaciones y crear nuevas solicitudes.

**Componentes:**

| Componente                     | Función                               |
| ------------------------------ | -------------------------------------- |
| **Encabezado**           | Título y botón "Nueva Solicitud"     |
| **Cards de métricas**   | Total, Pendientes, Aprobadas           |
| **Filtro de estado**     | Dropdown para filtrar solicitudes      |
| **Lista de solicitudes** | Tarjetas con detalle de cada solicitud |

**Información por solicitud:**

| Campo                          | Descripción                           |
| ------------------------------ | -------------------------------------- |
| **Estado**               | Badge (Pendiente, Aprobada, Rechazada) |
| **Fechas**               | Rango de inicio a fin                  |
| **Duración**            | Días totales                          |
| **Fecha de solicitud**   | Cuándo fue creada                     |
| **Fecha de aprobación** | Cuándo fue procesada                  |
| **Motivo**               | Razón opcional                        |

### 15.2 Formulario de Nueva Solicitud

![Vacation Request Form](images/07_vacation_request_form.png)

**Descripción:**
Formulario para crear una nueva solicitud de vacaciones.

**Campos del formulario:**

| Campo                     | Tipo        | Requerido | Descripción                                  |
| ------------------------- | ----------- | --------- | --------------------------------------------- |
| **Rango de Fechas** | Date picker | Sí       | Fecha inicio y fin                            |
| **Motivo**          | Textarea    | No        | Razón de la solicitud (máx 1000 caracteres) |

**Panel informativo "¿Cómo funciona?":**

1. Envío de la solicitud al sistema
2. Notificación automática al supervisor
3. Revisión y decisión del supervisor
4. Notificación por email del resultado

---

# APÉNDICES

---

## Flujos de Proceso

### Flujo 1: Firma de Documento

```mermaid
flowchart TD
    A[Empleado accede al sistema] --> B[Ve lista de documentos]
    B --> C{Hay pendientes}
    C -- Si --> D[Abre documento para ver]
    C -- No --> E[Dashboard sin acciones]
    D --> F[Clic en Firmar]
    F --> G[Modal verificacion 2 pasos]
    G --> H[Envia codigo por email]
    H --> I[Ingresa codigo de 6 digitos]
    I --> J{Codigo valido}
    J -- Si --> K[Documento firmado]
    J -- No --> L[Error - Reintentar]
```

### Flujo 2: Solicitud de Vacaciones

```mermaid
flowchart TD
    A[Empleado crea solicitud] --> B[Selecciona fechas]
    B --> C[Envia solicitud]
    C --> D[Notificacion a supervisor]
    D --> E{Decision del supervisor}
    E -- Aprobar --> F[Vacaciones aprobadas]
    E -- Rechazar --> G[Solicitud rechazada]
    F --> H[Notificacion al empleado]
    G --> H
```

### Flujo 3: Carga Masiva de Documentos

```mermaid
flowchart TD
    A[Admin sube archivo ZIP] --> B[Sistema analiza contenido]
    B --> C{PDFs validos}
    C -- Si --> D[Identifica empleados por DNI]
    C -- No --> E[Error de formato]
    D --> F[Preview de asignacion]
    F --> G[Confirmar carga]
    G --> H[Documentos asignados]
    H --> I[Notificacion a empleados]
```

### Flujo 4: Onboarding de Nueva Empresa

```mermaid
flowchart TD
    A[Root crea nueva empresa] --> B[Completa datos de organizacion]
    B --> C[Empresa activa]
    C --> D[Root crea usuarios]
    D --> E[Asigna organizacion y supervisor]
    E --> F[Sistema envia email de bienvenida]
    F --> G[Usuario establece contrasena]
    G --> H[Usuario accede al sistema]
```

---

## Notificaciones por Email

El sistema envía notificaciones automáticas en los siguientes eventos:

| Evento                     | Destinatario | Contenido                          |
| -------------------------- | ------------ | ---------------------------------- |
| Nuevo documento            | Empleado     | Enlace para ver y firmar           |
| Código de firma           | Empleado     | Código 6 dígitos (válido 5 min) |
| Nueva solicitud vacaciones | Supervisor   | Enlace para aprobar/rechazar       |
| Vacaciones aprobadas       | Empleado     | Confirmación con fechas           |
| Vacaciones rechazadas      | Empleado     | Razón del rechazo                 |

---

## Diseño Responsivo

El sistema está optimizado para diferentes dispositivos:

| Dispositivo       | Resolución | Características                          |
| ----------------- | ----------- | ----------------------------------------- |
| **Desktop** | ≥1024px    | Sidebar expandido, tablas completas       |
| **Tablet**  | 768-1023px  | Sidebar colapsable                        |
| **Mobile**  | <768px      | Navegación hamburguesa, cards verticales |

---

## Seguridad

### Medidas implementadas:

1. **Autenticación JWT** con tokens de corta duración
2. **Verificación en dos pasos** para firma de documentos
3. **HTTPS obligatorio** en producción
4. **Registro de auditoría** completo
5. **Control de acceso basado en roles (RBAC)**
6. **Validación de tenant** para aislamiento de datos

---

## Soporte

Para soporte técnico o consultas sobre el sistema:

- **Email:** lgranda@tisvel.com

*Documento generado automáticamente - MiBoleta v1.0.0*
*Última actualización: Enero 2026*
