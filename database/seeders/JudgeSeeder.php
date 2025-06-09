<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Judge;

class JudgeSeeder extends Seeder
{
    public function run(): void
    {
        Judge::insert([
            [
                'user_id' => 5,
                'court_name' => 'Riyadh Court',
                'specialization' => 'Family Law',
            ],
            [
                'user_id' => 6,
                'court_name' => 'Jeddah Court',
                'specialization' => 'Civil Law',
            ],
        ]);
    }
} 