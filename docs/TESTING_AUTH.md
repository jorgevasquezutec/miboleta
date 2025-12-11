# 🧪 Testing del Sistema de Autenticación

**Fecha:** 4 de diciembre de 2025
**Estado:** En Testing
**Módulo:** 1 - Autenticación y Autorización

---

## 📋 Índice

1. [Pre-requisitos](#pre-requisitos)
2. [Test 1: Login End-to-End](#test-1-login-end-to-end)
3. [Test 2: Verificar Cookies HttpOnly](#test-2-verificar-cookies-httponly)
4. [Test 3: Auto-refresh de Tokens](#test-3-auto-refresh-de-tokens)
5. [Test 4: Logout y Limpieza](#test-4-logout-y-limpieza)
6. [Test 5: TenantSwitcher](#test-5-tenantswitcher)
7. [Test 6: Usuario Multi-Tenant](#test-6-usuario-multi-tenant)
8. [Checklist Final](#checklist-final)

---

## Pre-requisitos

### Backend Corriendo

```bash
# Verificar que Laravel está corriendo en Docker
docker ps | grep miboleta

# Debería mostrar los contenedores corriendo
# Si no está corriendo:
cd backend
docker-compose up -d
```

### Frontend Configurado

```bash
# Verificar archivo .env.local
cat .env.local

# Debería contener:
# VITE_API_URL=http://localhost/api
# VITE_SHOW_TEST_USERS=true (para mostrar usuarios de prueba)
```

### Iniciar Frontend

```bash
# Desde la raíz del proyecto
npm run dev

# El frontend debería iniciar en http://localhost:5173
```

---

## Test 1: Login End-to-End

### Objetivo
Verificar que el login funciona correctamente con cookies HttpOnly.

### Pasos

1. **Abrir el navegador**
   - URL: `http://localhost:5173/login`

2. **Verificar usuarios de prueba visibles**
   - ✅ Deberías ver una sección azul con usuarios de prueba
   - Si no aparece, verifica `VITE_SHOW_TEST_USERS=true` en `.env.production.local`

3. **Probar login con usuario Root**
   - Email: `root@miboleta.com`
   - Password: `password`
   - Click en "Iniciar Sesión"

4. **Verificar redirección**
   - ✅ Deberías ser redirigido a `/admin` (dashboard de root)
   - ✅ No deberías ver errores en consola

5. **Verificar que no hay token en localStorage**
   - Abrir DevTools → Application → Local Storage
   - Buscar `auth-storage`
   - ✅ **NO debería haber campo `token`** (se usa cookies HttpOnly)
   - ✅ Debería haber `user` con datos del usuario

### ✅ Criterios de Éxito
- [ ] Login exitoso sin errores
- [ ] Redirección correcta según rol
- [ ] No hay token en localStorage
- [ ] User data presente en auth-storage

---

## Test 2: Verificar Cookies HttpOnly

### Objetivo
Confirmar que las cookies HttpOnly están configuradas correctamente.

### Pasos

1. **Abrir DevTools → Application → Cookies**
   - Seleccionar dominio: `http://localhost`

2. **Verificar cookie `access_token`**
   - ✅ Existe: `access_token`
   - ✅ HttpOnly: `✓` (marcado)
   - ✅ Secure: `✓` (en producción) o vacío (en desarrollo local HTTP)
   - ✅ SameSite: `Lax`
   - ✅ Path: `/`
   - ✅ Expira en: ~1 hora

3. **Verificar cookie `refresh_token`**
   - ✅ Existe: `refresh_token`
   - ✅ HttpOnly: `✓` (marcado)
   - ✅ Secure: `✓` (en producción) o vacío (en desarrollo)
   - ✅ SameSite: `Strict`
   - ✅ Path: `/`
   - ✅ Expira en: ~30 días

4. **Intentar acceder a las cookies desde JavaScript**
   - Abrir DevTools → Console
   - Ejecutar: `document.cookie`
   - ✅ **NO deberías ver `access_token` ni `refresh_token`** (protección HttpOnly)

### ✅ Criterios de Éxito
- [ ] Ambas cookies existen
- [ ] HttpOnly marcado en ambas
- [ ] SameSite configurado correctamente
- [ ] Cookies NO accesibles desde JavaScript

---

## Test 3: Auto-refresh de Tokens

### Objetivo
Verificar que el sistema renueva automáticamente el access token cuando expira.

### ✅ Implementación Actual

El auto-refresh **YA ESTÁ IMPLEMENTADO** en [apiClient.ts:93-97](../src/infrastructure/http/apiClient.ts#L93-L97):

```typescript
// Interceptor detecta 401 y llama automáticamente a /api/refresh
await axios.post(
  `${API_BASE_URL}/refresh`,
  {},
  { withCredentials: true } // Envía refresh_token cookie
);
```

**Características:**
- ✅ Detecta 401 automáticamente
- ✅ Llama a `/api/refresh` sin intervención del usuario
- ✅ Cola de requests (evita múltiples refreshes)
- ✅ Reintenta request original después del refresh
- ✅ Redirect a login si refresh falla

### Pasos

**NOTA:** Este test requiere esperar 1 hora o modificar temporalmente la duración del token en el backend.

### Opción A: Modificar duración del token (Recomendado para testing)

1. **Editar backend para token de corta duración**
   ```bash
   # En el backend
   # Archivo: app/Http/Controllers/AuthController.php
   # Cambiar línea de creación de token:

   # ANTES:
   $accessToken = $user->createToken('access_token', ['*'], now()->addHour());

   # DESPUÉS (para testing):
   $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(2));
   ```

2. **Reiniciar backend**
   ```bash
   docker-compose restart app
   ```

3. **Hacer login nuevamente**
   - Email: `root@miboleta.com`
   - Password: `password`

4. **Esperar 2 minutos**
   - Mantén la pestaña abierta
   - Mantén DevTools → Network abierto

5. **Hacer una acción que requiera autenticación**
   - Navegar a `/users` o `/tenants`
   - Deberías ver en Network:
     1. Request que falla con 401
     2. Request automático a `/api/refresh`
     3. Retry del request original con éxito

6. **Verificar en DevTools → Application → Cookies**
   - ✅ `access_token` renovado (nuevo valor y fecha de expiración)
   - ✅ `refresh_token` sin cambios

### Opción B: Esperar 1 hora (Token real)

Si prefieres probar con la configuración real:
1. Hacer login
2. Dejar el navegador abierto por 1 hora
3. Intentar navegar después de 1 hora
4. Verificar auto-refresh

### ✅ Criterios de Éxito
- [ ] Request original falla con 401
- [ ] Sistema llama automáticamente a `/api/refresh`
- [ ] Access token renovado
- [ ] Request original se reintenta y tiene éxito
- [ ] Usuario no es redirigido al login

---

## Test 4: Logout y Limpieza

### Objetivo
Verificar que el logout limpia correctamente cookies y estado.

### Pasos

1. **Estando logueado, hacer logout**
   - Click en el menú de usuario (esquina superior derecha)
   - Click en "Cerrar Sesión"

2. **Verificar redirección**
   - ✅ Redirigido a `/login`

3. **Verificar cookies eliminadas**
   - DevTools → Application → Cookies
   - ✅ `access_token`: Eliminada
   - ✅ `refresh_token`: Eliminada

4. **Verificar localStorage limpio**
   - DevTools → Application → Local Storage
   - ✅ `auth-storage` debe tener `user: null`

5. **Intentar acceder a ruta protegida**
   - Navegar manualmente a `http://localhost:5173/admin`
   - ✅ Debería redirigir a `/login`

### ✅ Criterios de Éxito
- [ ] Logout exitoso
- [ ] Cookies eliminadas
- [ ] localStorage limpio
- [ ] Rutas protegidas inaccesibles

---

## Test 5: TenantSwitcher

### Objetivo
Crear y probar el componente TenantSwitcher para cambiar entre tenants.

### Estado
🔴 **PENDIENTE - COMPONENTE NO CREADO**

### Componente a Crear

**Archivo:** `src/presentation/components/layout/TenantSwitcher.tsx`

```tsx
import { useAuthStore } from "@/presentation/stores";
import { useState } from "react";

export function TenantSwitcher() {
  const { user, currentTenant, switchTenant } = useAuthStore();
  const [isOpen, setIsOpen] = useState(false);

  if (!user || !user.tenants || user.tenants.length <= 1) {
    return null; // No mostrar si solo tiene 1 tenant
  }

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100"
      >
        <span className="text-sm font-medium">
          {currentTenant?.name || "Seleccionar Empresa"}
        </span>
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute top-full mt-2 w-64 bg-white rounded-lg shadow-lg border">
          {user.tenants.map((tenant) => (
            <button
              key={tenant.id}
              onClick={() => {
                switchTenant(tenant.id);
                setIsOpen(false);
              }}
              className={`w-full px-4 py-3 text-left hover:bg-gray-50 ${
                currentTenant?.id === tenant.id ? "bg-blue-50 font-semibold" : ""
              }`}
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">{tenant.name}</p>
                  <p className="text-xs text-gray-500">{tenant.ruc}</p>
                </div>
                {tenant.is_primary && (
                  <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                    Principal
                  </span>
                )}
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
```

### Pasos para Integrar

1. **Actualizar authStore.ts**
   - Verificar que existe `switchTenant(tenantId)` method
   - Verificar que `currentTenant` está en el state

2. **Agregar TenantSwitcher al Navbar**
   ```tsx
   import { TenantSwitcher } from "./TenantSwitcher";

   // Dentro del Navbar, agregar:
   <TenantSwitcher />
   ```

3. **Probar con usuario multi-tenant**
   - Login con: `multi-tenant@example.com` (si existe en seeders)
   - Verificar que aparece dropdown
   - Cambiar de tenant
   - Verificar que `X-Tenant-ID` header se actualiza en requests

### ✅ Criterios de Éxito
- [ ] Componente TenantSwitcher creado
- [ ] Solo visible para usuarios multi-tenant
- [ ] Dropdown muestra todos los tenants
- [ ] Marca tenant principal
- [ ] Cambio de tenant actualiza estado
- [ ] Requests subsecuentes usan nuevo tenant

---

## Test 6: Usuario Multi-Tenant

### Objetivo
Verificar que un usuario puede pertenecer a múltiples tenants y cambiar entre ellos.

### Pre-requisito
Tener un usuario en la BD con múltiples tenants.

### Verificar en Base de Datos

```sql
-- Conectar a la BD
docker exec -it miboleta-db mysql -u root -p

USE miboleta;

-- Ver usuarios con múltiples tenants
SELECT
    u.name,
    u.email,
    t.name as tenant_name,
    ut.is_primary
FROM users u
JOIN user_tenants ut ON u.id = ut.user_id
JOIN tenants t ON ut.tenant_id = t.id
ORDER BY u.email, ut.is_primary DESC;
```

### Si no hay usuario multi-tenant, crear uno:

```sql
-- Encontrar IDs
SELECT id, email FROM users WHERE email = 'admin@corporacionabc.com';
SELECT id, name FROM tenants LIMIT 5;

-- Agregar usuario a segundo tenant
INSERT INTO user_tenants (user_id, tenant_id, is_primary, created_at, updated_at)
VALUES (
    2, -- ID del usuario
    2, -- ID del segundo tenant
    0, -- No es primary
    NOW(),
    NOW()
);
```

### Pasos de Testing

1. **Login con usuario multi-tenant**
   - Email: (usuario con múltiples tenants)
   - Password: `password`

2. **Verificar authStore**
   - DevTools → Console
   - Ejecutar: `localStorage.getItem('auth-storage')`
   - ✅ `tenants` debe ser un array con múltiples elementos

3. **Cambiar de tenant con TenantSwitcher**
   - Click en dropdown de tenants
   - Seleccionar otro tenant
   - ✅ UI debe actualizar

4. **Verificar headers en requests**
   - DevTools → Network
   - Hacer una acción (ej: navegar a `/users`)
   - Verificar request headers
   - ✅ Debe tener `X-Tenant-ID` con el ID del tenant seleccionado

5. **Verificar scope de datos**
   - En `/users`, deberías ver solo usuarios del tenant actual
   - Cambiar de tenant
   - Lista de usuarios debe cambiar según el tenant

### ✅ Criterios de Éxito
- [ ] Usuario puede tener múltiples tenants
- [ ] TenantSwitcher muestra todos los tenants
- [ ] Cambio de tenant actualiza `X-Tenant-ID` header
- [ ] Datos mostrados respetan el tenant actual
- [ ] Tenant principal marcado visualmente

---

## Checklist Final

### ✅ Autenticación Básica
- [ ] Login funciona correctamente
- [ ] Redirección según rol
- [ ] No hay tokens en localStorage
- [ ] Cookies HttpOnly configuradas

### ✅ Seguridad
- [ ] Cookies HttpOnly: ✓
- [ ] SameSite configurado
- [ ] Cookies no accesibles desde JS
- [ ] CSRF protection activo

### ✅ Auto-refresh
- [ ] Token expira después de 1 hora
- [ ] Sistema auto-renueva sin intervención
- [ ] Usuario no es deslogueado
- [ ] No hay interrupciones en la UX

### ✅ Logout
- [ ] Logout limpia cookies
- [ ] Logout limpia localStorage
- [ ] Rutas protegidas inaccesibles después de logout
- [ ] Redirección a login funciona

### ✅ Multi-Tenancy
- [ ] TenantSwitcher creado
- [ ] Cambio de tenant funciona
- [ ] Header X-Tenant-ID actualizado
- [ ] Datos filtrados por tenant

### ✅ Componentes Pendientes
- [ ] TenantSwitcher component
- [ ] Navbar actualizado con tenant actual
- [ ] Indicador visual de tenant principal

---

## 🐛 Troubleshooting

### Problema: Cookies no aparecen

**Causa:** CORS mal configurado

**Solución:**
```php
// backend/config/cors.php
'supports_credentials' => true,
'allowed_origins' => ['http://localhost:5173'],
```

```typescript
// frontend: apiClient.ts
const apiClient = axios.create({
  baseURL: 'http://localhost/api',
  withCredentials: true, // ← CRÍTICO
});
```

### Problema: 401 en todas las requests

**Causa:** Middleware EnsureCookieAccessToken no está activo

**Solución:**
```php
// backend/bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prependToGroup('api', [
        \App\Http\Middleware\EnsureCookieAccessToken::class,
    ]);
})
```

### Problema: Auto-refresh no funciona

**Causa:** Interceptor de Axios no configurado

**Verificar:**
```typescript
// apiClient.ts línea ~68
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

    if (status === 401 && !originalRequest._retry) {
      // ... lógica de refresh
    }
  }
);
```

### Problema: TenantSwitcher no aparece

**Causa:** Usuario solo tiene 1 tenant

**Verificar en DB:**
```sql
SELECT COUNT(*) FROM user_tenants WHERE user_id = ?;
-- Debe ser > 1 para que aparezca el switcher
```

---

## 📊 Próximos Pasos

Una vez completados todos los tests:

1. **Actualizar TODO.md**
   - Marcar Módulo 1 como ✅ COMPLETADO
   - Actualizar % de progreso del proyecto

2. **Continuar con Módulo 3: Gestión de Usuarios**
   - Implementar UserController backend
   - Conectar UsersStore con API real
   - CRUD completo de usuarios

3. **Documentar hallazgos**
   - Bugs encontrados
   - Mejoras sugeridas
   - Performance issues

---

**Última actualización:** 4 de diciembre de 2025
**Próxima revisión:** Al completar todos los tests
