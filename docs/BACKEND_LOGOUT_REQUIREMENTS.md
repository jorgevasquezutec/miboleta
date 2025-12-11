# 🔐 Requisitos del Endpoint /logout en Backend

**Fecha:** 4 de diciembre de 2025
**Módulo:** Autenticación
**Endpoint:** `POST /api/logout`

---

## 🎯 Objetivo

El endpoint `/logout` debe:
1. Revocar los tokens en la base de datos
2. **Limpiar las cookies HttpOnly** del navegador
3. Retornar respuesta exitosa

---

## ✅ Implementación Requerida en Laravel

### AuthController.php

```php
public function logout(Request $request)
{
    try {
        $user = $request->user();

        if ($user) {
            // 1. Revocar TODOS los tokens del usuario
            $user->tokens()->delete();

            // 2. Revocar refresh tokens si existe tabla refresh_tokens
            // RefreshToken::where('user_id', $user->id)->delete();
        }

        // 3. CRÍTICO: Limpiar cookies HttpOnly
        // Esto DEBE hacerse devolviendo cookies con Max-Age=0

        return response()->json([
            'message' => 'Logged out successfully'
        ])->withCookie(cookie()->forget('access_token'))
          ->withCookie(cookie()->forget('refresh_token'));

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Logout failed',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

---

## 🔍 Verificación de Cookies

### Antes del Logout

En DevTools → Application → Cookies:
```
access_token: eyJ0eXAiOiJKV1QiLCJhbGc...
refresh_token: def502001a8b3c4d5e6f7g8h...
```

### Después del Logout

Las cookies deben ser **eliminadas completamente**:
```
(vacío - no deben aparecer access_token ni refresh_token)
```

---

## 🧪 Testing del Endpoint

### Opción 1: Testing con curl

```bash
# 1. Login primero
curl -c cookies.txt -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"root@miboleta.com","password":"password"}'

# 2. Verificar cookies guardadas
cat cookies.txt
# Deberías ver access_token y refresh_token

# 3. Hacer logout
curl -b cookies.txt -c cookies.txt -X POST http://localhost/api/logout

# 4. Verificar cookies después del logout
cat cookies.txt
# Las cookies deben tener Max-Age=0 o estar eliminadas
```

### Opción 2: Testing en el navegador

1. Hacer login en `http://localhost:5173/login`
2. Verificar cookies en DevTools → Application → Cookies
3. Hacer logout desde la aplicación
4. Volver a verificar cookies
5. ✅ **Las cookies deben desaparecer**

---

## ❌ Problemas Comunes

### Problema 1: Cookies no se eliminan

**Síntoma:** Después del logout, las cookies siguen apareciendo en DevTools.

**Causa:** El backend no está devolviendo cookies con `Max-Age=0`.

**Solución:**
```php
// INCORRECTO - No limpia cookies
return response()->json(['message' => 'Logged out']);

// CORRECTO - Limpia cookies
return response()->json(['message' => 'Logged out'])
    ->withCookie(cookie()->forget('access_token'))
    ->withCookie(cookie()->forget('refresh_token'));
```

### Problema 2: Cookies tienen dominios diferentes

**Síntoma:** Logout no limpia cookies porque el dominio no coincide.

**Causa:** Las cookies se crearon con un dominio y se intentan borrar con otro.

**Verificar:**
```php
// Al crear la cookie (login)
cookie('access_token', $token, 60, '/', null, false, true);
//                                    ↑     ↑      ↑      ↑
//                                  path  domain secure httponly

// Al borrar la cookie (logout) - DEBE usar los mismos parámetros
cookie()->forget('access_token'); // Automáticamente usa los mismos defaults
```

### Problema 3: SameSite bloquea la limpieza

**Síntoma:** En producción las cookies no se eliminan.

**Solución:** Asegurar que `config/session.php` tenga:
```php
'same_site' => env('SESSION_SAME_SITE', 'lax'),
'secure' => env('SESSION_SECURE_COOKIE', false), // true en producción
```

