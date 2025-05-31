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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->comment('References users.id for clients')->constrained('users')->onDelete('cascade');
            $table->foreignId('lawyer_id')->comment('References users.id for lawyers')->constrained('users')->onDelete('cascade');
            $table->foreignId('consultation_request_id')->nullable()->constrained('consultation_requests')->onDelete('set null');
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            // Create a unique index to prevent duplicate chats between the same client and lawyer
            $table->unique(['client_id', 'lawyer_id', 'consultation_request_id'], 'unique_chat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
}; 