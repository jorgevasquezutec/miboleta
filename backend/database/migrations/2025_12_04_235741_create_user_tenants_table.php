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
        Schema::create('user_tenants', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Tenant principal del usuario
            $table->boolean('is_primary')->default(false)->comment('Indica si es el tenant principal del usuario');

            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            
            // Constraint: Un usuario solo puede pertenecer una vez a un tenant
            $table->unique(['user_id', 'tenant_id']);
            
            // Índices
            $table->index('user_id');
            $table->index('tenant_id');
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tenants');
    }
};
