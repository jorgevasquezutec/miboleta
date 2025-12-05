# Sistema de Autenticación con HttpOnly Cookies

## 📋 Resumen

Sistema completo de autenticación con **access tokens** y **refresh tokens** usando **cookies HttpOnly** para máxima seguridad.

## 🔐 Características de Seguridad

### ✅ Implementado

1. **Cookies HttpOnly**: Los tokens no son accesibles desde JavaScript (protección contra XSS)
2. **Access Token** (corta duración: 1 hora):
   - Enviado en cookie `access_token`
   - Flag `HttpOnly = true`
   - Flag `SameSite = Lax`
   - Se renueva automáticamente con refresh token

3. **Refresh Token** (larga duración: 30 días):
   - Enviado en cookie `refresh_token`
   - Flag `HttpOnly = true`
   - Flag `SameSite = Strict`
   - Almacenado en BD para revocación
   - Incluye IP y User Agent para auditoría

4. **Renovación Automática**:
   - Interceptor en frontend detecta 401
   - Llama a `/api/refresh` automáticamente
   - Reintenta el request original
   - Si refresh falla, redirige a login

5. **CORS Configurado**:
   - `withCredentials: true` en Axios
   - `supports_credentials: true` en Laravel
   - Dominios permitidos en `sanctum.stateful`

## 🏗️ Arquitectura

### Backend (Laravel)

```
┌─────────────────────────────────────────────────────┐
│                   AuthController                    │
├─────────────────────────────────────────────────────┤
│ POST /api/login                                     │
│  ├─ Valida credenciales                            │
│  ├─ Crea access token (1h)                         │
│  ├─ Crea refresh token (30d) en BD                 │
│  └─ Retorna cookies HttpOnly                       │
│                                                     │
│ POST /api/refresh                                   │
│  ├─ Lee refresh_token de cookie                    │
│  ├─ Valida en BD (no expirado, no revocado)       │
│  ├─ Crea nuevo access token (1h)                   │
│  └─ Retorna nueva cookie access_token              │
│                                                     │
│ POST /api/logout                                    │
│  ├─ Revoca todos los tokens del usuario            │
│  └─ Limpia cookies (expires = -1)                  │
│                                                     │
│ GET /api/me                                         │
│  └─ Retorna usuario autenticado                    │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│              EnsureCookieAccessToken                │
│                   (Middleware)                      │
├─────────────────────────────────────────────────────┤
│ Lee cookie access_token                             │
│ Inyecta en header Authorization                     │
│ → Sanctum puede autenticar                          │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│                 RefreshToken Model                  │
├─────────────────────────────────────────────────────┤
│ user_id, token, expires_at                          │
│ ip_address, user_agent, last_used_at               │
│ is_revoked                                          │
├─────────────────────────────────────────────────────┤
│ generate(user, ip, userAgent)                       │
│ isValid()                                           │
│ revoke()                                            │
│ revokeAllForUser(userId)                            │
└─────────────────────────────────────────────────────┘
```

### Frontend (React + TypeScript)

```
┌─────────────────────────────────────────────────────┐
│                    apiClient.ts                     │
├─────────────────────────────────────────────────────┤
│ Axios instance con:                                 │
│  • withCredentials: true                            │
│  • Interceptor Request: agrega X-Tenant-ID          │
│  • Interceptor Response:                            │
│    - Detecta 401                                    │
│    - Llama a /api/refresh                           │
│    - Reintenta request original                     │
│    - Si falla, redirect a /login                    │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│                   authStore.ts                      │
├─────────────────────────────────────────────────────┤
│ State:                                              │
│  • user: User | null                                │
│  • currentTenant: TenantAssociation | null          │
│  • NO guarda token (está en cookie)                 │
│                                                     │
│ Actions:                                            │
│  • login(email, password)                           │
│  • logout()                                         │
│  • me()                                             │
│  • switchTenant(tenantId)                           │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│                 UserRepository.ts                   │
├─────────────────────────────────────────────────────┤
│ login(): Promise<{user}>  ← No retorna token        │
│ logout(): Promise<void>                             │
│ me(): Promise<User>                                 │
└─────────────────────────────────────────────────────┘
```

## 🔄 Flujo de Autenticación

### 1. Login

```
Frontend                  Backend                Database
   │                        │                       │
   ├─ POST /api/login ─────>│                       │
   │  {email, password}     │                       │
   │                        ├─ Valida credenciales  │
   │                        ├─ createToken('access')│
   │                        ├─ RefreshToken::gen───>│
   │                        │                       ├─ INSERT
   │                        │<──────────────────────┤
   │<─ Response ────────────┤                       │
   │  {user}                │                       │
   │  Set-Cookie:           │                       │
   │    access_token=...    │                       │
   │    refresh_token=...   │                       │
   │                        │                       │
   ├─ Guarda user en store  │                       │
   └─ Redirect /dashboard   │                       │
```

