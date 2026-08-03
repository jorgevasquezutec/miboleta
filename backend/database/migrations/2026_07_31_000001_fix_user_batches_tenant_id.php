<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 4 (OBS-CLIENTE 2026-07, D3/D6): corrige la columna user_batches.
 * tenant_id de los batches históricos creados con el bug
 * "$user->tenants->first()?->id" (UserBatchController::store/uploadData
 * usaba el PRIMER tenant del usuario, no la empresa ACTIVA del switcher).
 *
 * Esta migración NO es necesaria para que el historial vuelva a mostrar esos
 * batches: eso ya lo resuelve retirar TenantFilterScope de UserBatch::
 * booted() (ver esa migración de código). Esta migración solo desambigua la
 * columna "Empresa" que se muestra en el listado/detalle.
 *
 * Regla: si processing_options->affected_tenant_ids tiene EXACTAMENTE 1 id
 * distinto del tenant_id actual, se corrige tenant_id a ese id. Con varios
 * ids (batch que tocó más de una empresa) o sin el dato, no se toca -no hay
 * un único tenant "correcto" que asignarle a la columna-.
 *
 * Idempotente: una segunda corrida no encuentra más filas que corregir (una
 * vez corregido tenant_id, deja de diferir del único affected_tenant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixed = 0;

        DB::table('user_batches')
            ->whereNotNull('processing_options')
            ->orderBy('id')
            ->chunkById(200, function ($batches) use (&$fixed) {
                foreach ($batches as $batch) {
                    $options = json_decode($batch->processing_options ?? 'null', true);
                    $affectedTenantIds = $options['affected_tenant_ids'] ?? null;

                    if (!is_array($affectedTenantIds) || empty($affectedTenantIds)) {
                        continue;
                    }

                    $distinct = array_values(array_unique(array_map('intval', $affectedTenantIds)));

                    if (count($distinct) !== 1) {
                        continue; // multi-empresa: no hay un único tenant correcto
                    }

                    $correctTenantId = $distinct[0];

                    if ((int) $batch->tenant_id === $correctTenantId) {
                        continue; // ya correcto
                    }

                    DB::table('user_batches')
                        ->where('id', $batch->id)
                        ->update(['tenant_id' => $correctTenantId]);

                    $fixed++;
                }
            });

        Log::info('[migration] fix_user_batches_tenant_id ejecutado', ['fixed' => $fixed]);
    }

    public function down(): void
    {
        // No-op intencional: no se guarda el tenant_id original antes de
        // corregirlo, así que no hay forma segura de deshacerlo sin
        // reintroducir el bug. El valor corregido es, en cualquier caso, el
        // dato correcto -no es una migración de esquema reversible-.
    }
};
