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

        // 2. Migrar datos existentes
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
        Schema::table('users', function (Blueprint $table) {
            // Primero eliminamos la llave foránea (asumiendo convención estándar de nombres de Laravel)
            // O usamos el array syntax que Laravel resuelve automáticamente
            $table->dropForeign(['immediate_supervisor_id']);
            $table->dropColumn('immediate_supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restaurar la columna en users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('immediate_supervisor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        // 2. (Opcional) Intentar restaurar datos básicos... es complejo porque ahora hay múltiples supervisors.
        // Podríamos tomar el primero que encontremos.
        $tenantUsers = DB::table('user_tenants')
            ->whereNotNull('supervisor_id')
            ->get();

        foreach ($tenantUsers as $pivot) {
            DB::table('users')
                ->where('id', $pivot->user_id)
                ->update(['immediate_supervisor_id' => $pivot->supervisor_id]);
        }

        // 3. Eliminar la columna de la tabla pivote
        Schema::table('user_tenants', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });
    }
};
