<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_requests', function (Blueprint $table) {
            $table->id('request_id');
            
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lawyer_id')->constrained('lawyers', 'lawyer_id')->onDelete('cascade');
            $table->foreignId('case_id')->nullable()->constrained('cases', 'case_id')->onDelete('cascade');
            
            $table->text('details')->nullable(); // تفاصيل إضافية للطلب
            $table->enum('status', ['Pending', 'Accepted', 'Rejected'])->default('Pending'); // حالة الطلب
            $table->timestamps();
        });
    }        

    public function down(): void
    {
        Schema::dropIfExists('case_requests');
    }
};
