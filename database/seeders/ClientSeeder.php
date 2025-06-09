<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::insert([
            [
                'user_id' => 1,
                'phone_number' => '0500000001',
                'city' => 'Riyadh',
            ],
            [
                'user_id' => 2,
                'phone_number' => '0500000002',
                'city' => 'Jeddah',
            ],
        ]);
    }
} 