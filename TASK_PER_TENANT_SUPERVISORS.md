# Plan de Implementación: Supervisores por Sede (Multi-Tenant)

## 1. Objetivo
Permitir que un usuario tenga un supervisor distinto para cada empresa (tenant) a la que pertenece. Actualmente, el supervisor es global, lo que impide flujos de aprobación correctos en escenarios multi-empresa.

## 2. Cambios en Base de Datos

### 2.1. Migración Pivot Table
Modificar la tabla pivote `tenant_user` que relaciona usuarios con empresas.

- **Nueva Columna**: Agregar `supervisor_id` (nullable, foreign key a `users`) en la tabla `tenant_user`.
- **Migración de Datos**: (Opcional) Script para mover el `immediate_supervisor_id` actual a la nueva columna para el tenant principal del usuario.
- **Limpieza**: Marcar `immediate_supervisor_id` en la tabla `users` como deprecated (o eliminarlo en una fase posterior).

```php
Schema::table('tenant_user', function (Blueprint $table) {
    $table->foreignId('supervisor_id')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
});
```

## 3. Backend (Laravel)

### 3.1. Modelos
- **`TenantUser` / Relación Pivot**: Actualizar la relación `tenants()` en el modelo `User` para incluir el campo pivote `supervisor_id` (`withPivot`).
- **Accesores**: Crear método helper `$user->getSupervisorForTenant($tenantId)`.

### 3.2. Requests y Validaciones
- **`StoreUserRequest` y `UpdateUserRequest`**:
    - Eliminar validación de `immediate_supervisor_id` global.
    - Agregar validación para estructura de tenants con supervisor:
      ```php
      'tenants' => 'array',
      'tenants.*.id' => 'exists:tenants,id',
      'tenants.*.supervisor_id' => [
          'nullable',
          'exists:users,id',
          // Custom rule: Supervisor debe pertenecer al mismo tenant y ser admin
      ]
      ```

### 3.3. Servicios
- **`UserService`**: Modificar la lógica de guardado (`sync` de tenants) para guardar también el `supervisor_id` en la tabla pivote.
- **`VacationService`**:
    - Al crear una solicitud de vacaciones (`createRequest`), identificar el `tenant_id` de la solicitud.
    - Buscar el supervisor específico para ese tenant usando `$user->getSupervisorForTenant($request->tenant_id)`.
    - Asignar ese supervisor como el aprobador.

## 4. Frontend (React)

### 4.1. Componentes de Selección
- **Refactorizar `UserFormPage`**:
    - Eliminar el selector de "Jefe Inmediato" global.
    - Modificar la sección de "Organizaciones" para permitir configuración detallada.
- **Nuevo Componente `TenantSupervisorConfig`**:
    - Para cada organización seleccionada, mostrar una fila o tarjeta.
    - Incluir un `SupervisorSelector` dentro de cada fila.
    - El `SupervisorSelector` debe filtrar usuarios que sean **Admins de ESA organización específica**.

### 4.2. Lógica de Formulario
- El estado del formulario cambiará de:
  ```typescript
  { tenant_ids: [1, 2], immediate_supervisor_id: 5 }
  ```
  a:
  ```typescript
  {
    tenants_config: [
      { tenant_id: 1, supervisor_id: 5, is_main: true },
      { tenant_id: 2, supervisor_id: 8, is_main: false }
    ]
  }
  ```

## 5. Pasos de Ejecución Sugeridos

1. **DB**: Crear y ejecutar migración para `tenant_user`.
2. **Backend**: Actualizar modelo `User` y `UserService` para soportar guardar el supervisor por tenant.
3. **Backend**: Actualizar `VacationService` para leer el supervisor correcto.
4. **Frontend**: Actualizar `UserFormPage` para la nueva UI de configuración por tenant.
