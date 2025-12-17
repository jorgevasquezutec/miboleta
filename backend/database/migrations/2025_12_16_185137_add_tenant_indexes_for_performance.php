<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Añade índices compuestos en las columnas tenant_id para mejorar
     * el performance de queries con filtrado multi-tenant.
     */
    public function up(): void
    {
        // ✅ Índice compuesto en documents: tenant_id + status + created_at
        // Optimiza queries: WHERE tenant_id IN (...) AND status = 'x' ORDER BY created_at
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_documents_tenant_status_created');
            });
        } catch (\Exception $e) {
            // Índice ya existe o error, continuar
        }

        // ✅ Índice simple en documents: tenant_id (si no existe)
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->index('tenant_id', 'idx_documents_tenant_id');
            });
        } catch (\Exception $e) {
            // Índice ya existe
        }

        // ✅ Índice compuesto en vacation_requests: tenant_id + status + start_date
        try {
            Schema::table('vacation_requests', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'start_date'], 'idx_vacation_requests_tenant_status_start');
            });
        } catch (\Exception $e) {
            // Índice ya existe
        }

        // ✅ Índice simple en vacation_requests: tenant_id (si no existe)
        try {
            Schema::table('vacation_requests', function (Blueprint $table) {
                $table->index('tenant_id', 'idx_vacation_requests_tenant_id');
            });
        } catch (\Exception $e) {
            // Índice ya existe
        }

        // ✅ Índice en audit_logs si existe la tabla
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'tenant_id')) {
            try {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->index(['tenant_id', 'created_at'], 'idx_audit_logs_tenant_created');
                });
            } catch (\Exception $e) {
                // Índice ya existe
            }
        }

        // ✅ Índice en document_batches si existe
        if (Schema::hasTable('document_batches') && Schema::hasColumn('document_batches', 'tenant_id')) {
            try {
                Schema::table('document_batches', function (Blueprint $table) {
                    $table->index('tenant_id', 'idx_document_batches_tenant');
                });
            } catch (\Exception $e) {
                // Índice ya existe
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar índices en orden inverso

        if (Schema::hasTable('document_batches')) {
            try {
                Schema::table('document_batches', function (Blueprint $table) {
                    $table->dropIndex('idx_document_batches_tenant');
                });
            } catch (\Exception $e) {
                // Índice no existe
            }
        }

        if (Schema::hasTable('audit_logs')) {
            try {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->dropIndex('idx_audit_logs_tenant_created');
                });
            } catch (\Exception $e) {
                // Índice no existe
            }
        }

        try {
            Schema::table('vacation_requests', function (Blueprint $table) {
                $table->dropIndex('idx_vacation_requests_tenant_id');
            });
        } catch (\Exception $e) {
            // Índice no existe
        }

        try {
            Schema::table('vacation_requests', function (Blueprint $table) {
                $table->dropIndex('idx_vacation_requests_tenant_status_start');
            });
        } catch (\Exception $e) {
            // Índice no existe
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_documents_tenant_id');
            });
        } catch (\Exception $e) {
            // Índice no existe
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_documents_tenant_status_created');
            });
        } catch (\Exception $e) {
            // Índice no existe
        }
    }
};
