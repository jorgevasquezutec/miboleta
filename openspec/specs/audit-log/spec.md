# audit-log Specification

## Purpose
TBD - created by archiving change ajustes-sin-costo. Update Purpose after archive.
## Requirements
### Requirement: Auditoría del CRUD de usuarios
El sistema SHALL registrar en el log de auditoría la creación, edición y eliminación de usuarios
(incluidas las cuentas de administrador), identificando la cuenta que realizó el cambio.

#### Scenario: Creación de usuario
- **WHEN** un administrador o root crea un usuario
- **THEN** se registra un evento `user.created` con el id del usuario creado y el autor del cambio

#### Scenario: Edición de usuario
- **WHEN** se edita un usuario
- **THEN** se registra un evento `user.updated` con los valores anteriores y nuevos y el autor del cambio

#### Scenario: Eliminación de usuario
- **WHEN** se elimina un usuario
- **THEN** se registra un evento `user.deleted` con los datos del usuario eliminado y el autor del cambio

