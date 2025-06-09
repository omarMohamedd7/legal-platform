<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PublishedCase;

class PublishedCaseSeeder extends Seeder
{
    public function run(): void
    {
        PublishedCase::insert([
            [
                'case_id' => 1,
                'client_id' => 1,
                'status' => 'Active',
                'target_city' => 'Riyadh',
                'target_specialization' => 'Family Law',
            ],
            [
                'case_id' => 2,
                'client_id' => 2,
                'status' => 'Closed',
                'target_city' => 'Jeddah',
                'target_specialization' => 'Civil Law',
            ],
        ]);
    }
} 