### 2. Request Autenticado

```
Frontend                  Backend
   │                        │
   ├─ GET /api/me ─────────>│
   │  Cookie: access_token  │
   │                        ├─ Middleware: lee cookie
   │                        ├─ Sanctum: valida token
   │                        ├─ Retorna user
   │<─ Response ────────────┤
   │  {user data}           │
```

### 3. Token Expirado → Auto-refresh

```
Frontend                  Backend                Database
   │                        │                       │
   ├─ GET /api/users ──────>│                       │
   │  Cookie: access_token  │                       │
   │  (expirado)            │                       │
   │<─ 401 Unauthorized ────┤                       │
   │                        │                       │
   ├─ Interceptor detecta   │                       │
   │                        │                       │
   ├─ POST /api/refresh ───>│                       │
   │  Cookie: refresh_token │                       │
   │                        ├─ Busca en DB ────────>│
   │                        │                       ├─ SELECT WHERE token
   │                        │<──────────────────────┤
   │                        ├─ Valida (no expired,  │
   │                        │   no revoked)         │
   │                        ├─ createToken('access')│
   │                        ├─ updateLastUsed()───->│
   │<─ Response ────────────┤                       │
   │  Set-Cookie:           │                       │
   │    access_token=...    │                       │
   │                        │                       │
   ├─ Reintenta GET /users─>│                       │
   │  Cookie: nuevo access  │                       │
   │<─ 200 OK ──────────────┤                       │
   │  {users data}          │                       │
```

### 4. Logout

```
Frontend                  Backend                Database
   │                        │                       │
   ├─ POST /api/logout ────>│                       │
   │  Cookie: access_token  │                       │
   │                        ├─ Delete access tokens │
   │                        ├─ Revoke refresh ─────>│
   │                        │                       ├─ UPDATE is_revoked=1
   │<─ Response ────────────┤                       │
   │  Set-Cookie:           │                       │
   │    access_token=       │                       │
   │      (expires=-1)      │                       │
   │    refresh_token=      │                       │
   │      (expires=-1)      │                       │
   │                        │                       │
   ├─ Limpia authStore      │                       │
   └─ Redirect /login       │                       │
```

## 📁 Archivos Modificados

### Backend

1. **database/migrations/2025_12_05_004330_create_refresh_tokens_table.php**
   - Nueva tabla para refresh tokens
   - Campos: user_id, token, expires_at, ip_address, user_agent, is_revoked

2. **app/Models/RefreshToken.php** (nuevo)
   - Model con métodos: generate(), isValid(), revoke(), cleanup()

3. **app/Models/User.php**
   - Agregada relación: `refreshTokens()`

4. **app/Http/Controllers/Api/AuthController.php**
   - `login()`: Crea ambos tokens, retorna cookies
   - `refresh()`: Nuevo endpoint para renovar access token
   - `logout()`: Revoca tokens y limpia cookies
   - `me()`: Sin cambios (usa Sanctum)

5. **app/Http/Middleware/EnsureCookieAccessToken.php** (nuevo)
   - Lee `access_token` de cookie
   - Inyecta en header `Authorization`

6. **bootstrap/app.php**
   - Registrado middleware `EnsureCookieAccessToken`
   - Agregado `/api/refresh` a excepciones CSRF

7. **routes/api.php**
   - Nueva ruta: `POST /api/refresh`

8. **config/sanctum.php**
   - Ya configurado con dominios stateful

9. **config/cors.php**
   - Ya configurado con `supports_credentials: true`

### Frontend

1. **src/infrastructure/http/apiClient.ts**
   - Agregado `withCredentials: true`
   - Removido manejo manual de token
   - Interceptor response con lógica de refresh automático
   - Cola de requests durante refresh

2. **src/presentation/stores/authStore.ts**
   - Removido campo `token` del state
   - Removido `token` de persist
   - Comentarios actualizados

3. **src/presentation/hooks/useAuth.ts**
   - Removido `token` de destructuring
   - `isAuthenticated = !!user` (no depende de token)

4. **src/infrastructure/persistence/repositories/UserRepository.ts**
   - `LoginResponse` sin campo `token`
   - Comentarios actualizados

## 🧪 Cómo Probar

### 1. Verificar Backend

