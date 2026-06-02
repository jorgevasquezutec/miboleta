## ADDED Requirements

### Requirement: Dashboard del empleado con totales reales
El dashboard del empleado SHALL mostrar los totales individuales reales del usuario —documentos
totales, firmados y pendientes— calculados sobre todos sus documentos, y NO derivados de la lista
paginada visible.

#### Scenario: Empleado con documentos en varias páginas
- **WHEN** un empleado con 30 documentos (firmados y pendientes) repartidos en varias páginas abre su dashboard
- **THEN** los contadores de "total", "firmados" y "pendientes" reflejan el conteo real de sus 30 documentos, sin importar la paginación

#### Scenario: Empleado sin documentos
- **WHEN** un empleado sin documentos abre su dashboard
- **THEN** los contadores muestran 0 sin errores

### Requirement: Dashboard del admin a nivel organización
El dashboard del administrador SHALL reflejar métricas a nivel de la organización (tenant) activa.
Un administrador SHALL ver únicamente los datos de su empresa; el perfil root SHALL poder ver el
consolidado o filtrar por empresa.

#### Scenario: Admin de una empresa
- **WHEN** un administrador de la empresa X abre su dashboard
- **THEN** las métricas (documentos, usuarios, vacaciones) corresponden solo a la empresa X

#### Scenario: Usuario sin tenant primario asignado
- **WHEN** un usuario no-root sin tenant primario consulta el dashboard
- **THEN** el sistema resuelve su tenant de forma segura (no devuelve datos de todas las empresas ni un resultado nulo que rompa el filtrado)

### Requirement: Sumatorias del dashboard con totales reales por defecto
El dashboard SHALL usar "Todo el tiempo" como período por defecto, de modo que al cargar las tarjetas de resumen (total, firmados, pendientes, activos, huérfanos) muestren los totales reales de la organización. Cuando se selecciona un rango de fechas, las tarjetas Y los gráficos SHALL filtrarse por ese rango de forma consistente.

#### Scenario: Carga inicial con datos históricos
- **WHEN** un administrador abre el dashboard y la organización solo tiene documentos históricos
- **THEN** el período por defecto es "Todo el tiempo" y las tarjetas muestran los totales reales (no cero)

#### Scenario: Selección de un rango de fechas
- **WHEN** el administrador selecciona un rango de fechas
- **THEN** las tarjetas de resumen y los gráficos reflejan únicamente los datos de ese rango

#### Scenario: Volver a "Todo el tiempo"
- **WHEN** el administrador pulsa "Todo el tiempo"
- **THEN** las tarjetas vuelven a mostrar los totales reales de toda la organización

### Requirement: Dashboard del empleado con selector de período y filtros combinados
El dashboard del empleado SHALL tener un selector de período con "Todo el tiempo" por defecto. El período SHALL combinarse (AND) con los filtros existentes —buscador, estado y tipo— sobre la lista de documentos. Las tarjetas de resumen SHALL reflejar período + buscador + tipo (no el estado), mostrando el desglose por estado.

#### Scenario: Período combinado con otros filtros en la lista
- **WHEN** el empleado selecciona un rango de fechas y además aplica buscador, estado y/o tipo
- **THEN** la lista de documentos muestra solo los documentos que cumplen todos los filtros a la vez

#### Scenario: Tarjetas no afectadas por el filtro de estado
- **WHEN** el empleado filtra la lista por estado (p. ej. "Firmados")
- **THEN** las tarjetas Total/Firmados/Pendientes siguen mostrando el desglose por estado dentro del período/buscador/tipo, sin anularse por el estado seleccionado

#### Scenario: Por defecto "Todo el tiempo"
- **WHEN** el empleado abre su dashboard sin elegir período
- **THEN** las tarjetas y la lista muestran todos sus documentos (sin filtro de fecha)
