<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Re-ejecuta el backfill de user_tenant_roles de forma idempotente en el
 * próximo deploy.
 *
 * La migración original 2026_07_02_100005 era one-shot y en muchos casos
 * corrió antes de que existieran usuarios (BD vacía) o saltó usuarios sin
 * user_roles/user_tenants en ese instante, dejando user_tenant_roles vacío.
 * Como `docker-entrypoint.sh` corre `php artisan migrate --force` en cada
 * deploy pero nunca `db:seed`, esta migración es el mecanismo automático que
 * repuebla los datos de usuarios existentes. Delega en el comando idempotente
 * (insertOrIgnore + unique key), por lo que es seguro correrla de nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('users:backfill-tenant-roles');
        Log::info('[migration] backfill_user_tenant_roles_v2 ejecutado', [
            'output' => Artisan::output(),
        ]);
    }

    public function down(): void
    {
        // No-op intencional: a diferencia de 100005::down(), NO truncamos
        // user_tenant_roles, porque eso borraría asignaciones legítimas
        // creadas después de este backfill.
    }
};
