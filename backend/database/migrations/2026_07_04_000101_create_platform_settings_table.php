<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla singleton (una sola fila) con configuración GENERAL de la
     * plataforma. Por ahora solo almacena la IP pública del servidor donde
     * corre el servicio (ítem 23 del sprint-fix), registrada manualmente por
     * root para fines informativos/whitelisting con terceros (bancos,
     * SUNAT, etc.). Mismo patrón que signature_settings: ver
     * SignatureSettings::current() / PlatformSettings::current().
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();

            $table->string('public_ip')->nullable()
                ->comment('IP pública del servidor donde corre la plataforma, registrada manualmente por root');

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Usuario root que actualizó por última vez la configuración');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
