<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Lawyer;
use App\Models\Judge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create clients
        $this->createClients();
        
        // Create lawyers
        $this->createLawyers();
        
        // Create judges
        $this->createJudges();
    }
    
    /**
     * Create client users
     */
    private function createClients(): void
    {
        $clients = [
            [
                'name' => 'Ahmed Client',
                'email' => 'ahmed@client.com',
                'password' => 'password',
                'city' => 'Riyadh',
                'phone_number' => '0501234567',
            ],
            [
                'name' => 'Mohammed Client',
                'email' => 'mohammed@client.com',
                'password' => 'password',
                'city' => 'Jeddah',
                'phone_number' => '0551234567',
            ],
            [
                'name' => 'Sara Client',
                'email' => 'sara@client.com',
                'password' => 'password',
                'city' => 'Dammam',
                'phone_number' => '0561234567',
            ],
        ];
        
        foreach ($clients as $clientData) {
            $user = User::create([
                'name' => $clientData['name'],
                'email' => $clientData['email'],
                'password' => Hash::make($clientData['password']),
                'role' => 'client',
            ]);
            
            Client::create([
                'user_id' => $user->id,
                'city' => $clientData['city'],
                'phone_number' => $clientData['phone_number'],
            ]);
        }
    }
    
    /**
     * Create lawyer users
     */
    private function createLawyers(): void
    {
        $lawyers = [
            [
                'name' => 'Fahad Lawyer',
                'email' => 'fahad@lawyer.com',
                'password' => 'password',
                'specialization' => 'Family Law',
                'city' => 'Riyadh',
                'phone_number' => '0501234568',
                'bio' => 'Experienced family law attorney with 5 years of practice',
                'consult_fee' => 250.00,
            ],
            [
                'name' => 'Ali Lawyer',
                'email' => 'ali@lawyer.com',
                'password' => 'password',
                'specialization' => 'Criminal Law',
                'city' => 'Jeddah',
                'phone_number' => '0551234568',
                'bio' => 'Specialized in criminal defense with a high success rate',
                'consult_fee' => 300.00,
            ],
            [
                'name' => 'Nora Lawyer',
                'email' => 'nora@lawyer.com',
                'password' => 'password',
                'specialization' => 'Civil Law',
                'city' => 'Dammam',
                'phone_number' => '0561234568',
                'bio' => 'Expert in civil litigation and dispute resolution',
                'consult_fee' => 275.00,
            ],
            [
                'name' => 'Khalid Lawyer',
                'email' => 'khalid@lawyer.com',
                'password' => 'password',
                'specialization' => 'Commercial Law',
                'city' => 'Riyadh',
                'phone_number' => '0501234569',
                'bio' => 'Specialized in corporate and business law matters',
                'consult_fee' => 350.00,
            ],
        ];
        
        foreach ($lawyers as $lawyerData) {
            $user = User::create([
                'name' => $lawyerData['name'],
                'email' => $lawyerData['email'],
                'password' => Hash::make($lawyerData['password']),
                'role' => 'lawyer',
            ]);
            
            Lawyer::create([
                'user_id' => $user->id,
                'specialization' => $lawyerData['specialization'],
                'city' => $lawyerData['city'],
                'phone_number' => $lawyerData['phone_number'],
                'bio' => $lawyerData['bio'],
                'consult_fee' => $lawyerData['consult_fee'],
            ]);
        }
    }
    
    /**
     * Create judge users
     */
    private function createJudges(): void
    {
        $judges = [
            [
                'name' => 'Ibrahim Judge',
                'email' => 'ibrahim@judge.com',
                'password' => 'password',
                'court_name' => 'Riyadh Court',
                'specialization' => 'Family Law',
            ],
            [
                'name' => 'Omar Judge',
                'email' => 'omar@judge.com',
                'password' => 'password',
                'court_name' => 'Jeddah Court',
                'specialization' => 'Criminal Law',
            ],
        ];
        
        foreach ($judges as $judgeData) {
            $user = User::create([
                'name' => $judgeData['name'],
                'email' => $judgeData['email'],
                'password' => Hash::make($judgeData['password']),
                'role' => 'judge',
            ]);
            
            Judge::create([
                'user_id' => $user->id,
                'court_name' => $judgeData['court_name'],
                'specialization' => $judgeData['specialization'],
            ]);
        }
    }
}
