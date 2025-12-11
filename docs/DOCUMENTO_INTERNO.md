# DOCUMENTO TÉCNICO INTERNO - PLATAFORMA

# MIBOLETA

## Desarrollo por Sprints - Historias de Usuario

**Proyecto:** Sistema de Gestión Documental Laboral
**Desarrollador:** Jorge Luis Vásquez
**Arquitectura:** Monolito Laravel + React SPA
**Duración Total:** 8 semanas (4 sprints)

## STACK TECNOLÓGICO RESUMIDO

### Backend: Laravel 11 + MySQL + Redis + Horizon + Reverb (WebSockets) +

### Reverb (WebSockets)

### Frontend: React 18 + TypeScript + Tailwind CSS

### Integración: Laravel Sanctum + Vite

### Servidor: Nginx + Supervisor

## SPRINT 1: FUNDACIÓN DEL SISTEMA

**Duración:** 2 semanas | **Esfuerzo:** 80 horas | **Costo:** S/. 2,

### ÉPICA: Establecer infraestructura base y autenticación

**HU-001: Como developer quiero configurar el entorno completo**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Configurar servidor local con stack completo Laravel + React

**Criterios de Aceptación:**

```
Servidor LAMP configurado y funcionando
Laravel 11 instalado con todas las dependencias
React 18 + TypeScript configurado con Vite
Laravel Sanctum configurado para SPA
Tailwind CSS configurado y funcionando
Redis y Laravel Horizon operativos
Laravel Reverb configurado para WebSockets
Echo.js configurado en frontend para notificaciones en tiempo real
Hot reload funcionando en desarrollo
Build de producción funcionando
```
**Definición de Terminado:**

```
Comando npm run dev funciona sin errores
Comando npm run build genera assets optimizados
```

```
Laravel responde en localhost
React app carga correctamente
Horizon dashboard accesible
WebSocket server funciona correctamente
```
**HU-001B: Como sistema quiero notificaciones en tiempo real**

**Prioridad:** Alta | **Estimación:** 12 horas
**Descripción:** Configurar sistema de notificaciones en tiempo real con WebSockets

**Criterios de Aceptación:**

```
Laravel Reverb instalado y configurado
Echo.js configurado en React para escuchar eventos
Broadcasting configurado para notificaciones de usuario
Notificaciones aparecen sin refrescar página
Contador de notificaciones en tiempo real
Sonido opcional para nuevas notificaciones
Compatibilidad con múltiples pestañas del navegador
Manejo de desconexión/reconexión automática
```
**Eventos WebSocket implementados:**

```
DocumentUploaded → Nueva documento disponible
DocumentSigned → Documento firmado (para admin)
ProcessingComplete → Carga ZIP terminada
ProcessingError → Error en procesamiento
NewEmployee → Empleado nuevo detectado
```
**Definición de Terminado:**

```
Notificaciones aparecen instantáneamente
No requiere refrescar página para ver cambios
Performance no se degrada con múltiples usuarios conectados
Sistema funciona con WebSocket server ejecutándose
```
**HU-002: Como administrador quiero autenticarme en el sistema**

**Prioridad:** Alta | **Estimación:** 20 horas
**Descripción:** Implementar sistema de login con React + Laravel Sanctum

**Criterios de Aceptación:**

```
Pantalla de login moderna y responsive
Validación de formulario en tiempo real
Autenticación con email y password
Manejo de errores claros y amigables
Persistencia de sesión en navegador
Redirección automática tras login exitoso
Función logout funcional
Protección de rutas privadas
```

**Definición de Terminado:**

```
Admin puede loguearse exitosamente
Sesión persiste al recargar navegador
Logout limpia sesión completamente
Rutas protegidas redirigen al login
```
**HU-003: Como sistema necesito una base de datos robusta**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Crear estructura de base de datos completa para el sistema

**Criterios de Aceptación:**

```
Migración tabla tenants (empresas)
Migración tabla users (admin/empleados)
Migración tabla employees (empleados detallados)
Migración tabla document_categories (categorías fijas)
Migración tabla documents
Migración tabla document_signatures
Migración tabla audit_logs
Relaciones Eloquent definidas
Índices optimizados para búsquedas
Seeders para datos iniciales
```
**Definición de Terminado:**

```
Todas las migraciones ejecutan sin errores
Relaciones entre modelos funcionan
Seeders crean datos de prueba
Performance de queries optimizada
```
**HU-003B: Como sistema quiero organizar archivos con estructura lógica**

