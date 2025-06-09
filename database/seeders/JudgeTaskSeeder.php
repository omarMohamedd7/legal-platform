<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JudgeTask;

class JudgeTaskSeeder extends Seeder
{
    public function run(): void
    {
        JudgeTask::insert([
            [
                'judge_id' => 1,
                'title' => 'Review Case C-1001',
                'description' => 'Review all documents for case C-1001',
                'date' => '2024-06-05',
                'time' => '09:00:00',
                'task_type' => 'Review',
                'reminder_enabled' => true,
                'status' => 'pending',
            ],
            [
                'judge_id' => 2,
                'title' => 'Court Session',
                'description' => 'Attend court session for case C-1002',
                'date' => '2024-06-12',
                'time' => '11:00:00',
                'task_type' => 'Session',
                'reminder_enabled' => false,
                'status' => 'completed',
            ],
        ]);
    }
} 