<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LegalCase;

class LegalCaseSeeder extends Seeder
{
    public function run(): void
    {
        LegalCase::insert([
            [
                'case_number' => 'C-1001',
                'plaintiff_name' => 'Ali Ahmad',
                'defendant_name' => 'Sara Ali',
                'case_type' => 'Family Law',
                'description' => 'Divorce case',
                'status' => 'Pending',
                'attachments' => json_encode(['doc1.pdf']),
                'created_by_id' => 1,
                'assigned_lawyer_id' => 1,
            ],
            [
                'case_number' => 'C-1002',
                'plaintiff_name' => 'Mohammed Saleh',
                'defendant_name' => 'Fahad Nasser',
                'case_type' => 'Civil Law',
                'description' => 'Property dispute',
                'status' => 'Active',
                'attachments' => json_encode(['doc2.pdf']),
                'created_by_id' => 2,
                'assigned_lawyer_id' => 2,
            ],
        ]);
    }
} 