**Prioridad:** Alta | **Estimación:** 12 horas
**Descripción:** Implementar estructura de carpetas organizada por
empresa/empleado/categoría/período

**Criterios de Aceptación:**

```
Estructura: /EMPRESA/DNI/CATEGORIA/MES-ANO/archivo.pdf
Categorías fijas: Boletas, CTS, Utilidades, Certificados, Contratos, Legajo
Creación automática de directorios al subir documentos
Nombres consistentes y sin caracteres especiales
Validación de estructura antes de guardar
Migración de archivos existentes si cambia estructura
Optimización para descarga masiva por categoría/período
```
**Estructura de Ejemplo:**


```
storage/documents/
├── EMPRESA_ABC/
│ ├── 12345678/
│ │ ├── BOLETAS_REMUNERACION/
│ │ │ ├── 10-2025/
│ │ │ │ └── 12345678.pdf
│ │ │ └── 11-2025/
│ │ │ └── 12345678.pdf
│ │ ├── CTS/
│ │ │ └── 05-2025/
│ │ │ └── 12345678.pdf
│ │ └── UTILIDADES/
│ │ └── 03-2025/
│ │ └── 12345678.pdf
│ └── 87654321/
│ └── BOLETAS_REMUNERACION/
│ └── 10-2025/
│ └── 87654321.pdf
```
**Ventajas de esta estructura:**

```
Facilita descarga masiva por categoría
Organización cronológica clara
Escalable para múltiples empresas
Búsquedas eficientes por filesystem
Backup selectivo por categoría/período
```
**Definición de Terminado:**

```
Estructura se crea automáticamente
Archivos se organizan correctamente
Performance de acceso optimizada
Compatible con backup/restore
```
**HU-004: Como administrador quiero gestionar empleados**

**Prioridad:** Alta | **Estimación:** 28 horas
**Descripción:** CRUD completo de empleados con interfaz React moderna y gestión
automática de carpetas

**Criterios de Aceptación:**

```
Lista de empleados con tabla paginada
Búsqueda por DNI, nombre y email
Modal para crear nuevo empleado
Validación DNI único por empresa
Modal para editar empleado existente
Confirmación para eliminar empleado
Estados activo/inactivo
Creación automática de carpeta personal al crear empleado
Detección automática de documentos huérfanos por DNI
```

```
Asociación automática de documentos existentes
Notificación al empleado si hay documentos asociados
Ordenamiento por columnas
Mensajes de éxito/error claros
Responsive en móvil
```
**Flujo de Creación de Empleado:**

```
1. Admin crea nuevo empleado con DNI
2. Sistema verifica si existen documentos huérfanos para ese DNI
3. Si hay documentos huérfanos, se asocian automáticamente
4. Se crea la estructura de carpetas personales
5. Se notifica al empleado de documentos disponibles (si los hay)
6. Se limpia tabla de documentos huérfanos
```
**Definición de Terminado:**

```
CRUD completo funciona sin errores
Validaciones frontend y backend
Búsqueda responde instantáneamente
Creación de carpetas es automática
Asociación de documentos huérfanos funciona perfectamente
Interfaz responsive y profesional
```
### DEMO SPRINT 1

**Entregables para demo:**

```
Sistema funcionando en servidor local
Login de administrador operativo
Gestión completa de empleados
Base de datos poblada con datos de prueba
Interfaz React moderna y responsive
```
## SPRINT 2: PROCESAMIENTO DE DOCUMENTOS

**Duración:** 2 semanas | **Esfuerzo:** 80 horas | **Costo:** S/. 2,

### ÉPICA: Implementar carga masiva, procesamiento asíncrono y notificaciones

**HU-005: Como administrador quiero subir archivos ZIP masivamente**

**Prioridad:** Alta | **Estimación:** 20 horas
**Descripción:** Interfaz para carga de ZIP con múltiples PDFs de empleados

**Criterios de Aceptación:**

```
Componente drag & drop para subir ZIP
Validación de formato y tamaño de archivo
Barra de progreso durante upload
Vista previa del contenido del ZIP
Selección de categoría de documento
```

```
Selección de período (mes/año)
Opción "Requiere firma digital" para la carga
Confirmación antes de procesar
Cancelación de upload si es necesario
```
**Definición de Terminado:**

```
Upload funciona con archivos hasta 500MB
Validaciones previenen archivos corruptos
Preview muestra lista de archivos detectados
Configuración de firma se guarda correctamente
```
**HU-006: Como sistema quiero procesar ZIP en background con Horizon**

