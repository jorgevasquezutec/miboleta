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
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('labor_regime', ['general', 'micro', 'pequena'])
                ->default('general')
                ->after('initial_employee_count')
                ->comment('Régimen laboral peruano para el cómputo de vacaciones: general (30 días/año) o MYPE micro/pequeña (15 días/año)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('labor_regime');
        });
    }
};
