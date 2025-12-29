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
            // Campos de información personal (solo si no existen)
            if (!Schema::hasColumn('users', 'document_type')) {
                $table->string('document_type', 20)
                    ->nullable()
                    ->after('remember_token')
                    ->comment('Tipo de documento');
            }

            if (!Schema::hasColumn('users', 'document_text')) {
                $table->string('document_text', 20)
                    ->nullable()
                    ->unique()
                    ->after('document_type')
                    ->comment('Número de documento (NULL para root)');
            }

            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)
                    ->nullable()
                    ->after('name')
                    ->comment('Apellidos del usuario');
            }

            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)
                    ->nullable()
                    ->after('last_name')
                    ->comment('Teléfono de contacto');
            }

            // Jerarquía organizacional
            if (!Schema::hasColumn('users', 'immediate_supervisor_id')) {
                $table->foreignId('immediate_supervisor_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('users')
                    ->onDelete('set null')
                    ->comment('Jefe inmediato (self-reference)');
            }

            // Estado del usuario
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)
                    ->default('pending')
                    ->after('immediate_supervisor_id')
                    ->comment('Estado: active, inactive, terminated, pending');
            }

            // Último login
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->dateTime('last_login_at')
                    ->nullable()
                    ->after('status')
                    ->comment('Última fecha de inicio de sesión');
            }

            // Soft deletes
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->dateTime('deleted_at')->nullable()->after('updated_at');
            }
        });

        // Índices después de agregar columnas
        // Los índices se agregan dentro de un bloque separado para evitar errores si ya existen
        try {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'document_type')) {
                    $table->index('document_type');
                }
            });
        } catch (\Exception $e) {
            // Índice ya existe, continuar
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'status')) {
                    $table->index('status');
                }
            });
        } catch (\Exception $e) {
            // Índice ya existe, continuar
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'immediate_supervisor_id')) {
                    $table->index('immediate_supervisor_id');
                }
            });
        } catch (\Exception $e) {
            // Índice ya existe, continuar
        }
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