**Prioridad:** Alta | **Estimación:** 24 horas
**Descripción:** Jobs asíncronos para extraer y distribuir documentos

**Criterios de Aceptación:**

```
Job ProcessZipFile extrae archivos del ZIP
Job ValidateEmployees verifica DNIs existentes
Job DistributeDocuments organiza por empleado
Manejo de errores robusto con reintentos
Log detallado de cada paso del proceso
Notificación de progreso en tiempo real
Rollback automático en caso de fallo crítico
Dashboard Horizon muestra estado de jobs
```
**Definición de Terminado:**

```
Procesamiento no bloquea la aplicación
Horizon dashboard muestra progreso
Errores se manejan graciosamente
Logs permiten debug efectivo
```
**HU-007: Como administrador quiero notificar masivamente a empleados**

**Prioridad:** Alta | **Estimación:** 20 horas
**Descripción:** Sistema de notificación masiva tras procesamiento exitoso

**Criterios de Aceptación:**

```
Notificación automática a TODOS los empleados con nuevos documentos
Email personalizado con nombre y tipo de documento
Enlace directo al documento específico
Template diferente si requiere firma digital
Envío masivo usando colas para no saturar servidor
Opción de notificar manualmente grupos específicos
Control de frecuencia (evitar spam)
Tracking de emails entregados/rebotados
```

**Definición de Terminado:**

```
Todos los empleados con documentos reciben notificación
Emails se entregan sin ser marcados como spam
Templates son profesionales y claros
Sistema maneja volúmenes altos eficientemente
```
**HU-008: Como administrador quiero ser notificado de errores de procesamiento**

**Prioridad:** Alta | **Estimación:** 12 horas
**Descripción:** Sistema de alertas para errores críticos durante procesamiento

**Criterios de Aceptación:**

```
Email inmediato al admin cuando hay errores críticos
Notificación en dashboard cuando hay empleados no encontrados
Alerta cuando archivos no pueden procesarse
Resumen de errores al final del procesamiento
Distinción entre errores críticos y advertencias
Sugerencias de resolución en notificaciones
Log de errores descargable
Reintento automático para errores temporales
```
**Definición de Terminado:**

```
Admin recibe alertas inmediatas de problemas
Errores se categorizan correctamente
Información suficiente para resolver problemas
No se pierden documentos por errores no detectados
```
**HU-008B: Como sistema quiero manejar documentos huérfanos inteligentemente**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Gestión automática de documentos cuando el empleado no existe y
asociación posterior

**Criterios de Aceptación:**

```
Cuando DNI no existe, crear registro de documento "huérfano" en tabla
Almacenar documento físico en carpeta temporal por DNI
Lista de documentos huérfanos visible para admin
Al crear nuevo empleado, detectar automáticamente documentos existentes
Asociación automática de documentos huérfanos al nuevo empleado
Notificación automática al empleado de documentos asociados
Limpieza de carpeta temporal tras asociación
Prevención de pérdida de documentos
```
**Flujo Técnico:**

```
1. ZIP contiene documento para DNI no registrado
2. Sistema crea entrada en tabla orphaned_documents
3. Archivo se guarda en storage/orphaned/{dni}/
```

```
4. Admin ve lista de documentos huérfanos en dashboard
5. Al crear empleado con ese DNI, sistema detecta documentos huérfanos
6. Documentos se mueven a carpeta del empleado y se asocian
7. Empleado recibe notificación de documentos disponibles
```
**Definición de Terminado:**

```
Ningún documento se pierde por empleado inexistente
Asociación automática funciona sin intervención manual
Performance no se degrada con muchos documentos huérfanos
Flujo es transparente para el empleado final
```
**HU-009: Como sistema quiero solicitar firma digital automáticamente**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Marcado automático de documentos que requieren firma

**Criterios de Aceptación:**

```
Documentos marcados como "Requiere firma" durante carga
Estado "Pendiente de firma" visible para empleados
Email especial para documentos que requieren firma
Dashboard admin muestra estadísticas de firmas pendientes
Notificación al admin cuando empleado firma
```
**Definición de Terminado:**

```
Sistema identifica automáticamente documentos para firmar
Empleados reciben notificaciones claras sobre firma requerida
Admin puede monitorear estado de firmas pendientes
```
**HU-010: Como administrador quiero reportes completos de procesamiento**

**Prioridad:** Media | **Estimación:** 8 horas
**Descripción:** Dashboard con estadísticas detalladas del procesamiento incluyendo
documentos huérfanos

