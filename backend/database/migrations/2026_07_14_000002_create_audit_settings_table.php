<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla singleton (una sola fila) para el mantenedor de auditoría: guarda
     * el conjunto de acciones cuya CAPTURA está desactivada. Default = vacío
     * (todo activo, retrocompatible). Las acciones ALWAYS_ON nunca se
     * desactivan (se filtran en AuditSettingsService y se fuerzan en
     * AuditService). Mismo patrón singleton que platform_settings /
     * signature_settings.
     */
    public function up(): void
    {
        Schema::create('audit_settings', function (Blueprint $table) {
            $table->id();

            $table->json('disabled_actions')->nullable()
                ->comment('Acciones de auditoría con la captura desactivada (array de strings). Null/[] = todo activo.');

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Usuario root que actualizó por última vez la configuración de auditoría');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_settings');
    }
};
