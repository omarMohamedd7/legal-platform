<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lawyers', function (Blueprint $table) {
            // First set any NULL values to a default value (0) to avoid SQL errors
            DB::statement('UPDATE lawyers SET consult_fee = 0 WHERE consult_fee IS NULL');
            
            // Then change the column to be non-nullable
            $table->decimal('consult_fee', 10, 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lawyers', function (Blueprint $table) {
            $table->decimal('consult_fee', 10, 2)->nullable()->change();
        });
    }
}; 