---

## 🔐 Seguridad Adicional

### Revocar Refresh Tokens

Si tienes tabla `refresh_tokens`:

```php
public function logout(Request $request)
{
    $user = $request->user();

    if ($user) {
        // Revocar access tokens
        $user->tokens()->delete();

        // Revocar refresh tokens
        RefreshToken::where('user_id', $user->id)->delete();

        // Log de auditoría
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    return response()->json(['message' => 'Logged out'])
        ->withCookie(cookie()->forget('access_token'))
        ->withCookie(cookie()->forget('refresh_token'));
}
```

### Logout de Todos los Dispositivos

Si quieres permitir "cerrar sesión en todos los dispositivos":

```php
public function logoutAll(Request $request)
{
    $user = $request->user();

    if ($user) {
        // Revocar TODOS los tokens del usuario
        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->delete();
    }

    return response()->json([
        'message' => 'Logged out from all devices'
    ])->withCookie(cookie()->forget('access_token'))
      ->withCookie(cookie()->forget('refresh_token'));
}
```

---

## 📋 Checklist de Implementación

### Backend (Laravel)

- [ ] Endpoint `POST /api/logout` existe
- [ ] Revoca access tokens (`$user->tokens()->delete()`)
- [ ] Revoca refresh tokens (si aplica)
- [ ] Devuelve cookies con `cookie()->forget('access_token')`
- [ ] Devuelve cookies con `cookie()->forget('refresh_token')`
- [ ] Log de auditoría (opcional)
- [ ] Maneja errores apropiadamente

### Frontend (React)

- [ ] authStore.logout() llama a `userRepository.logout()`
- [ ] authStore.logout() limpia estado (`user: null`)
- [ ] authStore.logout() limpia localStorage (`localStorage.removeItem('auth-storage')`)
- [ ] Redirige a `/login` después del logout
- [ ] UI muestra feedback apropiado

### Testing

- [ ] Cookies se eliminan en DevTools
- [ ] localStorage se limpia
- [ ] Tokens revocados en BD
- [ ] Usuario no puede acceder a rutas protegidas después del logout
- [ ] Intentar usar token revocado retorna 401

---

## 🐛 Debugging

### Ver cookies en Laravel

```php
// En AuthController
public function logout(Request $request)
{
    // Debug: Ver cookies antes de limpiar
    \Log::info('Cookies before logout:', [
        'access_token' => $request->cookie('access_token'),
        'refresh_token' => $request->cookie('refresh_token'),
    ]);

    // ... código de logout

    \Log::info('Logout completed for user: ' . $request->user()?->id);
}
```

### Ver respuesta de logout en frontend

```typescript
// En UserRepository.ts
async logout(): Promise<void> {
  try {
    const response = await apiClient.post('/logout');
    console.log('Logout response:', response);
    console.log('Response headers:', response.headers);
  } catch (error) {
    console.error('Logout error:', error);
  }
}
```

### Verificar que cookies se eliminan

```typescript
// En authStore después del logout
logout: async () => {
  // ... código de logout

  // Debug
  console.log('Cookies after logout:', document.cookie);
  // No deberías ver access_token ni refresh_token (están HttpOnly)

  console.log('LocalStorage after logout:', localStorage.getItem('auth-storage'));
  // Debe ser null
}
```

---

## ✅ Criterios de Éxito

El logout funciona correctamente si:

1. ✅ Las cookies `access_token` y `refresh_token` desaparecen de DevTools
2. ✅ El localStorage `auth-storage` se limpia o tiene `user: null`
3. ✅ El usuario es redirigido a `/login`
4. ✅ Intentar acceder a `/admin` redirige a `/login`
5. ✅ En la BD, los tokens del usuario están revocados
6. ✅ Intentar hacer requests con el token viejo retorna 401

---

**Última actualización:** 4 de diciembre de 2025
