<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // أول شيء نغير نوع العمود case_type ليسمح بالقيم الجديدة
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('case_type', [
                'Family Law',
                'Civil Law',
                'Criminal Law',
                'Commercial Law',
                'International Law'
            ])->change();
        });
    }

    public function down(): void
    {
        // نرجع للقيم القديمة في حالة الرجوع
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('case_type', [
                'Family',
                'Civil',
                'Criminal',
                'Commercial',
                'International'
            ])->change();
        });
    }
};
