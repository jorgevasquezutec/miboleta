<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla singleton (una sola fila) con la configuración del certificado
     * de firma digital DE LA PLATAFORMA (no por empresa). El certificado
     * (.pfx/.p12) lo carga el usuario root y se usa para firmar documentos
     * bajo DS-009-2011-TR mediante el pipeline de firma (ver
     * SignatureCertificateService). Esta migración es agnóstica al
     * firmador: solo almacena la configuración de forma segura.
     */
    public function up(): void
    {
        Schema::create('signature_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('signature_enabled')->default(false)
                ->comment('Activa/desactiva el uso de firma digital con el certificado de plataforma');

            $table->string('certificate_path')->nullable()
                ->comment('Ruta del archivo .pfx/.p12 dentro del disco privado "certificates"');

            $table->text('certificate_password')->nullable()
                ->comment('Password del certificado, cifrado con el cast "encrypted" (mismo patrón que Tenant::mail_password)');

            $table->string('certificate_subject')->nullable()
                ->comment('Subject/label del certificado (p.ej. CN) para mostrar en UI sin exponer secretos');

            $table->string('tsa_url')->nullable()
                ->comment('URL del servicio de sello de tiempo (TSA) para el firmado PAdES');

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Usuario root que cargó el certificado vigente');

            $table->timestamp('uploaded_at')->nullable()
                ->comment('Fecha/hora de carga del certificado vigente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_settings');
    }
};