**Criterios de Aceptación:**

```
Resumen de archivos procesados vs errores
Lista de empleados no encontrados (DNI nuevo)
Lista de documentos huérfanos creados
Dashboard de documentos huérfanos pendientes de asociación
Lista de documentos que requieren firma
Estadísticas de notificaciones enviadas/fallidas
Métricas de asociaciones automáticas exitosas
Tiempo total de procesamiento
Botón para descargar reporte Excel completo
Filtros por fecha de carga
Métricas de adopción (empleados que accedieron)
```
**Definición de Terminado:**


```
Reportes incluyen información completa de documentos huérfanos
Dashboard muestra claramente documentos pendientes de asociación
Excel incluye todos los datos relevantes
Métricas ayudan a tomar decisiones operativas
Información es precisa y actualizada
```
### DEMO SPRINT 2

**Entregables para demo:**

```
Carga masiva ZIP con configuración de firma
Procesamiento asíncrono completo con Horizon
Sistema de notificación masiva funcionando
Alertas de errores operativas
Solicitud automática de firma digital
Reportes completos de procesamiento
```
## SPRINT 3: PORTAL EMPLEADO Y NOTIFICACIONES

**Duración:** 2 semanas | **Esfuerzo:** 80 horas | **Costo:** S/. 2,

### ÉPICA: Portal empleado y sistema de notificaciones

**HU-009: Como empleado quiero acceder a mis documentos**

**Prioridad:** Alta | **Estimación:** 24 horas
**Descripción:** Portal personalizado para cada empleado

**Criterios de Aceptación:**

```
Login empleado con DNI + password
Dashboard personal con resumen
Lista de documentos por categoría
Filtros por período y tipo
Indicadores de documentos nuevos
Búsqueda dentro de mis documentos
Responsive perfecto para móvil
Navegación intuitiva y rápida
```
**Definición de Terminado:**

```
Empleado accede solo a sus documentos
Interfaz optimizada para uso frecuente
Carga rápida en dispositivos móviles
Navegación clara e intuitiva
```
**HU-010: Como empleado quiero visualizar documentos PDF**

**Prioridad:** Alta | **Estimación:** 20 horas
**Descripción:** Visor de PDF integrado con funcionalidades básicas

**Criterios de Aceptación:**


```
Visor PDF embebido en la aplicación
Zoom in/out y ajuste a pantalla
Navegación entre páginas
Descarga del documento original
Funciona en móvil y desktop
Carga rápida para archivos grandes
Preview en lista sin abrir completo
Registro de visualización para auditoría
```
**Definición de Terminado:**

```
PDFs se visualizan sin plugins externos
Performance óptima en móvil
Todas las funciones básicas operativas
Auditoría registra accesos correctamente
```
**HU-011: Como sistema quiero notificar empleados por email**

**Prioridad:** Alta | **Estimación:** 24 horas
**Descripción:** Sistema automático de notificaciones por correo

**Criterios de Aceptación:**

```
Email automático cuando hay documento nuevo
Template profesional con branding
Enlace directo al documento específico
Envío masivo usando colas Horizon
Manejo de emails rebotados
Log de emails enviados/fallidos
Opción de reenviar notificación
Configuración SMTP flexible
```
**Definición de Terminado:**

```
Emails se envían automáticamente
Template se ve profesional en todos los clientes
Enlaces funcionan correctamente
Sistema maneja volumen alto de emails
```
**HU-012: Como empleado quiero gestionar mis notificaciones**

**Prioridad:** Media | **Estimación:** 12 horas
**Descripción:** Centro de notificaciones dentro de la aplicación

**Criterios de Aceptación:**

```
Centro de notificaciones en el header
Lista de notificaciones no leídas
Marcar como leído/no leído
Filtros por tipo de notificación
```

```
Configuración de preferencias
Badge con contador
Eliminar notificaciones antiguas
```
**Definición de Terminado:**

```
Centro funciona en tiempo real
Preferencias se guardan correctamente
Performance óptima con muchas notificaciones
Interfaz intuitiva y no intrusiva
```
**HU-012B: Como sistema quiero notificaciones en tiempo real con WebSockets**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Implementar Laravel Reverb para notificaciones push en tiempo real

**Criterios de Aceptación:**

