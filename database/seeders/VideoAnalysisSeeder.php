<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VideoAnalysis;

class VideoAnalysisSeeder extends Seeder
{
    public function run(): void
    {
        VideoAnalysis::insert([
            [
                'judge_id' => 1,
                'file_path' => 'videos/analysis1.mp4',
                'status' => 'Pending',
                'result' => null,
                'notes' => 'Awaiting review',
            ],
            [
                'judge_id' => 2,
                'file_path' => 'videos/analysis2.mp4',
                'status' => 'Completed',
                'result' => 'Valid evidence',
                'notes' => 'Reviewed and accepted',
            ],
        ]);
    }
} 