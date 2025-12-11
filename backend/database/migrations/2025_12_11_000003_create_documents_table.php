<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Híbrido: Campos del SQL original + campos extras para batch tracking y versionado
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id(); // BIGINT como en el modelo SQL original
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('NULL = documento huérfano');
            $table->foreignId('batch_id')->nullable()->constrained('document_batches')->onDelete('set null')->comment('Extra: tracking de carga');
            $table->foreignId('doc_type_id')->constrained('document_types')->onDelete('restrict');

            $table->string('employee_document_number', 20)->comment('Siempre presente para matching');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size');
            $table->string('original_name', 255);

            // Status del SQL original
            $table->enum('status', ['pending', 'signed', 'expired', 'orphan'])->default('pending');

            // Campos del SQL original
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->json('signature')->nullable()->comment('IP, user_agent, hash, geolocalización');
            $table->dateTime('signed_at')->nullable();
            $table->dateTime('expires_at')->nullable()->comment('Expiración automática si no firma');

            // Campos extras para features adicionales
            $table->boolean('requires_signature')->default(true)->comment('Extra: hereda del batch o type');
            $table->boolean('notified')->default(false)->comment('Extra: tracking de notificación');
            $table->dateTime('notified_at')->nullable();
            $table->integer('version')->default(1)->comment('Extra: versionado para reemplazos');

            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            // Constraint único del SQL original
            $table->unique(
                ['tenant_id', 'employee_document_number', 'doc_type_id', 'period'],
                'uk_tenant_doc'
            );

            // Índices del SQL original
            $table->index(['tenant_id', 'user_id'], 'idx_tenant_user');
            $table->index('employee_document_number', 'idx_employee_doc');
            $table->index('period', 'idx_period');
            $table->index('status', 'idx_status');
            $table->index('expires_at', 'idx_expires_at');
            $table->index('deleted_at', 'idx_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
