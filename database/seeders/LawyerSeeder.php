<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lawyer;

class LawyerSeeder extends Seeder
{
    public function run(): void
    {
        Lawyer::insert([
            [
                'user_id' => 3,
                'phone_number' => '0500000003',
                'specialization' => 'Family Law',
                'city' => 'Riyadh',
                'consult_fee' => 1000.00,
                'bio' => 'Experienced family law attorney with over 10 years of practice. Specializing in divorce, child custody, and marriage contracts.',
            ],
            [
                'user_id' => 4,
                'phone_number' => '0500000004',
                'specialization' => 'Civil Law',
                'city' => 'Jeddah',
                'consult_fee' => 1200.00,
                'bio' => 'Civil law specialist with expertise in property disputes, contracts, and commercial agreements. Graduate of King Saud University Law School.',
            ],
        ]);
    }
} 