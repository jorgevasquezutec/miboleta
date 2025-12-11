<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla para almacenar códigos de verificación para firmas digitales
     */
    public function up(): void
    {
        Schema::create('document_signature_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code_hash', 64)->comment('Hash SHA256 del código');
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Intentos fallidos');
            $table->dateTime('expires_at');
            $table->dateTime('last_attempt_at')->nullable();
            $table->boolean('used')->default(false);
            $table->dateTime('created_at')->nullable();

            // Índices
            $table->index(['document_id', 'user_id', 'used']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signature_codes');
    }
};
