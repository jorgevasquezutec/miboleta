<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify enum to add 'active' status
        DB::statement("ALTER TABLE documents MODIFY COLUMN status ENUM('pending', 'signed', 'expired', 'orphan', 'active') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'active' from enum
        DB::statement("ALTER TABLE documents MODIFY COLUMN status ENUM('pending', 'signed', 'expired', 'orphan') NOT NULL DEFAULT 'pending'");
    }
};