```bash
# Ver contenedores
docker compose ps

# Ver logs del backend
docker compose logs -f app

# Probar login con curl
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"root@miboleta.com","password":"password"}' \
  -c cookies.txt \
  -v

# Ver cookies guardadas
cat cookies.txt

# Probar /me con cookie
curl -X GET http://localhost/api/me \
  -b cookies.txt \
  -v

# Probar refresh
curl -X POST http://localhost/api/refresh \
  -b cookies.txt \
  -c cookies-new.txt \
  -v
```

### 2. Probar Frontend

```bash
# Iniciar frontend
npm run dev

# Abrir navegador en http://localhost:5173
```

**Test cases:**

1. **Login normal**:
   - Usar `root@miboleta.com` / `password`
   - Verificar en DevTools → Application → Cookies
   - Debe aparecer `access_token` y `refresh_token` con `HttpOnly ✓`

2. **Auto-refresh**:
   - Dejar sesión activa
   - Esperar 1 hora (o modificar duración a 1 minuto para test)
   - Hacer cualquier request
   - Debe renovar automáticamente sin logout

3. **Multi-tenant**:
   - Login con `ana.torres@email.com` / `password`
   - Cambiar tenant con el TenantSwitcher
   - Verificar que funcione después del refresh

4. **Logout**:
   - Click en logout
   - Verificar en DevTools que cookies se borran
   - No debe poder hacer requests autenticados

## 🔧 Configuración

### Variables de Entorno

**Backend (.env)**:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1:5173
FRONTEND_URL=http://localhost:5173
SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
```

**Frontend (.env.local)**:
```env
VITE_API_URL=http://localhost/api
```

### Ajustar Duraciones (para testing)

**Backend: AuthController.php**

```php
// Access token: cambiar de 1 hora a 1 minuto
$accessToken = $user->createToken(
  'access-token', 
  ['*'], 
  Carbon::now()->addMinute()  // Era addHour()
)->plainTextToken;

// Refresh token: cambiar de 30 días a 1 hora
$refreshToken = RefreshToken::generate(/* ... */);
// En RefreshToken::generate():
'expires_at' => Carbon::now()->addHour(),  // Era addDays(30)
```

## 🎯 Ventajas del Sistema

1. **Seguridad XSS**: JavaScript malicioso no puede leer los tokens
2. **Seguridad CSRF**: `SameSite` cookies + validación CSRF
3. **Revocación**: Tokens en BD permiten invalidar sesiones
4. **Auditoría**: IP y User Agent en cada refresh token
5. **UX Transparente**: Usuario no nota renovaciones automáticas
6. **Escalabilidad**: Access tokens cortos reducen carga en BD
7. **Multi-device**: Cada device tiene su refresh token

## 📊 Mantenimiento

### Limpiar tokens expirados

```php
// Crear comando artisan (opcional)
php artisan make:command CleanupExpiredTokens

// En el comando:
RefreshToken::cleanupExpired();

// Programar en app/Console/Kernel.php
$schedule->command('tokens:cleanup')->daily();
```

### Revocar sesión de un usuario

```php
// Por ID de usuario
RefreshToken::revokeAllForUser($userId);
$user->tokens()->delete();
```

### Ver sesiones activas de un usuario

```php
$sessions = RefreshToken::where('user_id', $userId)
    ->where('is_revoked', false)
    ->where('expires_at', '>', now())
    ->get();

foreach ($sessions as $session) {
    echo "IP: {$session->ip_address}\n";
    echo "Device: {$session->user_agent}\n";
    echo "Last used: {$session->last_used_at}\n";
}
```

## 🐛 Troubleshooting

### "401 Unauthorized" en requests

1. Verificar que cookies existen en DevTools
2. Verificar `withCredentials: true` en apiClient
3. Verificar CORS `supports_credentials: true`
4. Verificar dominios en `sanctum.stateful`

### Refresh loop infinito

1. Verificar que `/api/refresh` no requiere autenticación
2. Verificar que refresh_token cookie no está expirada
3. Verificar que token existe en BD y no está revocado

### Cookies no se establecen

1. Verificar dominio: debe coincidir (localhost = localhost)
2. Verificar HTTPS en producción (secure flag)
3. Verificar `SameSite` compatible con tu setup

### CORS errors

1. Verificar `supports_credentials: true` en backend
2. Verificar `allowed_origins` incluye tu frontend URL
3. Verificar `withCredentials: true` en frontend
4. NO usar `*` en `allowed_origins` con credentials

## 🚀 Próximos Pasos

- ✅ Sistema base implementado
- ⏸️ Probar en desarrollo
- ⏸️ Ajustar duraciones para producción
- ⏸️ Implementar comando de limpieza
- ⏸️ Agregar UI para "Ver sesiones activas"
- ⏸️ Configurar HTTPS para producción
- ⏸️ Monitoreo de intentos fallidos
- ⏸️ Rate limiting en login/refresh
