## ADDED Requirements

### Requirement: Aislamiento del filtro de empresa entre sesiones
El filtro de empresa persistido en el navegador SHALL pertenecer a un único usuario: al iniciar
sesión el sistema SHALL limpiar y resembrar el filtro para el usuario que entra, el filtro
persistido SHALL quedar sellado con el id de su dueño y SHALL descartarse al rehidratar si ese id no
coincide con el usuario autenticado, y el filtro SHALL eliminarse también cuando la sesión termina
(logout, incluido el estado en memoria del store) o muere por un refresh de token fallido.

#### Scenario: Sesión previa de otro usuario sin logout en el mismo navegador
- **WHEN** en un navegador donde quedó un filtro de empresa de una sesión anterior de otro usuario (que terminó sin logout) inicia sesión un usuario distinto
- **THEN** el filtro heredado se descarta y las peticiones del nuevo usuario no llevan `X-Tenant-Ids` de empresas ajenas

#### Scenario: Logout dentro de la SPA
- **WHEN** un usuario cierra sesión y, sin recargar la página, otro usuario inicia sesión
- **THEN** el filtro del primer usuario no persiste ni en `localStorage` ni en el estado en memoria del store

#### Scenario: Sesión muerta por refresh 401 fallido
- **WHEN** la sesión de un usuario muere porque el refresh del token falla con 401
- **THEN** el filtro de empresa persistido se elimina junto con los datos de autenticación

#### Scenario: Filtro persistido sin coincidencia de dueño
- **WHEN** la aplicación rehidrata un filtro persistido cuyo dueño sellado no coincide con el usuario autenticado actual
- **THEN** el filtro se descarta y se usa el valor por defecto del usuario actual

### Requirement: El header de empresas solo lleva empresas del usuario autenticado
El cliente HTTP SHALL calcular `X-Tenant-Ids` como la intersección entre los ids del filtro
persistido y las empresas del usuario autenticado; si la intersección queda vacía, el header SHALL
omitirse. En las rutas de datos, el backend SHALL seguir respondiendo 403 cuando los ids solicitados
no pertenecen al usuario (comprobación de seguridad que se mantiene).

#### Scenario: Filtro residual con empresas ajenas
- **WHEN** el filtro persistido contiene únicamente empresas que no pertenecen al usuario autenticado
- **THEN** la petición se envía sin el header `X-Tenant-Ids` y el backend responde con los datos de las empresas del usuario

#### Scenario: Petición a ruta de datos con empresas ajenas
- **WHEN** una petición a una ruta de datos llega al backend con `X-Tenant-Ids` de empresas no asociadas al usuario
- **THEN** el backend responde 403 con el mensaje "Las empresas seleccionadas no están asociadas a tu cuenta"

### Requirement: Las rutas de cuenta no dependen del filtro de empresa
El middleware de filtro de empresa SHALL excluir las rutas de cuenta propia
(`password/force-change`, `password/change`, `me`, `logout`, `refresh`), de modo que un filtro
corrupto o heredado nunca impida a un usuario autenticado completar operaciones sobre su propia
cuenta.

#### Scenario: Cambio de contraseña obligatorio con filtro corrupto
- **WHEN** un usuario autenticado con un `X-Tenant-Ids` de empresas ajenas envía `POST /password/force-change`
- **THEN** la petición se procesa con normalidad (no recibe el 403 del filtro de empresa) y el usuario puede completar su ingreso

#### Scenario: Cierre de sesión con filtro corrupto
- **WHEN** un usuario autenticado con un `X-Tenant-Ids` de empresas ajenas invoca `logout` o `refresh`
- **THEN** la operación se completa sin ser bloqueada por el filtro de empresa

### Requirement: Frescura de las empresas del usuario tras un cambio de asignación
Todo cambio en las asociaciones usuario-empresa SHALL invalidar las cachés de empresas de los
usuarios afectados (`user:{id}:tenant_ids` y `user:{id}:active_tenant_ids`). Aplica al alta, edición
y borrado de usuario, a la asignación/retiro de empresa y a la carga masiva, de modo que el cambio
surta efecto en la siguiente petición sin esperar a que expire la caché.

#### Scenario: Asignación de una empresa con la caché caliente
- **WHEN** un administrador asigna una empresa a un usuario cuyas cachés de empresas ya estaban pobladas
- **THEN** la siguiente petición del usuario con `X-Tenant-Ids` de esa empresa responde 200, sin esperar a la expiración de la caché

#### Scenario: Retiro de una empresa
- **WHEN** un administrador retira una empresa a un usuario
- **THEN** la siguiente petición del usuario ya no incluye datos de esa empresa y un `X-Tenant-Ids` con ella recibe 403

#### Scenario: Usuario creado por carga masiva
- **WHEN** un usuario es creado o modificado mediante la carga masiva
- **THEN** sus cachés de empresas quedan invalidadas y sus peticiones reflejan las asociaciones importadas de inmediato
