<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CaseRequest;

class CaseRequestSeeder extends Seeder
{
    public function run(): void
    {
        CaseRequest::insert([
            [
                'client_id' => 1,
                'lawyer_id' => 1,
                'case_id' => 1,
                'status' => 'Pending',
            ],
            [
                'client_id' => 2,
                'lawyer_id' => 2,
                'case_id' => 2,
                'status' => 'Accepted',
            ],
        ]);
    }
} 