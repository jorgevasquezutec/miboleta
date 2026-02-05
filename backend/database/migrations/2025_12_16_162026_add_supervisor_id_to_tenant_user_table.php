<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar columna a la tabla pivote
        Schema::table('user_tenants', function (Blueprint $table) {
            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // 2. Migrar datos existentes (solo si la columna existe)
        if (Schema::hasColumn('users', 'immediate_supervisor_id')) {
            try {
                $users = DB::table('users')
                    ->whereNotNull('immediate_supervisor_id')
                    ->select('id', 'immediate_supervisor_id')
                    ->get();

                foreach ($users as $user) {
                    // Asignar el supervisor actual a todas las relaciones de tenant del usuario
                    DB::table('user_tenants')
                        ->where('user_id', $user->id)
                        ->update(['supervisor_id' => $user->immediate_supervisor_id]);
                }
            } catch (\Exception $e) {
                logger()->warning('Could not migrate existing supervisors: ' . $e->getMessage());
            }

            // 3. Eliminar la columna antigua de la tabla users
            // Para SQLite, necesitamos desactivar foreign keys temporalmente
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                // SQLite requiere desactivar foreign keys para eliminar columnas con FK
                DB::statement('PRAGMA foreign_keys = OFF');

                // Recrear la tabla sin la columna (SQLite workaround)
                // Pero primero intentemos el enfoque simple
                try {
                    Schema::table('users', function (Blueprint $table) {
                        $table->dropColumn('immediate_supervisor_id');
                    });
                } catch (\Exception $e) {
                    // Si falla, simplemente continuamos - la columna puede quedarse
                    // ya que no afecta la funcionalidad
                    logger()->warning('Could not drop immediate_supervisor_id column: ' . $e->getMessage());
                }

                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['immediate_supervisor_id']);
                    $table->dropColumn('immediate_supervisor_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restaurar la columna en users (si no existe)
        if (!Schema::hasColumn('users', 'immediate_supervisor_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('immediate_supervisor_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });

            // 2. (Opcional) Intentar restaurar datos básicos
            try {
                $tenantUsers = DB::table('user_tenants')
                    ->whereNotNull('supervisor_id')
                    ->get();

                foreach ($tenantUsers as $pivot) {
                    DB::table('users')
                        ->where('id', $pivot->user_id)
                        ->update(['immediate_supervisor_id' => $pivot->supervisor_id]);
                }
            } catch (\Exception $e) {
                // Ignore errors during rollback
            }
        }

        // 3. Eliminar la columna de la tabla pivote
        if (Schema::hasColumn('user_tenants', 'supervisor_id')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                Schema::table('user_tenants', function (Blueprint $table) {
                    $table->dropColumn('supervisor_id');
                });
            } else {
                Schema::table('user_tenants', function (Blueprint $table) {
                    $table->dropForeign(['supervisor_id']);
                    $table->dropColumn('supervisor_id');
                });
            }
        }
    }
};
