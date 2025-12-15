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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');

            // Notification type: document.new, document.signed, vacation.approved, etc.
            $table->string('type', 50);

            // Display content
            $table->string('title');
            $table->text('message')->nullable();

            // Additional data (JSON) - document_id, vacation_id, etc.
            $table->json('data')->nullable();

            // Link to navigate when clicked
            $table->string('action_url')->nullable();

            // Read status
            $table->dateTime('read_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
