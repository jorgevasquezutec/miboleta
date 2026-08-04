# Acta de entrega de software

**Sistema:** MiBoleta — Sistema de Gestión Documental y Vacaciones
**Versión entregada:** `__VERSION__`
**Fecha de entrega:** `__FECHA__`
**Lugar:** ______________________________________________

---

## 1. Partes

| | Proveedor | Cliente |
| --- | --- | --- |
| Razón social | | |
| RUC | | |
| Representante | | |
| DNI | | |
| Cargo | | |

---

## 2. Objeto

El proveedor hace entrega al cliente del software **MiBoleta**, en la versión y
fecha indicadas, junto con su código fuente, su documentación y una copia
ejecutable de la aplicación.

---

## 3. Contenido entregado

Se entrega **un disco por cada parte**, ambos de contenido idéntico, con:

| Elemento | Descripción |
| --- | --- |
| `fuentes/miboleta-src-<versión>.zip` | Código fuente completo de la versión entregada |
| `fuentes/miboleta-historia.bundle` | Repositorio con todo su historial, para continuidad del desarrollo |
| `imagenes/*.tar` | Imágenes de la aplicación y sus dependencias, ejecutables sin conexión |
| `imagenes/DIGESTS.txt` | Huella criptográfica de cada imagen |
| `documentacion/MiBoleta-Manual-de-Usuario.pdf` | Manual de usuario |
| `documentacion/MiBoleta-Documentacion-Tecnica.pdf` | Documentación técnica |
| `levantar` / `verificar` / `detener` | Scripts de arranque, comprobación y parada |
| `LEEME.md` | Instrucciones de uso |
| `VERSION.txt` | Versión, fecha y commit exacto |
| `MANIFIESTO-SHA256.txt` | Huella de todos los archivos del disco |
| `evidencia/` | Constancia de la ejecución realizada en este acto |

---

## 4. Identificación técnica de lo entregado

| Concepto | Valor |
| --- | --- |
| Etiqueta de versión | `__VERSION__` |
| Commit | `__COMMIT__` |
| **SHA-256 del manifiesto** | `__HASH_MANIFIESTO__` |

El SHA-256 del manifiesto identifica de forma única el contenido completo del
disco. Cualquier modificación posterior de cualquier archivo altera este valor,
por lo que ambas partes pueden verificar en el futuro que el contenido es el
mismo que se entregó en esta fecha.

---

## 5. Comprobación realizada en este acto

En presencia de ambas partes se ejecutaron, sobre un equipo ajeno a la
infraestructura del proveedor:

1. `levantar` — arranque de la aplicación desde el disco entregado.
2. `verificar` — comprobación automática de funcionamiento.

Resultado obtenido: **______ de ______ comprobaciones correctas**.

Las comprobaciones incluyen que la aplicación responde, que su interfaz carga,
que un usuario puede autenticarse y que la base de datos contiene información.
El registro generado, con marca de fecha y hora, se adjunta a esta acta como
**Anexo A**.

---

## 6. Alcance funcional entregado

- Gestión de empresas y usuarios, con cinco roles y permisos diferenciados.
- Carga y distribución de documentos laborales, individual y masiva.
- Firma de documentos por el trabajador con verificación en dos pasos.
- Firma digital criptográfica de documentos en formato PAdES.
- Gestión de vacaciones: solicitud, aprobación, confirmación e histórico, con
  cálculo según el régimen laboral peruano.
- Registro de auditoría de las acciones del sistema.
- Notificaciones por correo y en la propia aplicación.

---

## 7. Exclusiones

Se deja constancia expresa de que **no** forman parte de esta entrega:

1. **Los datos de producción.** La copia entregada contiene únicamente datos
   ficticios de demostración. La plataforma en producción aloja información de
   varias empresas, y su entrega habría supuesto la cesión de datos personales
   de terceros, prohibida por la Ley N.º 29733 de Protección de Datos
   Personales.
2. **El certificado de firma digital de la plataforma** y su contraseña, que son
   propiedad del proveedor y de uso exclusivo del servicio en producción.
3. **Las credenciales de acceso** a la infraestructura de producción.
4. **Los servicios de terceros** de pago o suscripción que la aplicación pueda
   consumir en producción (autoridad de sellado de tiempo, servidor de correo).

---

## 8. Declaraciones

El **proveedor** declara que el software entregado corresponde a la versión
identificada en la cláusula 4, que su código fuente es completo y que la copia
entregada fue ejecutada y comprobada en este acto.

El **cliente** declara haber recibido el disco, haber presenciado la ejecución y
comprobación descritas en la cláusula 5, y haber verificado el contenido
entregado según la cláusula 3.

---

## 9. Firmas

| Proveedor | Cliente |
| --- | --- |
| <br><br>_______________________________ | <br><br>_______________________________ |
| Nombre: | Nombre: |
| DNI: | DNI: |
| Fecha: | Fecha: |

---

**Anexo A** — Registro de la ejecución realizada (`evidencia/ejecucion-*.log`)
