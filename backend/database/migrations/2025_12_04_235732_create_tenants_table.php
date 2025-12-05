<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            
            // Información básica
            $table->string('name', 100)->comment('Nombre corto del tenant');
            $table->string('ruc', 11)->unique()->comment('RUC de la empresa');
            $table->string('business_name', 200)->comment('Razón social');
            
            // Información de contacto
            $table->text('address')->nullable()->comment('Dirección completa');
            $table->string('phone', 20)->nullable()->comment('Teléfono principal');
            
            // Logo y branding
            $table->string('logo_path')->nullable()->comment('Ruta del logo');
            
            // Estado
            $table->string('status', 20)->default('active')->comment('Estado: active, inactive, suspended');
            
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            
            // Índices
            $table->index('status');
            $table->index('ruc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
