<?php

namespace Tests\Feature\Migrations;

use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración que separa la firma digital de la empresa (digital_*) de la
 * conformidad del trabajador (status/signature/signed_at). Se ejecuta
 * `down()` primero para dejar la tabla en su forma anterior, se insertan filas
 * con la forma ANTIGUA vía DB::table (el modelo ya conoce las columnas nuevas)
 * y se corre `up()`.
 */
class SplitDigitalSignatureMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'database/migrations/2026_10_07_000001_split_digital_signature_on_documents.php';

    private Tenant $tenant;
    private User $user;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->docType = DocumentType::factory()->create();

        $this->migration()->down();
    }

    private function migration()
    {
        return require base_path(self::FILE);
    }

    private function legacyRow(string $n, array $attrs): int
    {
        return DB::table('documents')->insertGetId($attrs + [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'doc_type_id' => $this->docType->id,
            'period' => '2026-09',
            'file_path' => "t/{$n}.pdf",
            'file_size' => 100,
            'original_name' => "{$n}.pdf",
            'employee_document_number' => $n,
            'uploaded_by' => $this->user->id,
            'status' => 'signed',
            'requires_signature' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pades(array $extra = []): string
    {
        return json_encode($extra + [
            'method' => 'pades_pyhanko', 'signer_subject' => 'CN=X', 'certificate_source' => 'tenant',
            'certificate_ruc' => '20603839961', 'sha256' => 'abc',
        ]);
    }

    public function test_backfill_pades_deja_pending_y_mueve_la_metadata(): void
    {
        $pending = $this->legacyRow('1', [
            'signature' => $this->pades(['signing_time' => '2026-10-01T10:00:00-05:00']),
            'signed_at' => '2026-10-01 15:00:05',
        ]);
        // El caso requires_signature=false -> 'active' no se prueba aquí: el enum
        // de sqlite (suite) no incluye 'active' (esa migración solo corre en MySQL).

        $this->migration()->up();

        $row = DB::table('documents')->where('id', $pending)->first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->signature);
        $this->assertNull($row->signed_at);
        $this->assertSame('signed', $row->digital_signature_status);
        $this->assertSame('2026-10-01 15:00:05', $row->digitally_signed_at);
        $this->assertNull($row->original_file_path);
        $this->assertEquals(0, $row->original_has_conformity);

        $digital = json_decode($row->digital_signature, true);
        $this->assertSame('pades_pyhanko', $digital['method']);
        $this->assertSame('tenant', $digital['certificate_source']);
        $this->assertFalse($digital['includes_conformity']);
        $this->assertNull($digital['conformity_signed_at']);
        $this->assertSame(0, $digital['resign_count']);
        $this->assertSame('2026-10-01T10:00:00-05:00', $digital['first_signed_at']); // de signing_time, con su offset
    }

    public function test_first_signed_at_cae_a_signed_at_en_hora_de_lima(): void
    {
        $id = $this->legacyRow('3', [
            'signature' => $this->pades(), // sin signing_time
            'signed_at' => '2026-10-01 15:00:05',
        ]);

        $this->migration()->up();

        $digital = json_decode(DB::table('documents')->where('id', $id)->value('digital_signature'), true);
        $this->assertSame('2026-10-01T15:00:05-05:00', $digital['first_signed_at']);
    }

    public function test_las_filas_2fa_y_sin_firma_quedan_intactas(): void
    {
        $twoFa = json_encode(['verification_method' => 'email_2fa', 'user_name' => 'Ana', 'timestamp' => '2026-10-01T10:00:00+00:00']);
        $signed = $this->legacyRow('4', ['signature' => $twoFa, 'signed_at' => '2026-10-01 15:00:05']);
        $none = $this->legacyRow('5', ['status' => 'pending', 'signature' => null, 'signed_at' => null]);

        $this->migration()->up();

        $row = DB::table('documents')->where('id', $signed)->first();
        $this->assertSame('signed', $row->status);
        $this->assertSame($twoFa, $row->signature);
        $this->assertSame('2026-10-01 15:00:05', $row->signed_at);
        $this->assertNull($row->digital_signature);
        $this->assertNull($row->digital_signature_status);

        $this->assertSame('pending', DB::table('documents')->where('id', $none)->value('status'));
    }

    public function test_down_revierte_solo_donde_el_trabajador_no_firmo_y_quita_las_columnas(): void
    {
        $a = $this->legacyRow('6', ['signature' => $this->pades(['signing_time' => '2026-10-01T10:00:00-05:00']), 'signed_at' => '2026-10-01 15:00:05']);
        $this->migration()->up();
        // Otro documento con las DOS firmas (ya migrado): gana la del trabajador.
        $b = $this->legacyRow('7', [
            'status' => 'signed',
            'signature' => json_encode(['verification_method' => 'email_2fa']),
            'signed_at' => '2026-10-03 10:00:00',
            'digital_signature' => $this->pades(['includes_conformity' => true]),
            'digitally_signed_at' => '2026-10-03 10:01:00',
            'digital_signature_status' => 'signed',
        ]);

        $this->assertTrue(Schema::hasColumn('documents', 'digital_signature'));

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('documents', 'digital_signature'));
        $this->assertFalse(Schema::hasColumn('documents', 'original_file_path'));

        $rowA = DB::table('documents')->where('id', $a)->first();
        $this->assertSame('signed', $rowA->status);
        $this->assertSame('2026-10-01 15:00:05', $rowA->signed_at);
        $sig = json_decode($rowA->signature, true);
        $this->assertSame('pades_pyhanko', $sig['method']);
        $this->assertArrayNotHasKey('includes_conformity', $sig);
        $this->assertArrayNotHasKey('resign_count', $sig);

        $rowB = DB::table('documents')->where('id', $b)->first();
        $this->assertSame(['verification_method' => 'email_2fa'], json_decode($rowB->signature, true));

        // Deja la tabla como el resto de la suite la espera.
        $this->migration()->up();
    }
}
