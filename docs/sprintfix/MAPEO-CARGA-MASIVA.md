# Mapeo de carga masiva de usuarios: plantilla del cliente → formato interno

Este documento describe la capa de alias/normalización de encabezados
(`backend/app/Support/BulkUserColumns.php` + `UsersImport::normalizeRow()`)
que permite aceptar, en un mismo importador, tres formatos de encabezado:

1. Los **títulos legibles** de la plantilla que genera el sistema
   ("Número de documento", "Rol en empresa 1").
2. Nuestro **formato canónico** en snake_case (`numero_documento`, `org1_rol`),
   que es el que se usa internamente.
3. Los **encabezados de la plantilla real del cliente** (APEPAT, TIPDOC,
   DOCIDEN, ORGANIZACIÓN...).

La normalización corre **antes** de cualquier validación, fila por fila. No
se elimina el soporte del formato canónico: si una clave canónica ya viene
presente y con valor en la fila, el alias correspondiente NO la sobrescribe.

## Normalización de encabezados

`BulkUserColumns::canonicalKey()` normaliza el encabezado (`Str::ascii` +
minúsculas + `[^a-z0-9]+` → `_`, el mismo criterio que el heading row
formatter 'slug' de Laravel Excel) y luego lo busca en el mapa de alias, que
se construye a partir de los propios títulos de la plantilla. Eso mantiene la
plantilla y el importador sincronizados: cambiar un título en
`BulkUserColumns` cambia a la vez lo que se escribe en el Excel y lo que el
importador sabe leer.

## El rol es por empresa

No existe una columna de rol a nivel de fila. El rol vive siempre en la
empresa (`org{n}_rol` → `user_tenant_roles`) y es **obligatorio** para cada
empresa que la fila declare; toda fila necesita al menos una empresa. El único
rol global es `root`, que **no se da de alta por carga masiva**: solo un root
puede crear otro root (jerarquía que esta vía no puede verificar por fila), así
que se crea a mano desde el alta individual de usuarios.

## Mapeo columna cliente → campo interno

| Columna cliente (encabezado Excel) | Clave slugificada        | Campo interno canónico              | Notas                                                                 |
|-------------------------------------|---------------------------|--------------------------------------|------------------------------------------------------------------------|
| APEPAT                               | `apepat`                  | `apellido` (junto con APEMAT)        | `apellido = trim("$apepat $apemat")`                                  |
| APEMAT                               | `apemat`                  | `apellido` (junto con APEPAT)        | Ídem                                                                    |
| NOMBRE                               | `nombre`                  | `nombre`                             | Ya es canónico, sin alias                                             |
| TIPDOC                               | `tipdoc`                  | `tipo_documento`                     | DNI→`dni`, CE→`ce`, PAS/PASAPORTE→`passport`, RUC→`ruc` (case-insensitive) |
| DOCIDEN                              | `dociden`                 | `numero_documento`                   |                                                                          |
| EMAIL                                | `email`                   | `email`                              | Ya es canónico, sin alias                                             |
| TELEFONO                             | `telefono`                | `telefono`                           | Ya es canónico, sin alias                                             |
| ROL                                  | `rol`                     | —                                    | **Se ignora.** El rol es por empresa: usar `org{n}_rol` ("Rol en empresa {n}") |
| ESTADO                               | `estado`                  | `estado`                             | ACTIVO/ACTIVA→`active`, INACTIVO/INACTIVA→`inactive` (case-insensitive) |
| ORGANIZACIÓN                         | `organizacion`             | `org1_ruc`                           | Ver "Resolución de ORGANIZACIÓN" abajo                                |
| DEPARTAMENTO                         | `departamento`             | `org1_departamento`                  | Solo si no viene ya `org{n}_departamento` explícito                   |
| CARGO                                | `cargo`                    | `org1_cargo`                         | Solo si no viene ya `org{n}_cargo` explícito                          |
| SALDO DE VACACIONES                  | `saldo_de_vacaciones`      | `org1_saldo_vacaciones`              |                                                                          |
| PERIODO DE VACACIONES                | `periodo_de_vacaciones`    | `org1_fecha_ingreso` (fallback)      | Ver "Interpretación de PERIODO DE VACACIONES" abajo                   |

Todos los alias solo aplican a la **primera organización** (`org1_*`): la
plantilla del cliente no tiene un concepto de "varias organizaciones por
fila" (a diferencia de nuestro formato canónico, que soporta `org1`..`org5`
para usuarios con múltiples empresas). Si una fila necesita más de una
organización, debe usarse el formato canónico completo
(`org2_ruc`, `org2_departamento`, etc.).

