<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use App\Models\Lawyer;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Hash;

class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test clients
        $client1 = User::create([
            'name' => 'Client One',
            'email' => 'client1@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);
        
        $client1->client()->create([
            'phone_number' => '1234567890',
            'city' => 'New York',
        ]);
        
        $client2 = User::create([
            'name' => 'Client Two',
            'email' => 'client2@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);
        
        $client2->client()->create([
            'phone_number' => '0987654321',
            'city' => 'Los Angeles',
        ]);
        
        // Create test lawyers
        $lawyer1 = User::create([
            'name' => 'Lawyer One',
            'email' => 'lawyer1@example.com',
            'password' => Hash::make('password'),
            'role' => 'lawyer',
        ]);
        
        $lawyer1->lawyer()->create([
            'phone_number' => '1122334455',
            'city' => 'New York',
            'specialization' => 'Criminal Law',
            'years_of_experience' => 5,
            'bio' => 'Experienced criminal lawyer',
        ]);
        
        $lawyer2 = User::create([
            'name' => 'Lawyer Two',
            'email' => 'lawyer2@example.com',
            'password' => Hash::make('password'),
            'role' => 'lawyer',
        ]);
        
        $lawyer2->lawyer()->create([
            'phone_number' => '5566778899',
            'city' => 'Los Angeles',
            'specialization' => 'Family Law',
            'years_of_experience' => 8,
            'bio' => 'Specialized in family law cases',
        ]);
        
        // Create contacts between clients and lawyers
        // Client 1 - Lawyer 1
        $contact1 = Contact::create([
            'user_id' => $client1->id,
            'contact_user_id' => $lawyer1->id,
            'last_message_date' => now(),
        ]);
        
        $contact2 = Contact::create([
            'user_id' => $lawyer1->id,
            'contact_user_id' => $client1->id,
            'last_message_date' => now(),
        ]);
        
        // Client 1 - Lawyer 2
        $contact3 = Contact::create([
            'user_id' => $client1->id,
            'contact_user_id' => $lawyer2->id,
            'last_message_date' => now(),
        ]);
        
        $contact4 = Contact::create([
            'user_id' => $lawyer2->id,
            'contact_user_id' => $client1->id,
            'last_message_date' => now(),
        ]);
        
        // Client 2 - Lawyer 1
        $contact5 = Contact::create([
            'user_id' => $client2->id,
            'contact_user_id' => $lawyer1->id,
            'last_message_date' => now(),
        ]);
        
        $contact6 = Contact::create([
            'user_id' => $lawyer1->id,
            'contact_user_id' => $client2->id,
            'last_message_date' => now(),
        ]);
        
        // Create sample messages
        // Client 1 - Lawyer 1 conversation
        Message::create([
            'sender_id' => $client1->id,
            'receiver_id' => $lawyer1->id,
            'message' => 'Hello, I need legal advice regarding a contract dispute.',
            'created_at' => now()->subHours(2),
        ]);
        
        Message::create([
            'sender_id' => $lawyer1->id,
            'receiver_id' => $client1->id,
            'message' => 'Hi there! I would be happy to help. Can you provide more details about the dispute?',
            'created_at' => now()->subHours(1)->subMinutes(45),
        ]);
        
        Message::create([
            'sender_id' => $client1->id,
            'receiver_id' => $lawyer1->id,
            'message' => 'It\'s about a construction contract. The contractor didn\'t complete the work as agreed.',
            'created_at' => now()->subHours(1)->subMinutes(30),
        ]);
        
        Message::create([
            'sender_id' => $lawyer1->id,
            'receiver_id' => $client1->id,
            'message' => 'I see. Do you have a copy of the contract that we can review together?',
            'created_at' => now()->subHours(1),
        ]);
        
        // Client 1 - Lawyer 2 conversation
        Message::create([
            'sender_id' => $client1->id,
            'receiver_id' => $lawyer2->id,
            'message' => 'Hi, I\'m looking for advice on a family matter.',
            'created_at' => now()->subDays(1)->subHours(3),
        ]);
        
        Message::create([
            'sender_id' => $lawyer2->id,
            'receiver_id' => $client1->id,
            'message' => 'Hello! I specialize in family law. How can I assist you today?',
            'created_at' => now()->subDays(1)->subHours(2),
        ]);
        
        // Client 2 - Lawyer 1 conversation
        Message::create([
            'sender_id' => $client2->id,
            'receiver_id' => $lawyer1->id,
            'message' => 'Good morning, I need consultation regarding a potential criminal case.',
            'created_at' => now()->subDays(2),
        ]);
        
        Message::create([
            'sender_id' => $lawyer1->id,
            'receiver_id' => $client2->id,
            'message' => 'Good morning. I\'d be happy to discuss your case. Would you prefer to set up a call or continue chatting here?',
            'created_at' => now()->subDays(2)->addHours(1),
        ]);
        
        Message::create([
            'sender_id' => $client2->id,
            'receiver_id' => $lawyer1->id,
            'message' => 'Let\'s continue here for now. It\'s about a false accusation of property damage.',
            'created_at' => now()->subDays(2)->addHours(2),
        ]);
        
        $this->command->info('Chat test data seeded successfully!');
    }
}