```
Laravel Reverb configurado y funcionando
WebSocket connection desde React
Notificaciones push instantáneas sin recargar página
Eventos para: documento nuevo, documento firmado, procesamiento completado
Manejo de reconexión automática
Performance optimizada (solo usuarios conectados)
Fallback a polling si WebSocket falla
Dashboard admin recibe notificaciones en tiempo real
```
**Eventos WebSocket a implementar:**

```
DocumentProcessed → Notifica cuando termina procesamiento ZIP
NewDocumentAvailable → Notifica empleado de documento nuevo
DocumentSigned → Notifica admin cuando empleado firma
ProcessingError → Notifica admin de errores críticos
```
**Definición de Terminado:**

```
Notificaciones llegan instantáneamente sin recargar
Sistema funciona con múltiples usuarios simultáneos
Reconexión automática tras pérdida de conexión
Performance no se degrada con muchos usuarios conectados
```
### DEMO SPRINT 3

**Entregables para demo:**

```
Portal empleado completamente funcional
Visor PDF integrado operativo
Sistema de notificaciones automático
Centro de notificaciones interno
Experiencia móvil optimizada
```
## SPRINT 4: FIRMA DIGITAL Y FINALIZACIÓN


**Duración:** 2 semanas | **Esfuerzo:** 80 horas | **Costo:** S/. 2,

### ÉPICA: Sistema de firma digital y entrega final

**HU-013: Como empleado quiero firmar documentos digitalmente**

**Prioridad:** Alta | **Estimación:** 28 horas
**Descripción:** Sistema de firma electrónica con validez legal

**Criterios de Aceptación:**

```
Modal de términos y condiciones (primera vez)
Proceso de firma simple con confirmación
Registro de timestamp, IP y geolocalización
Estado visual de documentos firmados/pendientes
Prevención de doble firma
Firma masiva para múltiples documentos
Certificado de firma descargable
Cumplimiento normativas peruanas
```
**Definición de Terminado:**

```
Firma tiene validez legal básica
Proceso es intuitivo para empleados
Auditoría completa de cada firma
Sistema previene manipulaciones
```
**HU-014: Como administrador quiero auditoría completa**

**Prioridad:** Alta | **Estimación:** 20 horas
**Descripción:** Sistema de trazabilidad y auditoría

**Criterios de Aceptación:**

```
Log de todos los accesos al sistema
Registro de todas las firmas
Historial de cambios en documentos
Dashboard de métricas de uso
Seguridad de logs garantizada
```
**Definición de Terminado:**

```
Trazabilidad completa de todas las acciones
Performance óptima con histórico grande
Seguridad de logs garantizada
```
**HU-015: Como usuario quiero búsqueda avanzada**

**Prioridad:** Media | **Estimación:** 16 horas
**Descripción:** Sistema de búsqueda potente y rápido

**Criterios de Aceptación:**


```
Búsqueda por múltiples criterios
Filtros combinables (AND/OR)
Resultados paginados y ordenables
Exportación de resultados
```
**Definición de Terminado:**

```
Búsqueda responde en menos de 2 segundos
Resultados son precisos y relevantes
Interfaz intuitiva para usuarios finales
Performance escalable con volumen
```
**HU-016: Como developer quiero sistema optimizado para producción**

**Prioridad:** Alta | **Estimación:** 16 horas
**Descripción:** Optimizaciones finales y documentación

**Criterios de Aceptación:**

```
Performance optimizada (cache, queries)
Seguridad hardening completo
Monitoring y logs centralizados
Manual técnico completo
Manual de usuario final
Scripts de deployment
Testing de carga básico
```
**Definición de Terminado:**

```
Sistema soporta carga esperada sin degradación
Documentación permite mantenimiento autónomo
Seguridad cumple estándares básicos
```
### DEMO SPRINT 4 - ENTREGA FINAL

**Entregables para entrega:**

```
Sistema completo de firma digital
Auditoría y trazabilidad completa
Búsqueda avanzada funcional
Sistema optimizado para producción
Documentación técnica y usuario
Capacitación al equipo cliente
```
## CRITERIOS DE ACEPTACIÓN GENERALES

### Cada Sprint debe cumplir:

```
Todas las historias marcadas como completadas
Demo funcional sin errores críticos
Código en repositorio Git con commits descriptivos
```

```
Testing básico de funcionalidades principales
Performance aceptable en entorno objetivo
```
### Entrega final debe cumplir:

```
Sistema 100% funcional según especificaciones originales
Documentación técnica completa
Manual de usuario ilustrado
Capacitación básica realizada
Código fuente entregado al cliente
```
**Total estimado:** 320 horas | **Inversión:** S/. 10,


