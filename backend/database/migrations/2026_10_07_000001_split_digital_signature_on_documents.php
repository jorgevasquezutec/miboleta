<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa las dos firmas que convivían (mal) en las columnas `signature` /
 * `signed_at` / `status = 'signed'` de documents:
 *
 *   - Conformidad del trabajador (código 2FA por correo): sigue en
 *     `status`, `signature` y `signed_at`. No es criptográfica.
 *   - Firma digital de la empresa (PAdES con su certificado): pasa a las
 *     columnas nuevas `digital_*`.
 *
 * Backfill: antes, un documento firmado con PAdES quedaba con
 * signature.method = 'pades_pyhanko' y status = 'signed' SIN que el
 * trabajador hubiera dado su conformidad (assertEligible solo firmaba
 * documentos 'pending'). Esos documentos pasan sus datos a `digital_*` y
 * vuelven a 'pending' (o 'active' si no requieren firma) para que el
 * trabajador pueda firmar su conformidad. Si requires_signature y expires_at
 * ya pasó, vuelven a contar como vencidos/pendientes: es lo esperado.
 *
 * Todo en PHP portable (sin funciones exclusivas de MySQL): la suite corre
 * en sqlite :memory:.
 *
 * down(): LIMITACIÓN conocida -solo revierte los documentos que NO tienen la
 * conformidad del trabajador (status <> 'signed'). Si existen las dos firmas,
 * gana la del trabajador y se pierde la metadata PAdES al borrar las columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->json('digital_signature')->nullable()->after('signed_at');
            $table->dateTime('digitally_signed_at')->nullable()->after('digital_signature');
            // null = no aplica / nunca se intentó; pending | signed | failed
            $table->string('digital_signature_status', 20)->nullable()->after('digitally_signed_at');
            $table->string('digital_signature_error', 500)->nullable()->after('digital_signature_status');
            // Base de re-firma: revisión 0 normalizada a PDF/A (disco documents).
            $table->string('original_file_path')->nullable()->after('digital_signature_error');
            // La base ya trae el nombre del trabajador dibujado por FPDI.
            $table->boolean('original_has_conformity')->default(false)->after('original_file_path');

            $table->index('digital_signature_status');
        });

        DB::table('documents')->whereNotNull('signature')->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $sig = json_decode($row->signature, true);
                    if (!is_array($sig) || ($sig['method'] ?? null) !== 'pades_pyhanko') {
                        continue;
                    }

                    $first = $sig['signing_time'] ?? ($row->signed_at
                        ? Carbon::parse($row->signed_at, 'America/Lima')->toIso8601String()
                        : null);

                    DB::table('documents')->where('id', $row->id)->update([
                        'digital_signature' => json_encode($sig + [
                            'includes_conformity' => false,
                            'conformity_signed_at' => null,
                            'first_signed_at' => $first,
                            'resign_count' => 0,
                        ]),
                        'digitally_signed_at' => $row->signed_at,
                        'digital_signature_status' => 'signed',
                        'signature' => null,
                        'signed_at' => null,
                        'status' => $row->requires_signature ? 'pending' : 'active',
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('documents')->whereNotNull('digital_signature')->where('status', '<>', 'signed')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $sig = json_decode($row->digital_signature, true);
                    if (!is_array($sig)) {
                        continue;
                    }
                    unset($sig['includes_conformity'], $sig['conformity_signed_at'], $sig['first_signed_at'], $sig['resign_count']);

                    DB::table('documents')->where('id', $row->id)->update([
                        'signature' => json_encode($sig),
                        'signed_at' => $row->digitally_signed_at,
                        'status' => 'signed',
                    ]);
                }
            });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['digital_signature_status']);
            $table->dropColumn([
                'digital_signature',
                'digitally_signed_at',
                'digital_signature_status',
                'digital_signature_error',
                'original_file_path',
                'original_has_conformity',
            ]);
        });
    }
};
