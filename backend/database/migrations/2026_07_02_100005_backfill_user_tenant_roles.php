<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Copia los roles globales actuales del usuario (user_roles), excluyendo 'root',
     * hacia la nueva tabla user_tenant_roles para cada empresa a la que pertenece.
     * Esto preserva el comportamiento actual (roles operativos visibles en todas
     * sus empresas) hasta que el workstream de auth/services gestione la asignación
     * de roles por empresa de forma granular.
     */
    public function up(): void
    {
        DB::table('user_tenants')
            ->select('user_id', 'tenant_id')
            ->orderBy('id')
            ->chunk(500, function ($userTenants) {
                $userIds = $userTenants->pluck('user_id')->unique()->values();

                if ($userIds->isEmpty()) {
                    return;
                }

                // Roles globales actuales por usuario, excluyendo 'root'
                $rolesByUser = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->whereIn('user_roles.user_id', $userIds)
                    ->where('roles.name', '!=', 'root')
                    ->select('user_roles.user_id', 'user_roles.role_id')
                    ->get()
                    ->groupBy('user_id');

                $now = now();
                $rows = [];

                foreach ($userTenants as $userTenant) {
                    $roles = $rolesByUser->get($userTenant->user_id);

                    if (!$roles) {
                        continue;
                    }

                    foreach ($roles as $role) {
                        $rows[] = [
                            'user_id' => $userTenant->user_id,
                            'tenant_id' => $userTenant->tenant_id,
                            'role_id' => $role->role_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('user_tenant_roles')->insertOrIgnore($chunk);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_tenant_roles')->truncate();
    }
};
