<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to normalize the published_cases table
     * by removing duplicated fields that already exist in the cases table.
     */
    public function up(): void
    {
        Schema::table('published_cases', function (Blueprint $table) {
            // Remove duplicated fields that are already in the cases table
            if (Schema::hasColumn('published_cases', 'case_number')) {
                $table->dropColumn('case_number');
            }
            
            if (Schema::hasColumn('published_cases', 'plaintiff_name')) {
                $table->dropColumn('plaintiff_name');
            }
            
            if (Schema::hasColumn('published_cases', 'defendant_name')) {
                $table->dropColumn('defendant_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('published_cases', function (Blueprint $table) {
            // Add back the removed columns
            if (!Schema::hasColumn('published_cases', 'case_number')) {
                $table->string('case_number');
            }
            
            if (!Schema::hasColumn('published_cases', 'plaintiff_name')) {
                $table->string('plaintiff_name')->nullable();
            }
            
            if (!Schema::hasColumn('published_cases', 'defendant_name')) {
                $table->string('defendant_name')->nullable();
            }
        });
    }
};
