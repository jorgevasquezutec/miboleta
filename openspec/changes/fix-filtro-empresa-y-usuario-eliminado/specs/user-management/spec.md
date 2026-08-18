## ADDED Requirements

### Requirement: Mensaje claro ante colisión con un usuario eliminado
El sistema SHALL responder 422, al crear o editar un usuario, cuando el correo o el número de
documento colisionan con un usuario **eliminado** (soft delete), con un mensaje por campo que
indique que existe un usuario eliminado con ese dato, nombrando al usuario y su fecha de eliminación
e indicando a quién dirigirse. Las colisiones con usuarios activos SHALL conservar el mensaje actual.
El sistema SHALL NOT permitir restaurar al usuario eliminado ni reutilizar su correo o documento
(fuera de alcance por decisión del cliente).

#### Scenario: Crear un usuario con el correo de uno eliminado
- **WHEN** un administrador crea un usuario con un correo que pertenece a un usuario eliminado
- **THEN** recibe un 422 en el campo `email` que indica que ese correo pertenece a un usuario eliminado, con su nombre y la fecha de eliminación, y no el mensaje genérico "Este email ya está registrado"

#### Scenario: Crear un usuario con el DNI de uno eliminado
- **WHEN** un administrador crea un usuario con un número de documento que pertenece a un usuario eliminado
- **THEN** recibe un 422 en el campo `document_text` con el mismo detalle (usuario eliminado, nombre y fecha de eliminación)

#### Scenario: Editar un usuario hacia un correo de uno eliminado
- **WHEN** un administrador edita un usuario y le asigna el correo de un usuario eliminado
- **THEN** recibe un 422 en el campo `email` con el mensaje específico de usuario eliminado

#### Scenario: Colisión con un usuario activo
- **WHEN** un administrador crea o edita un usuario con el correo o documento de un usuario activo
- **THEN** recibe el mensaje actual de duplicado ("ya está registrado"), sin mención a usuarios eliminados

#### Scenario: El formulario muestra el motivo por campo
- **WHEN** el formulario de crear/editar usuario recibe el 422 de colisión con un usuario eliminado
- **THEN** el mensaje específico se muestra asociado al campo correspondiente (email o documento), no como un error genérico

### Requirement: El refetch de la lista de usuarios conserva los filtros
Tras una acción sobre la lista de usuarios (crear, editar, eliminar), la recarga de la lista SHALL
conservar la búsqueda, el filtro de estado y la página actuales.

#### Scenario: Eliminar un usuario desde una página filtrada
- **WHEN** un administrador elimina un usuario estando en la página 3 de una búsqueda filtrada
- **THEN** la lista se recarga manteniendo la misma búsqueda, el mismo filtro de estado y la misma página
