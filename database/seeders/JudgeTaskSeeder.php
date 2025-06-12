<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JudgeTask;

class JudgeTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // NOTE: Update 'judge_id' to match an existing judge in your database
        $judgeId = 1;

        $tasks = [
            [
                'judge_id' => $judgeId,
                'title' => 'Review Case Files',
                'description' => 'Review all case files for tomorrow\'s hearings.',
                'date' => now()->addDay()->format('Y-m-d'),
                'time' => '09:00',
                'status' => 'pending',
            ],
            [
                'judge_id' => $judgeId,
                'title' => 'Court Session',
                'description' => 'Preside over the morning court session.',
                'date' => now()->addDays(2)->format('Y-m-d'),
                'time' => '10:30',
                'status' => 'pending',
            ],
            [
                'judge_id' => $judgeId,
                'title' => 'Meeting with Lawyers',
                'description' => 'Discuss case progress with lawyers.',
                'date' => now()->addDays(3)->format('Y-m-d'),
                'time' => '13:00',
                'status' => 'pending',
            ],
            [
                'judge_id' => $judgeId,
                'title' => 'Sign Judgments',
                'description' => 'Sign off on completed judgments.',
                'date' => now()->addDays(4)->format('Y-m-d'),
                'time' => '15:00',
                'status' => 'pending',
            ],
        ];

        foreach ($tasks as $task) {
            JudgeTask::create($task);
        }
    }
} 