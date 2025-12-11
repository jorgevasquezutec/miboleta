<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Extra table: No existe en SQL original pero es necesaria para tracking de cargas masivas
     */
    public function up(): void
    {
        Schema::create('document_batches', function (Blueprint $table) {
            $table->id(); // BIGINT como las otras tablas
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('type_id')->constrained('document_types')->onDelete('cascade');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->string('original_filename', 255);

            // Metrics
            $table->integer('total_files')->default(0);
            $table->integer('processed_files')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('replaced_count')->default(0);
            $table->integer('orphan_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('errors')->nullable()->comment('[{file, message}]');

            // Notification settings
            $table->boolean('notify_employees')->default(false);
            $table->boolean('notifications_sent')->default(false);
            $table->boolean('requires_signature')->default(false);

            // Status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'completed_with_errors',
                'failed'
            ])->default('pending');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_batches');
    }
};
