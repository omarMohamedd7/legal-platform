<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('court_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_case_id');
            $table->unsignedBigInteger('lawyer_id');
            $table->string('court_name');
            $table->date('session_date');
            $table->time('session_time');
            $table->text('notes')->nullable();
            $table->enum('status', ['upcoming', 'completed', 'cancelled'])->default('upcoming');
            $table->timestamps();

            // Foreign keys
            $table->foreign('legal_case_id')->references('case_id')->on('cases')->onDelete('cascade');
            $table->foreign('lawyer_id')->references('lawyer_id')->on('lawyers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('court_sessions');
    }
}; 