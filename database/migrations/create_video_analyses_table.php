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
        Schema::create('video_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('judge_id');
            $table->string('file_path');
            $table->enum('status', ['Pending', 'Processing', 'Completed'])->default('Pending');
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('judge_id')->references('judge_id')->on('judges')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('video_analyses');
    }
}; 