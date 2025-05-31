<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Lawyer;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id('case_id');
            $table->string('case_number')->unique();
            $table->string('plaintiff_name')->nullable();
            $table->string('defendant_name')->nullable();
            $table->enum('case_type', ['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law']);
            $table->text('description')->nullable();
            $table->enum('status', ['Pending', 'Active', 'Closed'])->default('Pending');
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('assigned_lawyer_id')->nullable();
            $table->foreign('assigned_lawyer_id')->references('lawyer_id')->on('lawyers')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
