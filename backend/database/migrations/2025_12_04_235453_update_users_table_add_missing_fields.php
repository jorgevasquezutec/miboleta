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
        Schema::table('users', function (Blueprint $table) {
            // Campos de información personal
            $table->string('document_type', 20)
                ->nullable()
                ->after('remember_token')
                ->comment('Tipo de documento');
            
            $table->string('document_text', 20)
                ->nullable()
                ->unique()
                ->after('document_type')
                ->comment('Número de documento (NULL para root)');
            
            $table->string('last_name', 100)
                ->nullable()
                ->after('name')
                ->comment('Apellidos del usuario');
            
            $table->string('phone', 20)
                ->nullable()
                ->after('last_name')
                ->comment('Teléfono de contacto');
            
            // Jerarquía organizacional
            $table->foreignId('immediate_supervisor_id')
                ->nullable()
                ->after('phone')
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Jefe inmediato (self-reference)');
            
            // Estado del usuario
            $table->string('status', 20)
                ->default('pending')
                ->after('immediate_supervisor_id')
                ->comment('Estado: active, inactive, terminated, pending');
            
            // Último login
            $table->dateTime('last_login_at')
                ->nullable()
                ->after('status')
                ->comment('Última fecha de inicio de sesión');
            
            // Soft deletes
            $table->dateTime('deleted_at')->nullable()->after('updated_at');
            
            // Índices
            $table->index('document_type');
            $table->index('status');
            $table->index('immediate_supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Primero verificar y eliminar foreign key si existe
            if (Schema::hasColumn('users', 'immediate_supervisor_id')) {
                $table->dropForeign(['immediate_supervisor_id']);
            }

            // Eliminar columnas que existan (los índices se eliminan automáticamente)
            $columnsToCheck = [
                'document_type',
                'document_text',
                'last_name',
                'phone',
                'immediate_supervisor_id',
                'status',
                'last_login_at',
                'deleted_at',
            ];

            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
