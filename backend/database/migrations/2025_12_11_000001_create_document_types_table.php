<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Híbrido: Campos del SQL original + campos extras para tracking de batches
     */
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id(); // BIGINT como en el modelo SQL
            $table->string('name', 50)->unique()->comment('boleta, liquidacion, cts, etc.');
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->boolean('requires_signature')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // Extra: para ordenar en UI
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Índices del SQL original
            $table->index('is_active', 'idx_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
