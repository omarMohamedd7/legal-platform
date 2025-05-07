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
        Schema::create('published_cases', function (Blueprint $table) {
            $table->id('published_case_id');
            $table->foreignId('case_id')->constrained('cases', 'case_id')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->enum('status', ['Active', 'Closed'])->default('Active');
            $table->string('target_city')->nullable();
            $table->enum('target_specialization', ['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('published_cases');
    }
};
