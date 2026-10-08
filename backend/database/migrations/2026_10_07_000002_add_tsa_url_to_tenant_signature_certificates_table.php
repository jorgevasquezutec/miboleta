<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_signature_certificates', function (Blueprint $table) {
            $table->string('tsa_url', 255)->nullable()->after('certificate_expires_at')
                ->comment('TSA propia de la empresa; null = usar la TSA global');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_signature_certificates', function (Blueprint $table) {
            $table->dropColumn('tsa_url');
        });
    }
};
