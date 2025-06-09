<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CaseOffer;

class CaseOfferSeeder extends Seeder
{
    public function run(): void
    {
        CaseOffer::insert([
            [
                'published_case_id' => 1,
                'lawyer_id' => 1,
                'message' => 'I can help you with this case.',
                'expected_price' => 1500.00,
                'status' => 'Pending',
            ],
            [
                'published_case_id' => 2,
                'lawyer_id' => 2,
                'message' => 'Experienced in similar cases.',
                'expected_price' => 2000.00,
                'status' => 'Accepted',
            ],
        ]);
    }
} 