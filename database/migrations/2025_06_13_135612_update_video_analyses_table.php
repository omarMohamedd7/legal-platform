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
        Schema::table('video_analyses', function (Blueprint $table) {
            // Add new columns for AI analysis results
            $table->string('video_name')->nullable()->after('file_path');
            $table->integer('duration')->nullable()->after('video_name')->comment('Duration in seconds');
            $table->timestamp('analysis_date')->nullable()->after('duration');
            $table->string('prediction')->nullable()->after('analysis_date')->comment('AI prediction result');
            $table->decimal('confidence', 5, 2)->nullable()->after('prediction')->comment('Confidence score (0-100%)');
            
            // Rename existing columns to better match our needs
            $table->renameColumn('result', 'summary');
            
            // Drop unused columns if any
            if (Schema::hasColumn('video_analyses', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_analyses', function (Blueprint $table) {
            // Revert column name changes
            $table->renameColumn('summary', 'result');
            
            // Add back status column
            $table->enum('status', ['Pending', 'Processing', 'Completed'])->default('Pending');
            
            // Remove added columns
            $table->dropColumn([
                'video_name',
                'duration',
                'analysis_date',
                'prediction',
                'confidence'
            ]);
        });
    }
};
