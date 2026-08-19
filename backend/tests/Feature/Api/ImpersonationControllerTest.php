<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * "Iniciar sesión como" (impersonation). Cubre tanto el contrato HTTP
 * (ImpersonationController: autorización, forma de la respuesta, auditoría)
 * como las trampas de AuthService::impersonate()/leaveImpersonation()/
 * refreshAccessToken() a nivel de servicio, donde es más preciso verificar
 * qué fila concreta de personal_access_tokens/refresh_tokens sobrevive y cuál
 * no (justo lo que puede fallar en silencio).
 *
 * Se autentica con Bearer real (createToken()->plainTextToken) y no con
 * actingAs(): esta feature depende de currentAccessToken()->name, que solo
 * existe cuando la request se resolvió por un token Sanctum de verdad.
 */
class ImpersonationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function bearer(User $user): string
    {
        return $user->createToken('access_token', ['*'])->plainTextToken;
    }

    /**
     * @return array{0: User, 1: User, 2: Tenant} [root, empleado, empresa]
     */
    private function makeRootAndEmployee(): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $root = User::factory()->root()->create(['status' => 'active']);
        $employee = User::factory()->client()
            ->withTenantRole($tenant, 'client', true)
            ->create(['status' => 'active']);

        return [$root, $employee, $tenant];
    }

    // ============ HTTP: autorización y forma de la respuesta ============

    public function test_root_can_impersonate_another_user(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$employee->id}/impersonate");

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $employee->id)
            ->assertJsonPath('impersonator.id', $root->id)
            ->assertJsonPath('impersonator.email', $root->email)
            ->assertCookie('access_token')
            ->assertCookie('refresh_token')
            ->assertCookie('impersonator_return');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_IMPERSONATION_STARTED,
            'user_id' => $root->id,
            'entity_type' => 'User',
            'entity_id' => $employee->id,
        ]);

        $this->assertTrue(
            PersonalAccessToken::where('tokenable_id', $employee->id)
                ->where('name', 'impersonation:' . $root->id)
                ->exists()
        );
    }

    public function test_non_root_cannot_impersonate(): void
    {
        [, $employee, $tenant] = $this->makeRootAndEmployee();
        $admin = User::factory()->admin()
            ->withTenantRole($tenant, 'admin', true)
            ->create(['status' => 'active']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($admin))
            ->postJson("/api/users/{$employee->id}/impersonate");

        $response->assertStatus(403);
    }

    public function test_cannot_impersonate_self(): void
    {
        [$root] = $this->makeRootAndEmployee();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$root->id}/impersonate");

        $response->assertStatus(403);
    }

    public function test_cannot_impersonate_another_root(): void
    {
        [$root] = $this->makeRootAndEmployee();
        $otherRoot = User::factory()->root()->create(['status' => 'active']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$otherRoot->id}/impersonate");

        $response->assertStatus(403);
    }

    public function test_impersonate_missing_user_returns_404(): void
    {
        [$root] = $this->makeRootAndEmployee();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson('/api/users/999999/impersonate');

        $response->assertStatus(404);
    }

    // ============ Trampa #1: la sesión real del empleado sobrevive ============

    public function test_impersonating_does_not_revoke_employee_real_session(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $employeeOwnToken = $employee->createToken('access_token', ['*'])->plainTextToken;
        $employeeOwnTokenId = PersonalAccessToken::findToken($employeeOwnToken)->id;
        $originalLastLogin = $employee->last_login_at;

        app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        // El token real del empleado sigue vivo: impersonate() solo AGREGA un
        // token nuevo, nunca toca $target->tokens() (Trampa #1 del contrato).
        $this->assertNotNull(PersonalAccessToken::find($employeeOwnTokenId));
        // Tampoco se pisa last_login_at: root entrando no es "el empleado
        // inició sesión".
        $this->assertEquals($originalLastLogin, $employee->fresh()->last_login_at);
    }

    // ============ Trampa #3: el refresh propaga la marca ============

    public function test_refresh_preserves_impersonation_marker_and_does_not_touch_real_tokens(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $employeeOwnToken = $employee->createToken('access_token', ['*'])->plainTextToken;
        $employeeOwnTokenId = PersonalAccessToken::findToken($employeeOwnToken)->id;

        $result = app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        $refreshed = app(AuthService::class)->refreshAccessToken(
            $result['refresh_token']->token,
            $result['access_token']
        );

        $this->assertNotNull($refreshed);

        $newToken = PersonalAccessToken::findToken($refreshed['access_token']);
        $this->assertSame('impersonation:' . $root->id, $newToken->name);

        // El token de impersonación viejo se borró (uno solo, no todos los
        // del empleado)...
        $this->assertNull(PersonalAccessToken::findToken($result['access_token']));
        // ...y el real del empleado, que ni se tocó al entrar, sigue vivo.
        $this->assertNotNull(PersonalAccessToken::find($employeeOwnTokenId));
    }

    // ============ Trampa #2 y Decisión #4: salir revoca solo lo suyo ============

    public function test_leaving_impersonation_revokes_only_the_impersonation_tokens(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        // Sesiones reales previas, de root y del empleado: deben sobrevivir
        // intactas a la impersonación completa (entrar + salir).
        $employeeOwnAccessToken = $employee->createToken('access_token', ['*'])->plainTextToken;
        $employeeOwnAccessTokenId = PersonalAccessToken::findToken($employeeOwnAccessToken)->id;
        $employeeOwnRefreshToken = RefreshToken::generate($employee, '127.0.0.1', 'PHPUnit');

        $result = app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        $leaveResult = app(AuthService::class)->leaveImpersonation(
            $result['user'],
            $result['impersonator_return_token']->token,
            $result['refresh_token']->token,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertNotNull($leaveResult);
        $this->assertSame($root->id, $leaveResult['user']->id);

        // Se revocó SOLO el token de impersonación...
        $this->assertNull(PersonalAccessToken::findToken($result['access_token']));
        // ...y el refresh token que se creó para el empleado al entrar.
        $this->assertTrue(
            RefreshToken::where('token', $result['refresh_token']->token)->first()->is_revoked
        );
        // El "boleto de vuelta" también queda consumido (de un solo uso).
        $this->assertTrue(
            RefreshToken::where('token', $result['impersonator_return_token']->token)->first()->is_revoked
        );

        // Nada de lo que ya existía se tocó: ni el token real del empleado...
        $this->assertNotNull(PersonalAccessToken::find($employeeOwnAccessTokenId));
        // ...ni su refresh token real.
        $this->assertFalse($employeeOwnRefreshToken->fresh()->is_revoked);
    }

    public function test_leave_without_active_impersonation_returns_422(): void
    {
        [$root] = $this->makeRootAndEmployee();

        // root con SU PROPIO token (no marcado): no hay nada que cerrar.
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson('/api/impersonate/leave');

        $response->assertStatus(422);
    }

    // ============ Auditoría: user_id = empleado, impersonator_id = root ============

    public function test_action_during_impersonation_is_audited_with_impersonator_id(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $result = app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        // Cualquier endpoint auditado sirve para probar la captura central de
        // AuditService::log(); /logout ya está instrumentado y no requiere
        // payload adicional.
        $this->withHeader('Authorization', 'Bearer ' . $result['access_token'])
            ->postJson('/api/logout')
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_USER_LOGOUT,
            'user_id' => $employee->id,
            'impersonator_id' => $root->id,
        ]);
    }

    // ============ /me expone impersonator ============

    public function test_me_exposes_impersonator_during_impersonated_session(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $result = app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        $response = $this->withHeader('Authorization', 'Bearer ' . $result['access_token'])
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('id', $employee->id)
            ->assertJsonPath('impersonator.id', $root->id)
            ->assertJsonPath('impersonator.email', $root->email);
    }

    public function test_me_impersonator_is_null_for_normal_session(): void
    {
        [$root] = $this->makeRootAndEmployee();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('impersonator', null);
    }

    // ============ Flujo completo por HTTP: entrar y salir con cookies reales ============

    public function test_full_impersonate_then_leave_flow_via_http(): void
    {
        [$root, $employee] = $this->makeRootAndEmployee();

        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$employee->id}/impersonate");

        $startResponse->assertStatus(200);

        // Cookies tal como quedaron en la respuesta (decrypt=false: el valor
        // LITERAL del Set-Cookie, cifrado o no según corresponda a cada una),
        // para reenviarlas en la request de salida igual que haría un
        // navegador real.
        $cookies = [
            'access_token' => $startResponse->getCookie('access_token', false)->getValue(),
            'refresh_token' => $startResponse->getCookie('refresh_token', false)->getValue(),
            'impersonator_return' => $startResponse->getCookie('impersonator_return', false)->getValue(),
        ];

        // withoutHeader: la request de arriba dejó un Authorization: Bearer
        // <token de root> como header por defecto para el resto del test; sin
        // limpiarlo, esta request se autenticaría con ESE token (el de root)
        // en vez de con la cookie access_token del empleado impersonado, que
        // es justo lo que se quiere probar (el flujo real de un navegador,
        // que nunca manda ese header).
        //
        // forgetGuards(): el guard de Sanctum (RequestGuard) cachea el user
        // resuelto en $this->user tras la PRIMERA llamada a user() y no lo
        // vuelve a evaluar — normal en producción (vive un solo request),
        // pero en un test ambas llamadas HTTP comparten el mismo contenedor
        // de la aplicación. Sin este reset, esta segunda request seguiría
        // "autenticada" como root aunque la cookie ahora sea la del empleado.
        $this->app['auth']->forgetGuards();

        // withCredentials(): postJson() no manda cookies salvo que se pida
        // explícitamente. withUnencryptedCookies() y no withCookies(): el
        // cliente de test cifra por defecto cualquier cookie que se le pase
        // (asumiendo el comportamiento estándar de Laravel), pero
        // access_token/refresh_token/impersonator_return viajan tal cual las
        // puso el propio backend (ver valores ya en claro extraídos arriba);
        // cifrarlas de nuevo aquí las dejaría ilegibles para la request real.
        $leaveResponse = $this->withoutHeader('Authorization')
            ->withCredentials()
            ->withUnencryptedCookies($cookies)
            ->postJson('/api/impersonate/leave');

        $leaveResponse->assertStatus(200)
            ->assertJsonPath('user.id', $root->id)
            ->assertCookieExpired('impersonator_return');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_IMPERSONATION_STOPPED,
            'user_id' => $root->id,
            'entity_type' => 'User',
            'entity_id' => $employee->id,
        ]);
    }

    public function test_logout_during_impersonation_clears_the_return_ticket(): void
    {
        // Cerrar sesión desde dentro de una impersonación es algo que un root
        // hace sin pensarlo (el botón de logout sigue ahí). Si el logout no
        // expira `impersonator_return`, la cookie sobrevive hasta 8 h y deja a
        // root en un punto muerto: start() responde 403 ("ya estás
        // impersonando") porque ve la cookie, y leave() responde 422 porque el
        // token actual ya no lleva la marca. Sin forma de salir desde la UI.
        [$root, $employee] = $this->makeRootAndEmployee();

        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$employee->id}/impersonate");
        $startResponse->assertStatus(200);

        $cookies = [
            'access_token' => $startResponse->getCookie('access_token', false)->getValue(),
            'refresh_token' => $startResponse->getCookie('refresh_token', false)->getValue(),
            'impersonator_return' => $startResponse->getCookie('impersonator_return', false)->getValue(),
        ];

        $this->app['auth']->forgetGuards();

        $logoutResponse = $this->withoutHeader('Authorization')
            ->withCredentials()
            ->withUnencryptedCookies($cookies)
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertCookieExpired('impersonator_return');
    }

    public function test_refresh_preserves_marker_without_the_access_token_cookie(): void
    {
        // Reproduce el refresh REAL de un navegador. La cookie access_token se
        // emite con maxAge = ACCESS_TOKEN_EXPIRY, el MISMO minuto en que
        // caduca el token que contiene: para cuando el frontend recibe el 401
        // y llama a /refresh, el navegador ya la descartó y solo manda
        // refresh_token (30 días) e impersonator_return (8 h).
        //
        // Si la propagación de la marca depende de esa cookie ausente, la
        // sesión impersonada sobrevive pero deja de registrar al impersonador
        // — y además arrastra el tokens()->delete() que mata la sesión real
        // del empleado.
        [$root, $employee] = $this->makeRootAndEmployee();

        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$employee->id}/impersonate");
        $startResponse->assertStatus(200);

        // La sesión REAL del empleado, abierta en su propio navegador, que no
        // debe verse afectada por lo que haga root.
        $employeeRealToken = $employee->createToken('access_token', ['*'], now()->addMinutes(60));

        $this->app['auth']->forgetGuards();

        // SIN access_token: exactamente lo que manda el navegador.
        $refreshResponse = $this->withoutHeader('Authorization')
            ->withCredentials()
            ->withUnencryptedCookies([
                'refresh_token' => $startResponse->getCookie('refresh_token', false)->getValue(),
                'impersonator_return' => $startResponse->getCookie('impersonator_return', false)->getValue(),
            ])
            ->postJson('/api/refresh');

        $refreshResponse->assertStatus(200);

        // 1) El token nuevo debe seguir marcado, o se pierde el rastro.
        $newTokenValue = $refreshResponse->getCookie('access_token', false)->getValue();
        $newToken = \Laravel\Sanctum\PersonalAccessToken::findToken($newTokenValue);
        $this->assertNotNull($newToken);
        $this->assertSame(
            "impersonation:{$root->id}",
            $newToken->name,
            'El refresh perdió la marca de impersonación: a partir de aquí la auditoría deja de registrar al root.'
        );

        // 2) La sesión real del empleado debe seguir viva.
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $employeeRealToken->accessToken->id,
        ]);
    }

    public function test_leave_does_not_restore_session_for_a_deactivated_root(): void
    {
        // Si desactivan a root mientras impersona, salir NO puede devolverle
        // una sesión: attemptLogin() y refreshAccessToken() ya cortan por
        // status, y leave() debe cortar igual. Sin esto la desactivación no
        // surtía efecto hasta 60 minutos después.
        [$root, $employee] = $this->makeRootAndEmployee();

        $result = app(AuthService::class)->impersonate($root, $employee, '127.0.0.1', 'PHPUnit');

        $root->update(['status' => 'inactive']);

        $leave = app(AuthService::class)->leaveImpersonation(
            $result['user'],
            $result['impersonator_return_token']->token,
            $result['refresh_token']->token,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertNull($leave);
    }

    public function test_leave_expires_the_return_ticket_even_when_it_fails(): void
    {
        // Un boleto de vuelta vencido (TTL 8 h, contra los 30 días que dura la
        // sesión impersonada) o revocado desde otro dispositivo dejaba al
        // usuario atascado: leave() daba 422 sin limpiar la cookie, y start()
        // daba 403 justamente por verla. El botón "Volver a mi cuenta" no
        // hacía nada y la única salida era Logout.
        [$root, $employee] = $this->makeRootAndEmployee();

        $startResponse = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($root))
            ->postJson("/api/users/{$employee->id}/impersonate");
        $startResponse->assertStatus(200);

        $cookies = [
            'access_token' => $startResponse->getCookie('access_token', false)->getValue(),
            'refresh_token' => $startResponse->getCookie('refresh_token', false)->getValue(),
            'impersonator_return' => $startResponse->getCookie('impersonator_return', false)->getValue(),
        ];

        // El boleto caduca mientras la sesión impersonada sigue viva.
        RefreshToken::where('user_id', $root->id)->update(['is_revoked' => true]);

        $this->app['auth']->forgetGuards();

        $leaveResponse = $this->withoutHeader('Authorization')
            ->withCredentials()
            ->withUnencryptedCookies($cookies)
            ->postJson('/api/impersonate/leave');

        $leaveResponse->assertStatus(422)
            ->assertCookieExpired('impersonator_return');
    }
}
