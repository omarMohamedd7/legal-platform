<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        Payment::insert([
            [
                'consultation_request_id' => 1,
                'amount' => 500.00,
                'payment_method' => 'credit_card',
                'status' => 'successful',
                'transaction_id' => 'TXN1001',
            ],
            [
                'consultation_request_id' => 2,
                'amount' => 700.00,
                'payment_method' => 'paypal',
                'status' => 'pending',
                'transaction_id' => 'TXN1002',
            ],
        ]);
    }
} 