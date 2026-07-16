<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repobla `user_tenant_roles` a partir de los roles globales existentes
 * (`user_roles`, excluyendo 'root') para cada empresa del usuario
 * (`user_tenants`).
 *
 * Reemplaza a la migración one-shot 2026_07_02_100005_backfill_user_tenant_roles
 * con una versión idempotente y re-ejecutable: puede correrse tantas veces como
 * haga falta (la unique key uk_user_tenant_role + insertOrIgnore evitan
 * duplicados) tanto en producción (usuarios ya existentes) como manualmente
 * desde soporte.
 */
class BackfillUserTenantRoles extends Command
{
    protected $signature = 'users:backfill-tenant-roles {--dry-run : No inserta, solo reporta cuántas filas se crearían} {--chunk=500 : Tamaño de chunk al recorrer user_tenants}';

    protected $description = 'Repobla user_tenant_roles desde user_roles (excluyendo root) para cada empresa del usuario. Idempotente.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $processed = 0;          // filas de user_tenants procesadas
        $inserted = 0;           // ternas realmente insertadas (o que se insertarían en dry-run)
        $affectedUsers = [];     // user_ids que recibieron al menos una terna
        $unrecoverable = [];     // user_ids sin user_roles operativo Y sin user_tenant_roles

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se insertará ninguna fila; solo se reportarán los conteos.');
        }

        DB::table('user_tenants')
            ->select('user_id', 'tenant_id')
            ->orderBy('id')
            ->chunk($chunkSize, function ($userTenants) use (&$processed, &$inserted, &$affectedUsers, &$unrecoverable, $dryRun) {
                $userIds = $userTenants->pluck('user_id')->unique()->values();
                if ($userIds->isEmpty()) {
                    return;
                }

                // Roles globales operativos por usuario (excluyendo 'root').
                $rolesByUser = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->whereIn('user_roles.user_id', $userIds)
                    ->where('roles.name', '!=', 'root')
                    ->select('user_roles.user_id', 'user_roles.role_id')
                    ->get()
                    ->groupBy('user_id');

                // Ternas ya existentes en user_tenant_roles para estos usuarios,
                // para (a) contar solo las que realmente faltan y (b) detectar
                // usuarios que ya tienen roles por empresa (no erosionados).
                $existing = DB::table('user_tenant_roles')
                    ->whereIn('user_id', $userIds)
                    ->select('user_id', 'tenant_id', 'role_id')
                    ->get();
                $existingKeys = $existing
                    ->map(fn ($r) => $r->user_id . ':' . $r->tenant_id . ':' . $r->role_id)
                    ->flip();
                $usersWithTenantRoles = $existing->pluck('user_id')->unique()->flip();

                $now = now();
                $rows = [];

                foreach ($userTenants as $userTenant) {
                    $processed++;
                    $roles = $rolesByUser->get($userTenant->user_id);

                    if (!$roles || $roles->isEmpty()) {
                        // Sin rol global operativo. Si tampoco tiene ninguna
                        // terna en user_tenant_roles, es un usuario "erosionado"
                        // (posible víctima del sync([]) previo): reportar, no actuar.
                        if (!$usersWithTenantRoles->has($userTenant->user_id)) {
                            $unrecoverable[$userTenant->user_id] = true;
                        }
                        continue;
                    }

                    foreach ($roles as $role) {
                        $key = $userTenant->user_id . ':' . $userTenant->tenant_id . ':' . $role->role_id;
                        if ($existingKeys->has($key)) {
                            continue; // ya existe, no cuenta como insertada
                        }
                        $existingKeys->put($key, true); // evita duplicar dentro del mismo chunk
                        $inserted++;
                        $affectedUsers[$userTenant->user_id] = true;
                        $rows[] = [
                            'user_id' => $userTenant->user_id,
                            'tenant_id' => $userTenant->tenant_id,
                            'role_id' => $role->role_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!$dryRun && !empty($rows)) {
                    foreach (array_chunk($rows, 500) as $batch) {
                        DB::table('user_tenant_roles')->insertOrIgnore($batch);
                    }
                }
            });

        // Un usuario "erosionado" deja de serlo si en otro chunk sí tenía datos.
        foreach (array_keys($affectedUsers) as $uid) {
            unset($unrecoverable[$uid]);
        }
        $unrecoverableIds = array_keys($unrecoverable);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['user_tenants procesados', $processed],
                [$dryRun ? 'filas a insertar (dry-run)' : 'filas insertadas', $inserted],
                ['usuarios afectados', count($affectedUsers)],
                ['usuarios sin rol recuperable', count($unrecoverableIds)],
            ]
        );

        if (!empty($unrecoverableIds)) {
            $preview = array_slice($unrecoverableIds, 0, 20);
            $this->warn('Usuarios sin rol recuperable (sin user_roles operativo ni user_tenant_roles). Requieren revisión manual.');
            $this->line('IDs (primeros 20): ' . implode(', ', $preview) . (count($unrecoverableIds) > 20 ? ' …' : ''));
            Log::warning('[BackfillUserTenantRoles] usuarios sin rol recuperable', [
                'count' => count($unrecoverableIds),
                'user_ids' => $unrecoverableIds,
                'dry_run' => $dryRun,
            ]);
        }

        return self::SUCCESS;
    }
}
