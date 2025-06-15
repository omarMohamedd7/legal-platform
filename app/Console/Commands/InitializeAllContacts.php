<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Console\Command;

class InitializeAllContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:initialize-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize contacts between all clients and lawyers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing contacts between all clients and lawyers...');

        // Get all lawyers and clients
        $lawyers = User::whereHas('lawyer')->get();
        $clients = User::whereHas('client')->get();
        
        $contactsCreated = 0;

        // Create contacts for each client with all lawyers
        foreach ($clients as $client) {
            foreach ($lawyers as $lawyer) {
                $contact = Contact::firstOrCreate([
                    'user_id' => $client->id,
                    'contact_user_id' => $lawyer->id
                ], [
                    'last_message_date' => now()
                ]);
                
                if ($contact->wasRecentlyCreated) {
                    $contactsCreated++;
                }
                
                // Create reverse contact for the lawyer
                $reverseContact = Contact::firstOrCreate([
                    'user_id' => $lawyer->id,
                    'contact_user_id' => $client->id
                ], [
                    'last_message_date' => now()
                ]);
                
                if ($reverseContact->wasRecentlyCreated) {
                    $contactsCreated++;
                }
            }
        }

        $this->info("Successfully initialized contacts. Created $contactsCreated new contacts.");
        
        return 0;
    }
} 