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
        Schema::create('user_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();

            // Archivo original
            $table->string('original_filename')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            // Estado y progreso
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial'])
                ->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_users')->default(0);
            $table->unsignedInteger('updated_users')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);

            // Chunks
            $table->unsignedInteger('current_chunk')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);

            // Resultados y errores
            $table->json('error_summary')->nullable();
            $table->json('success_summary')->nullable();
            $table->json('processing_options')->nullable();

            // Tiempos
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();

            // Índices para performance
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            $table->index('created_by_user_id', 'idx_created_by');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_batches');
    }
};
