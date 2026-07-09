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
        Schema::table('user_tenants', function (Blueprint $table) {
            $table->string('department')
                ->nullable()
                ->after('vacation_balance_initial')
                ->comment('Departamento/área del usuario dentro de esta empresa');

            $table->string('position')
                ->nullable()
                ->after('department')
                ->comment('Cargo/puesto del usuario dentro de esta empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tenants', function (Blueprint $table) {
            $table->dropColumn(['department', 'position']);
        });
    }
};
