<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado de firma digital PROPIO de cada empresa (uno por empresa).
 * Si una empresa no tiene fila aquí, sus documentos se firman con el
 * certificado global de signature_settings (fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_signature_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete()
                ->comment('Empresa dueña del certificado (uno por empresa)');
            $table->string('certificate_path')->comment('Ruta del .pfx/.p12 en el disco privado "certificates"');
            $table->text('certificate_password')->comment('Cifrada con cast encrypted');
            $table->string('certificate_subject')->nullable()->comment('CN o subject legible (máx 255)');
            $table->string('certificate_ruc', 11)->nullable()->comment('RUC leído del subject');
            $table->string('certificate_organization')->nullable()->comment('Razón social (O) del subject (máx 255)');
            // dateTime y NO timestamp: TIMESTAMP de MySQL termina en 2038 y un
            // certificado puede vencer después.
            $table->dateTime('certificate_expires_at')->nullable()->comment('notAfter del certificado, en zona app');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_signature_certificates');
    }
};
