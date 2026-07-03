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
        Schema::create('user_tenant_roles', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');

            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            // Un usuario no puede tener el mismo rol duplicado en la misma empresa
            $table->unique(['user_id', 'tenant_id', 'role_id'], 'uk_user_tenant_role');

            // Índices
            $table->index('user_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tenant_roles');
    }
};
