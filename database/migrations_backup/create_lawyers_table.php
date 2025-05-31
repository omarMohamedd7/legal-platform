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
    Schema::create('lawyers', function (Blueprint $table) {
        $table->id("lawyer_id");
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->string('phone')->nullable();
        $table->enum('specialization', [
            'Family Law',
            'Criminal Law',
            'Civil Law',
            'Commercial Law',
            'International Law',
        ]);    
        $table->string('city')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lawyers');
    }
};
