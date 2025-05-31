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
        Schema::create('case_offers', function (Blueprint $table) {
            $table->id('offer_id');
            $table->foreignId('published_case_id')->constrained('published_cases', 'published_case_id')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('lawyers', 'lawyer_id')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['Pending', 'Accepted', 'Rejected'])->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_offers');
    }
}; 