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
            $table->date('hire_date')
                ->nullable()
                ->after('supervisor_id')
                ->comment('Fecha de inicio laboral del usuario en esta empresa');

            $table->decimal('vacation_balance_initial', 5, 2)
                ->nullable()
                ->default(0)
                ->after('hire_date')
                ->comment('Saldo inicial de vacaciones al momento del alta en la empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_tenants', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'vacation_balance_initial']);
        });
    }
};