## Resolución de ORGANIZACIÓN

La columna `organizacion` puede traer el RUC o el nombre/razón social de la
empresa:

- Si el valor son **11 dígitos**, se asume que ya es un RUC y se usa tal cual
  como `org1_ruc`.
- Si es texto, se busca un `Tenant` cuyo `name` o `business_name` coincida
  (comparación case-insensitive, coincidencia exacta).
- Si no resuelve a ninguna empresa registrada, se agrega un **warning**
  explícito (`field: organizacion`) y la fila queda sin `org1_ruc`, por lo
  que la validación estándar la marcará según corresponda (ej. usuario
  `client`/`admin` sin organización).

## Interpretación de PERIODO DE VACACIONES

`user_tenants` (la tabla pivote empresa↔usuario) solo tiene una columna de
saldo inicial de vacaciones (`vacation_balance_initial`); no existe una
columna dedicada a "periodo" o "año de devengo". Por lo tanto:

- **NO se persiste tal cual** en ningún campo nuevo (evita introducir un
  campo sin consumidor real).
- **NO genera devengo adicional**: no duplica ni interactúa con el cálculo
  de devengo por aniversario que ya existe en el sistema de vacaciones (ver
  `hire_date` + lógica de vacaciones).
- Se usa únicamente como **fallback de `org1_fecha_ingreso`**: si la fila no
  trae una fecha de ingreso explícita (`org{n}_fecha_ingreso` /
  `FECHAING`, esta última hoy sin alias porque no está en el listado de
  columnas de la plantilla), se interpreta el primer año de 4 dígitos
  encontrado en `periodo_de_vacaciones` (soporta `"2024"`, `"2024-2025"`,
  `"Periodo 2024"`, etc.) y se usa el **1 de enero de ese año** como fecha de
  ingreso de referencia. Esto le da al sistema de devengo por aniversario una
  fecha con la que trabajar cuando la plantilla del cliente no trae una
  fecha de ingreso real.
- Si la fila ya trae `org1_fecha_ingreso` explícito, el periodo se ignora
  (no se sobreescribe una fecha real).
- Si no se puede interpretar un año plausible (2000-2100) del valor, se
  agrega un warning (`field: periodo_de_vacaciones`) y se ignora.

Esta es una interpretación práctica documentada como decisión de este
sprint-fix; si el negocio necesita conservar el periodo tal cual (por
ejemplo, para mostrarlo en el histórico de vacaciones), se requeriría una
columna nueva en `user_tenants` y no está en el alcance de este cambio.

## Nota sobre la plantilla de referencia

El archivo `docs/sprintfix/ESTRUCTURA DE CARGA MI BOLETA.xlsx` (hoja `Hoja1`,
14 columnas, sin tablas embebidas) tiene exactamente estos encabezados en la
fila 1, verificados directamente sobre el archivo:

`APEPAT, APEMAT, NOMBRE, TIPDOC, DOCIDEN, EMAIL, TELEFONO, ROL, ESTADO,
ORGANIZACIÓN, DEPARTAMENTO, CARGO, SALDO DE VACACIONES, PERIODO DE VACACIONES`

La tabla de mapeo de arriba cubre las 14 columnas. `normalizeHeaderKey()` +
`normalizeRow()` en `UsersImport.php` son el único lugar que hace falta tocar
para agregar o ajustar alias si la plantilla final del cliente cambia.

### Campos del cliente sin destino directo y cómo se resuelven

- **APEPAT + APEMAT**: nuestro modelo usa un único `apellido`; se concatenan.
  Si el negocio necesita apellidos paterno/materno separados, requeriría dos
  columnas nuevas en `users` (fuera del alcance de este sprint-fix).
- **DEPARTAMENTO / CARGO**: se agregaron como columnas `department` / `position`
  en el pivote `user_tenants` (migración `2026_07_04_000002`), soportadas
  end-to-end (importador, formulario, grid de edición, plantilla de descarga).
- **PERIODO DE VACACIONES**: ver sección arriba (fallback de fecha de ingreso).
- **ORGANIZACIÓN**: el valor `"NN : Nombre"` (código + nombre) que trae la
  plantilla en DEPARTAMENTO se guarda tal cual como texto de `department`; si
  se requiere separar código y nombre, es un ajuste menor en `normalizeRow()`.
