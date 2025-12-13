<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->string('laravel_batch_id')->nullable()->after('id');
            $table->index('laravel_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->dropIndex(['laravel_batch_id']);
            $table->dropColumn('laravel_batch_id');
        });
    }
};
