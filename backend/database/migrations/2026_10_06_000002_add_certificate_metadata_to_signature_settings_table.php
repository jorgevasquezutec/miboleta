<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatos legibles del certificado global (RUC, razón social, vencimiento).
 * Quedan NULL hasta que root vuelva a cargar el certificado global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_settings', function (Blueprint $table) {
            $table->string('certificate_ruc', 11)->nullable()->after('certificate_subject');
            $table->string('certificate_organization')->nullable()->after('certificate_ruc');
            $table->dateTime('certificate_expires_at')->nullable()->after('certificate_organization');
        });
    }

    public function down(): void
    {
        Schema::table('signature_settings', function (Blueprint $table) {
            $table->dropColumn(['certificate_ruc', 'certificate_organization', 'certificate_expires_at']);
        });
    }
};
