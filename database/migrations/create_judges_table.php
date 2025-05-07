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
        Schema::create('judges', function (Blueprint $table) {
            $table->id("judge_id");
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('specialization', [
                'Family Law',
                'Criminal Law',
                'Civil Law',
                'Commercial Law',
                'International Law',
            ]);    
            $table->string('court_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('judges');
    }
};
