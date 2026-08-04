# MiBoleta — copia entregada

Esta carpeta contiene la aplicación **MiBoleta** lista para ejecutarse en un
equipo propio, junto con su código fuente y su documentación.

---

## Qué necesita

| Requisito | Detalle |
| --- | --- |
| **Docker Desktop** | Windows 10/11, macOS o Linux. Descarga gratuita en <https://www.docker.com/products/docker-desktop> |
| **Memoria** | 8 GB de RAM (4 GB asignados a Docker como mínimo) |
| **Disco** | 20 GB libres |
| **Internet** | **No hace falta.** Las imágenes vienen en este disco |

---

## Cómo arrancarla

### Windows

1. Abra Docker Desktop y espere a que indique **Running**.
2. Haga doble clic en **`levantar.bat`**.
3. Cuando termine, abra <http://localhost:9090> en su navegador.

### macOS y Linux

```bash
./levantar.sh
```

La primera vez tarda varios minutos: carga las imágenes, crea la base de datos y
la puebla con datos de ejemplo. Las siguientes veces arranca en segundos.

---

## Con qué usuario entrar

Todos usan la contraseña **`password`**. Cada uno muestra una parte distinta del
sistema:

| Usuario | Rol | Qué se puede ver con él |
| --- | --- | --- |
| `admin@email.com` | Super Administrador | Alta de empresas, ajustes de plataforma, certificado de firma |
| `admin.clientes@miboleta.demo` | Admin Clientes | Administración completa de una empresa y carga masiva de personal |
| `admin@corporacionabc.com` | Admin Empleados | Carga de boletas, usuarios, reportes y auditoría |
| `aprobador@miboleta.demo` | Aprobador | Bandeja de aprobación de vacaciones, con el saldo de cada solicitante |
| `juan.perez@corporacionabc.com` | Empleado | Sus boletas, su firma y sus solicitudes de vacaciones |

---

## Otras direcciones

| Para qué | Dirección |
| --- | --- |
| La aplicación | <http://localhost:9090> |
| **Correos** enviados por el sistema | <http://localhost:9025> |
| Consultar la base de datos | <http://localhost:9091> |

> **Los correos no salen a internet.** Quedan retenidos en un buzón local que se
> consulta en la segunda dirección. Es donde aparece el **código de verificación
> para firmar documentos**: sin abrir ese buzón no se puede completar una firma
> en esta copia.

---

## Comprobar que funciona

```bash
./verificar.sh          # macOS y Linux
verificar.bat           # Windows
```

Ejecuta comprobaciones reales —que la aplicación responde, que el frontend
carga, que un usuario puede iniciar sesión y que la base tiene datos— y guarda
el resultado con fecha en la carpeta `evidencia/`.

**Ese archivo es parte del entregable**: deja constancia de que esta copia
funcionaba en la fecha de la entrega. Se adjunta al acta.

---

## Detenerla

```bash
./detener.sh            # conserva los datos
./detener.sh --borrar   # vuelve a los datos originales en el próximo arranque
```

---

## Qué hay en este disco

| Carpeta | Contenido |
| --- | --- |
| `fuentes/` | Código fuente completo, exportado de la versión entregada |
| `imagenes/` | Imágenes de Docker de esa misma versión |
| `documentacion/` | Manual de usuario y documentación técnica (PDF) |
| `evidencia/` | Constancias de ejecución generadas por `verificar.sh` |
| `MANIFIESTO-SHA256.txt` | Huella de cada archivo, para comprobar que nada se alteró |
| `VERSION.txt` | Versión, fecha y commit exacto de esta entrega |

---

## Sobre los datos

Los datos de esta copia son **ficticios**, creados para la demostración:
empresas, empleados, boletas y solicitudes de vacaciones de ejemplo.

**No contiene información de ninguna persona real.** La plataforma en producción
aloja datos de varias empresas, y entregarlos aquí habría expuesto información
personal de terceros.

---

## Comprobar que el disco no se alteró

```bash
shasum -a 256 -c MANIFIESTO-SHA256.txt    # macOS y Linux
certutil -hashfile <archivo> SHA256       # Windows, archivo por archivo
```

Si alguna línea no dice `OK`, ese archivo cambió respecto a lo entregado.

---

## Soporte

Ante cualquier duda o incidencia, contacte con el proveedor por los canales
acordados en el contrato.
