<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConsultationRequest;

class ConsultationRequestSeeder extends Seeder
{
    public function run(): void
    {
        ConsultationRequest::insert([
            [
                'client_id' => 1,
                'lawyer_id' => 1,
                'price' => 500.00,
                'status' => 'pending',
            ],
            [
                'client_id' => 2,
                'lawyer_id' => 2,
                'price' => 700.00,
                'status' => 'paid',
            ],
        ]);
    }
} 