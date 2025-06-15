<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\LegalBookSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\PublishedCaseSeeder;
use Database\Seeders\CaseRequestSeeder;
use Database\Seeders\CaseOfferSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\LawyerSeeder;
use Database\Seeders\JudgeSeeder;
use Database\Seeders\LegalCaseSeeder;
use Database\Seeders\ConsultationRequestSeeder;
use Database\Seeders\PaymentSeeder;
use Database\Seeders\JudgeTaskSeeder;
use Database\Seeders\VideoAnalysisSeeder;
use Database\Seeders\ChatSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ClientSeeder::class,
            LawyerSeeder::class,
            JudgeSeeder::class,
            LegalCaseSeeder::class,
            PublishedCaseSeeder::class,
            CaseRequestSeeder::class,
            CaseOfferSeeder::class,
            ConsultationRequestSeeder::class,
            PaymentSeeder::class,
            LegalBookSeeder::class,
            JudgeTaskSeeder::class,
            VideoAnalysisSeeder::class,
            ChatSeeder::class,
        ]);
    }
}
