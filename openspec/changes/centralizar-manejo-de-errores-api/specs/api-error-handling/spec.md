## ADDED Requirements

### Requirement: Todos los errores de una respuesta de validación son visibles
La interfaz SHALL mostrar todos los mensajes de error de una respuesta de validación, no solo el
primero, y SHALL construir el resumen a partir de los errores por campo, sin reutilizar el texto de
resumen del framework (que añade el literal "(and N more errors)").

#### Scenario: Guardado con dos errores de validación
- **WHEN** el usuario guarda un formulario y el backend responde 422 con errores en dos campos
- **THEN** ve los dos mensajes y no ve el literal "(and 1 more error)"

#### Scenario: Un único error
- **WHEN** la respuesta 422 trae un solo error
- **THEN** el usuario ve ese mensaje tal cual, sin adornos ni recuentos

#### Scenario: Muchos errores a la vez
- **WHEN** la respuesta 422 trae más de cinco errores
- **THEN** el aviso muestra los primeros cinco e indica cuántos quedan, y el detalle completo sigue
  disponible en el formulario

### Requirement: Ningún error se pierde en silencio
Un mensaje de error SHALL ser visible aunque su campo no tenga ningún control en la interfaz: el
formulario SHALL separar los errores que sabe pintar de los que no, y SHALL mostrar estos últimos en
un resumen del formulario además del aviso.

#### Scenario: Error de un campo sin control visible
- **WHEN** la respuesta trae un error cuyo campo no corresponde a ningún control del formulario
- **THEN** el mensaje aparece en el aviso y en el resumen del formulario, en vez de descartarse

#### Scenario: Error de un campo con control
- **WHEN** la respuesta trae un error de un campo que sí tiene control
- **THEN** el mensaje se pinta junto a ese control y no se duplica en el resumen del formulario

### Requirement: Los errores por empresa se pintan en su control
Los errores anidados por empresa SHALL resolverse hasta el control correspondiente de esa empresa,
usando el mismo orden con el que se construyó el envío, de modo que el mensaje aparezca en la
empresa que lo provocó.

#### Scenario: Fecha de inicio laboral inválida en la segunda empresa
- **WHEN** el backend rechaza la fecha de inicio laboral de la segunda empresa asignada a un usuario
- **THEN** el mensaje se pinta bajo el campo de fecha de esa empresa, no en la primera ni fuera del
  bloque de empresas

#### Scenario: Errores en varias empresas
- **WHEN** el backend rechaza campos de dos empresas distintas en el mismo envío
- **THEN** cada mensaje aparece en el control de su empresa

### Requirement: Formato consistente en las respuestas de validación
Todas las respuestas de validación de la API SHALL incluir los errores por campo, sin descartar los
mensajes posteriores al primero, incluidas las de creación y edición de empresas.

#### Scenario: Alta de empresa con varios campos inválidos
- **WHEN** se crea una empresa con más de un campo inválido
- **THEN** la respuesta 422 incluye todos los campos que fallaron con su mensaje, y el formulario
  puede pintarlos junto a cada control

### Requirement: Normalización única de los errores del cliente HTTP
El cliente HTTP SHALL normalizar cualquier error de la API en un único tipo, que expone el estado,
la clasificación del error, el mensaje de resumen, la lista completa de mensajes y el mapa de
errores por campo; el resto de la aplicación SHALL consumir ese tipo en vez de interpretar la
respuesta cruda.

#### Scenario: Repositorio que propaga un error de la API
- **WHEN** cualquier repositorio propaga el fallo de una petición
- **THEN** quien lo recibe obtiene el error normalizado, con los errores por campo intactos

#### Scenario: Respuesta de error sin errores por campo
- **WHEN** la API responde con un error que no es de validación
- **THEN** el error normalizado conserva el mensaje del backend y su lista de mensajes no queda
  vacía

#### Scenario: Fallo de red sin respuesta
- **WHEN** la petición falla sin respuesta del servidor
- **THEN** el error normalizado lo refleja como fallo de red y la interfaz muestra un mensaje, no
  una pantalla en blanco
