## ADDED Requirements

### Requirement: Solo el rol root cambia el correo de un usuario
El cambio del correo electrónico de un usuario SHALL estar restringido al rol **root**. Un
administrador (no root) NO SHALL poder cambiar el correo de un usuario, y el usuario tampoco SHALL
poder cambiar su propio correo desde su perfil (`PUT /api/profile`).

#### Scenario: Root cambia el correo de un usuario
- **WHEN** un usuario con rol root edita el correo de un usuario
- **THEN** el cambio se aplica correctamente

#### Scenario: Admin no-root intenta cambiar el correo
- **WHEN** un administrador que no es root intenta cambiar el correo de un usuario
- **THEN** el sistema rechaza el cambio de correo (no lo aplica)

#### Scenario: Usuario intenta cambiar su propio correo desde el perfil
- **WHEN** un usuario edita su perfil en `/profile` e intenta cambiar su correo
- **THEN** el correo no cambia (el campo no está habilitado en el perfil)

### Requirement: Aviso de cambio de correo
Cuando el rol root cambia el correo de un usuario, el sistema SHALL enviar la notificación
`EmailChangedNotificationMail` tanto al correo anterior como al nuevo.

#### Scenario: Notificación a ambos correos
- **WHEN** root cambia el correo de un usuario de `antiguo@correo.com` a `nuevo@correo.com`
- **THEN** se envía el aviso de cambio de correo a `antiguo@correo.com` y a `nuevo@correo.com`

#### Scenario: Actualización sin cambiar el correo
- **WHEN** se actualiza un usuario sin modificar su correo
- **THEN** no se envía ningún aviso de cambio de correo
