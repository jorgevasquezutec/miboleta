<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\AuditSettings;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\DocumentSignatureCode;
use App\Models\DocumentType;
use App\Models\Notification;
use App\Models\PlatformSettings;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use App\Models\VacationRequest;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cubre miboleta:limpiar-datos (ver App\Console\Commands\LimpiarDatos):
 * dry-run, confirmación, conservación de root(s) y borrado real de datos de
 * negocio + archivos, sin tocar catálogo/configuración ni el certificado.
 */
class LimpiarDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Storage::fake('documents');
        Storage::fake('local');
        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('certificates');
    }

    /**
     * Misma fórmula que LimpiarDatos::fraseConfirmacion(): se calcula aquí
     * en vez de hardcodearla para no depender del .env real de quien corra
     * los tests.
     */
    private function fraseConfirmacion(): string
    {
        $conexion = config('database.default');
        $baseDatos = (string) config("database.connections.{$conexion}.database");
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url');

        return "BORRAR {$baseDatos}@{$host}";
    }

    /**
     * Arma un escenario de negocio "completo" colgado de un tenant y de un
     * usuario NO root (que debe desaparecer al ejecutar el comando).
     */
    private function crearDatosDeNegocio(): array
    {
        $tenant = Tenant::factory()->create(['logo_path' => 'tenants/logos/logo1.png']);
        Storage::disk('public')->put('tenants/logos/logo1.png', 'logo');

        $empleado = User::factory()
            ->client()
            ->withTenantRole($tenant, 'client', true)
            ->create(['avatar_url' => 'avatars/empleado.png']);
        Storage::disk('public')->put('avatars/empleado.png', 'avatar');

        $docType = DocumentType::factory()->create();

        // DocumentBatch no tiene factory propia: se crea directo con los
        // campos NOT NULL de la migración (tenant_id, uploaded_by, type_id,
        // period, original_filename).
        $batch = DocumentBatch::create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $empleado->id,
            'type_id' => $docType->id,
            'period' => now()->format('Y-m'),
            'original_filename' => 'lote.zip',
        ]);

        $documento = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $empleado->id,
            'uploaded_by' => $empleado->id,
            'doc_type_id' => $docType->id,
            'batch_id' => $batch->id,
            'file_path' => 'documento1.pdf',
        ]);
        Storage::disk('documents')->put('documento1.pdf', 'contenido');

        // Documento soft-deleted: debe desaparecer de verdad (DB::table no
        // respeta el global scope de SoftDeletes).
        $documentoBorrado = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $empleado->id,
            'uploaded_by' => $empleado->id,
            'doc_type_id' => $docType->id,
            'file_path' => 'documento2.pdf',
        ]);
        $documentoBorrado->delete();

        DocumentSignatureCode::create([
            'document_id' => $documento->id,
            'user_id' => $empleado->id,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        VacationRequest::factory()->create([
            'user_id' => $empleado->id,
            'tenant_id' => $tenant->id,
        ]);

        Notification::factory()->create([
            'user_id' => $empleado->id,
            'tenant_id' => $tenant->id,
        ]);

        UserBatch::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $empleado->id,
            'status' => 'completed',
        ]);

        Storage::disk('private')->put('user-batches/carga.xlsx', 'contenido');
        Storage::disk('local')->put('temp/zip123.zip', 'contenido');

        $empleado->refreshTokens()->create([
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $empleado->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        return compact('tenant', 'empleado', 'documento');
    }

    public function test_dry_run_no_modifica_nada(): void
    {
        $root = User::factory()->root()->create();
        $this->crearDatosDeNegocio();

        $tenantsAntes = DB::table('tenants')->count();
        $usersAntes = DB::table('users')->count();
        $documentsAntes = DB::table('documents')->count();

        $this->artisan('miboleta:limpiar-datos')->assertExitCode(0);

        $this->assertSame($tenantsAntes, DB::table('tenants')->count());
        $this->assertSame($usersAntes, DB::table('users')->count());
        $this->assertSame($documentsAntes, DB::table('documents')->count());
        $this->assertTrue(User::query()->whereKey($root->id)->exists());
        Storage::disk('documents')->assertExists('documento1.pdf');
    }

    public function test_confirmacion_incorrecta_no_cambia_nada(): void
    {
        User::factory()->root()->create();
        $datos = $this->crearDatosDeNegocio();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => 'frase incorrecta',
        ])->assertExitCode(1);

        $this->assertTrue(Tenant::query()->whereKey($datos['tenant']->id)->exists());
        $this->assertTrue(User::query()->whereKey($datos['empleado']->id)->exists());
    }

    public function test_confirmacion_interactiva_incorrecta_no_cambia_nada(): void
    {
        User::factory()->root()->create();
        $datos = $this->crearDatosDeNegocio();

        $this->artisan('miboleta:limpiar-datos', ['--ejecutar' => true])
            ->expectsQuestion("Escribe exactamente «{$this->fraseConfirmacion()}» para continuar", 'no')
            ->assertExitCode(1);

        $this->assertTrue(Tenant::query()->whereKey($datos['tenant']->id)->exists());
        $this->assertTrue(User::query()->whereKey($datos['empleado']->id)->exists());
    }

    public function test_ejecucion_conserva_roots_activos_y_borra_todo_lo_demas(): void
    {
        $root = User::factory()->root()->create(['avatar_url' => 'avatars/root.png']);
        Storage::disk('public')->put('avatars/root.png', 'avatar root');

        $rootBorrado = User::factory()->root()->create();
        $rootBorrado->delete(); // soft-deleted: NO cuenta como activo, debe borrarse

        $datos = $this->crearDatosDeNegocio();

        PlatformSettings::current();
        AuditSettings::current();
        SignatureSettings::query()->create([
            'signature_enabled' => true,
            'certificate_path' => 'certificado.pfx',
            'uploaded_by' => $datos['empleado']->id,
        ]);
        Storage::disk('certificates')->put('certificado.pfx', 'contenido-certificado');

        $rolesAntes = DB::table('roles')->count();
        $tiposDocumentoAntes = DB::table('document_types')->count();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
        ])->assertExitCode(0);

        // Solo queda el root activo.
        $this->assertSame([$root->id], DB::table('users')->pluck('id')->all());
        $this->assertTrue($root->fresh()->hasRole('root'));

        // Negocio borrado por completo (incluido lo soft-deleted).
        foreach (['tenants', 'documents', 'document_batches', 'document_signature_codes', 'vacation_requests', 'notifications', 'user_batches', 'user_tenants', 'user_tenant_roles', 'sessions', 'personal_access_tokens', 'refresh_tokens'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "La tabla {$tabla} debería quedar vacía");
        }

        // Auditoría: se borró todo lo anterior y queda SOLO el evento del wipe.
        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(
            AuditLog::ACTION_PLATFORM_DATA_WIPED,
            DB::table('audit_logs')->value('action')
        );

        // Catálogo/config intactos.
        $this->assertSame($rolesAntes, DB::table('roles')->count());
        $this->assertSame($tiposDocumentoAntes, DB::table('document_types')->count());
        $this->assertNotNull(PlatformSettings::current()->id);
        $this->assertNotNull(AuditSettings::current()->id);

        // signature_settings sobrevive; su uploaded_by (el empleado borrado)
        // queda en NULL por la FK nullOnDelete, sin que el comando lo toque.
        $settings = SignatureSettings::first();
        $this->assertNotNull($settings);
        $this->assertNull($settings->uploaded_by);

        // Archivos.
        Storage::disk('certificates')->assertExists('certificado.pfx');
        $this->assertEmpty(Storage::disk('documents')->allFiles());
        $this->assertEmpty(Storage::disk('private')->allFiles());
        $this->assertEmpty(Storage::disk('local')->allFiles());
        Storage::disk('public')->assertExists('avatars/root.png');
        Storage::disk('public')->assertMissing('avatars/empleado.png');
        Storage::disk('public')->assertMissing('tenants/logos/logo1.png');
    }

    public function test_documento_de_usuario_borrado_no_rompe_restriccion_fk(): void
    {
        User::factory()->root()->create();
        $this->crearDatosDeNegocio();

        // documents.uploaded_by es RESTRICT: si el comando intentara borrar
        // primero al usuario, esto lanzaría una excepción de integridad.
        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('documents')->count());
        $this->assertSame(0, DB::table('document_batches')->count());
    }

    public function test_mantener_root_con_email_no_root_falla(): void
    {
        User::factory()->root()->create();
        $empleado = User::factory()->client()->create(['email' => 'empleado@empresa.com']);

        $usersAntes = DB::table('users')->count();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
            '--mantener-root' => ['empleado@empresa.com'],
        ])->assertExitCode(1);

        $this->assertSame($usersAntes, DB::table('users')->count());
        $this->assertTrue(User::query()->whereKey($empleado->id)->exists());
    }

    public function test_mantener_id_conserva_solo_ese_root_entre_varios(): void
    {
        $root1 = User::factory()->root()->create();
        $root2 = User::factory()->root()->create();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
            '--mantener-id' => [(string) $root1->id],
        ])->assertExitCode(0);

        $this->assertSame([$root1->id], DB::table('users')->pluck('id')->all());
        $this->assertFalse(User::query()->whereKey($root2->id)->exists());
    }

    public function test_mantener_id_con_usuario_no_root_falla(): void
    {
        User::factory()->root()->create();
        $empleado = User::factory()->client()->create();

        $usersAntes = DB::table('users')->count();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
            '--mantener-id' => [(string) $empleado->id],
        ])->assertExitCode(1);

        $this->assertSame($usersAntes, DB::table('users')->count());
    }

    public function test_mantener_id_y_mantener_root_juntos_falla(): void
    {
        $root = User::factory()->root()->create();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
            '--mantener-id' => [(string) $root->id],
            '--mantener-root' => [$root->email],
        ])->assertExitCode(1);

        $this->assertTrue(User::query()->whereKey($root->id)->exists());
    }

    public function test_ejecutar_dos_veces_no_falla(): void
    {
        $root = User::factory()->root()->create();
        $this->crearDatosDeNegocio();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
        ])->assertExitCode(0);

        // Segunda corrida: ya no hay nada de negocio que borrar, pero debe
        // seguir funcionando (idempotente) y el root sigue ahí.
        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
        ])->assertExitCode(0);

        $this->assertSame([$root->id], DB::table('users')->pluck('id')->all());
    }

    public function test_conserva_el_gitignore_de_la_raiz_de_los_discos(): void
    {
        User::factory()->root()->create();
        $this->crearDatosDeNegocio();
        Storage::disk('private')->put('.gitignore', "*\n!.gitignore\n");
        Storage::disk('documents')->put('.gitignore', "*\n!.gitignore\n");

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
        ])->assertExitCode(0);

        Storage::disk('private')->assertExists('.gitignore');
        Storage::disk('documents')->assertExists('.gitignore');
        Storage::disk('private')->assertMissing('user-batches/carga.xlsx');
        Storage::disk('documents')->assertMissing('documento1.pdf');
    }

    public function test_mantener_auditoria_conserva_audit_logs(): void
    {
        User::factory()->root()->create();
        $this->crearDatosDeNegocio();

        AuditLog::create([
            'action' => AuditLog::ACTION_USER_LOGIN,
            'created_at' => now(),
        ]);

        $auditoriaAntes = DB::table('audit_logs')->count();

        $this->artisan('miboleta:limpiar-datos', [
            '--ejecutar' => true,
            '--confirmar' => $this->fraseConfirmacion(),
            '--mantener-auditoria' => true,
        ])->assertExitCode(0);

        // Se conservan las anteriores + la nueva del wipe.
        $this->assertSame($auditoriaAntes + 1, DB::table('audit_logs')->count());
        $this->assertTrue(
            DB::table('audit_logs')->where('action', AuditLog::ACTION_PLATFORM_DATA_WIPED)->exists()
        );
        $this->assertTrue(
            DB::table('audit_logs')->where('action', AuditLog::ACTION_USER_LOGIN)->exists()
        );
    }
}
