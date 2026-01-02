# TASK: Documentación de la Plataforma MiBoleta

## Objetivo
Crear documentación completa de la plataforma MiBoleta para facilitar el onboarding de nuevos desarrolladores, el mantenimiento del sistema y la comprensión funcional por parte de stakeholders.

---

## Entregable 1: Documento Funcional

### Descripción
Manual de usuario con capturas de pantalla que explica todas las funcionalidades de la plataforma.

### Contenido Requerido

#### 1. Introducción
- [ ] Descripción general de MiBoleta
- [ ] Roles de usuario (Root, Admin, Client)
- [ ] Flujo general de uso

#### 2. Autenticación
- [ ] Pantalla de Login
- [ ] Recuperación de contraseña
- [ ] Cambio de contraseña forzado
- [ ] Selector de organización (multi-tenant)

#### 3. Dashboard
- [ ] Dashboard del Empleado (Client)
  - Vista de documentos
  - Estadísticas personales
  - Filtros y búsqueda
- [ ] Dashboard del Administrador (Admin/Root)
  - Métricas generales
  - Accesos rápidos

#### 4. Gestión de Documentos
- [ ] Listado de documentos
- [ ] Subida masiva (ZIP)
- [ ] Visor de PDF
- [ ] Firma de documentos
- [ ] Estados de documentos (pending, signed, orphan)

#### 5. Gestión de Vacaciones
- [ ] Solicitud de vacaciones (empleado)
- [ ] Lista de mis solicitudes
- [ ] Aprobación/Rechazo (supervisor)
- [ ] Calendario de equipo
- [ ] Historial de vacaciones

#### 6. Gestión de Usuarios
- [ ] Listado de usuarios
- [ ] Crear/Editar usuario
- [ ] Carga masiva de usuarios (Excel)
- [ ] Detalle de usuario
- [ ] Reset de contraseña

#### 7. Gestión de Organizaciones (Root)
- [ ] Listado de tenants
- [ ] Crear/Editar organización

#### 8. Notificaciones
- [ ] Centro de notificaciones
- [ ] Tipos de notificaciones
- [ ] Notificaciones en tiempo real

#### 9. Perfil de Usuario
- [ ] Editar perfil
- [ ] Cambiar contraseña

#### 10. Auditoría
- [ ] Logs de auditoría
- [ ] Filtros disponibles

### Formato de Entrega
- Documento PDF o Markdown
- Capturas de pantalla de cada funcionalidad
- Descripción paso a paso de cada proceso

---

## Entregable 2: Documento Técnico

### Descripción
Documentación técnica para desarrolladores y DevOps que incluye arquitectura, deployment y mantenimiento.

### Contenido Requerido

#### 1. Arquitectura del Sistema
- [ ] Diagrama de arquitectura general
  - Frontend (React + Vite)
  - Backend (Laravel API)
  - Base de datos (MySQL)
  - Cache/Queue (Redis)
  - WebSockets (Laravel Reverb)
- [ ] Estructura de carpetas del proyecto
- [ ] Tecnologías utilizadas y versiones

#### 2. Configuración de Desarrollo Local
- [ ] Requisitos previos (Node, Docker)
- [ ] Instalación paso a paso
- [ ] Variables de entorno (.env.local, backend/.env)
- [ ] Scripts disponibles (npm run dev, dev:mobile, etc.)
- [ ] Acceso a servicios locales (puertos)

#### 3. Sistema de Autenticación
- [ ] Laravel Sanctum con cookies HttpOnly
- [ ] Flujo de tokens (access/refresh)
- [ ] Multi-tenant

#### 4. CI/CD Pipeline
- [ ] GitHub Actions workflow
- [ ] Secrets necesarios
- [ ] Proceso de build de imagen Docker
- [ ] Trigger automático vs manual

#### 5. Deployment a Producción (Docker Swarm)
- [ ] Requisitos del servidor
- [ ] Configuración de VPN
- [ ] docker-stack.yml explicado
- [ ] Volúmenes y persistencia de datos
- [ ] Servicios (app, nginx, db, redis, horizon, reverb)

#### 6. Comandos de Mantenimiento en Swarm
```bash
# Ver servicios
docker stack services miboleta

# Ver logs de un servicio
docker service logs miboleta_app --tail 100
docker service logs miboleta_horizon --tail 100

# Entrar a un contenedor
docker exec -it --user www-data $(docker ps -qf "name=miboleta_app" | head -1) sh

# Ejecutar comandos artisan
docker exec -it --user www-data $(docker ps -qf "name=miboleta_app" | head -1) php artisan migrate
docker exec -it --user www-data $(docker ps -qf "name=miboleta_app" | head -1) php artisan tinker

# Ver estado de la base de datos
docker exec -it $(docker ps -qf "name=miboleta_db" | head -1) mysql -u root -p

# Reiniciar un servicio
docker service update --force miboleta_app

# Ver archivos en storage
docker exec -it $(docker ps -qf "name=miboleta_app" | head -1) ls -la /var/www/html/storage/app/documents/
```

#### 7. Gestión de Archivos/Documentos
- [ ] Estructura de storage (storage/app/documents)
- [ ] Cómo se guardan los PDFs
- [ ] Permisos de archivos (www-data)
- [ ] Volumen storage_data en Swarm

#### 8. Monitoreo y Troubleshooting
- [ ] Horizon Dashboard (/horizon)
- [ ] Logs de Laravel
- [ ] Errores comunes y soluciones
  - "Archivo no encontrado" → Permisos
  - "Network error" → CORS o VPN
  - MySQL no inicia → Volúmenes

#### 9. Backups
- [ ] Backup de base de datos
- [ ] Backup de archivos (storage)
- [ ] Restauración

#### 10. Seguridad
- [ ] Variables de entorno sensibles
- [ ] GitHub Secrets
- [ ] Acceso SSH via VPN
- [ ] Permisos de archivos

### Formato de Entrega
- Documento Markdown en /docs
- Diagramas en formato visual (Mermaid, PNG)
- Ejemplos de comandos copiables

---

## Archivos de Referencia Existentes

Los siguientes archivos en `/docs` contienen información útil:
- AUTH_SYSTEM.md - Sistema de autenticación
- DEPLOYMENT.md - Guía de deployment
- GITHUB-ACTIONS-DEPLOY.md - CI/CD
- ENV-FILES-GUIDE.md - Variables de entorno
- ARCHITECTURE_ANALYSIS.md - Análisis de arquitectura

---

## Notas Adicionales

- Las capturas de pantalla deben tomarse en modo desktop y móvil
- El documento funcional debe ser entendible por usuarios no técnicos
- El documento técnico debe permitir que un nuevo desarrollador levante el ambiente en menos de 1 hora
- Considerar usar herramientas como Notion, Confluence o simplemente archivos .md con imágenes

---

## Estado: PENDIENTE

Creado: 2026-01-02
Prioridad: Alta
