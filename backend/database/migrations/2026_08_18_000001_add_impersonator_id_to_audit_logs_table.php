<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Impersonation ("iniciar sesión como"): quién estaba REALMENTE
            // detrás de la acción. `user_id` sigue siendo el actor de negocio
            // (el empleado impersonado) — no se toca ni se reinterpreta, ver
            // AuditService::log(). nullOnDelete() y no cascade: si se elimina
            // la cuenta root, el rastro de auditoría del empleado debe
            // sobrevivir; solo se pierde el detalle de quién impersonaba.
            $table->foreignId('impersonator_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('impersonator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['impersonator_id']);
            $table->dropIndex(['impersonator_id']);
            $table->dropColumn('impersonator_id');
        });
    }
};
