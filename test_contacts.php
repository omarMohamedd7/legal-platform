<?php

// This script tests the contacts functionality
// Run with: php test_contacts.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Import models
use App\Models\User;
use App\Models\Contact;
use App\Models\Client;
use App\Models\Lawyer;

// Test client user
$clientEmail = 'ahmed@client.com';
$client = User::where('email', $clientEmail)->first();

if (!$client) {
    echo "Client user not found with email: $clientEmail\n";
    exit(1);
}

// Test lawyer user
$lawyerEmail = 'fahad@lawyer.com';
$lawyer = User::where('email', $lawyerEmail)->first();

if (!$lawyer) {
    echo "Lawyer user not found with email: $lawyerEmail\n";
    exit(1);
}

echo "Testing contacts for client: {$client->name} (ID: {$client->id})\n";
echo "Testing contacts for lawyer: {$lawyer->name} (ID: {$lawyer->id})\n\n";

// Check if client has contacts
$clientContacts = Contact::where('user_id', $client->id)->get();
echo "Client has " . $clientContacts->count() . " contacts:\n";
foreach ($clientContacts as $contact) {
    $contactUser = User::find($contact->contact_user_id);
    $role = '';
    
    if (Lawyer::where('user_id', $contactUser->id)->exists()) {
        $role = 'Lawyer';
    } elseif (Client::where('user_id', $contactUser->id)->exists()) {
        $role = 'Client';
    }
    
    echo "- {$contactUser->name} ({$role}, ID: {$contactUser->id})\n";
}

echo "\n";

// Check if lawyer has contacts
$lawyerContacts = Contact::where('user_id', $lawyer->id)->get();
echo "Lawyer has " . $lawyerContacts->count() . " contacts:\n";
foreach ($lawyerContacts as $contact) {
    $contactUser = User::find($contact->contact_user_id);
    $role = '';
    
    if (Lawyer::where('user_id', $contactUser->id)->exists()) {
        $role = 'Lawyer';
    } elseif (Client::where('user_id', $contactUser->id)->exists()) {
        $role = 'Client';
    }
    
    echo "- {$contactUser->name} ({$role}, ID: {$contactUser->id})\n";
}

echo "\n";

// Check all contacts in the system
$totalContacts = Contact::count();
echo "Total contacts in system: $totalContacts\n";

// Count lawyers and clients
$lawyerCount = User::whereHas('lawyer')->count();
$clientCount = User::whereHas('client')->count();
echo "Total lawyers: $lawyerCount\n";
echo "Total clients: $clientCount\n";

// Expected number of contacts (each client should have a contact with each lawyer, and vice versa)
$expectedContacts = $lawyerCount * $clientCount * 2; // *2 because contacts are bidirectional
echo "Expected number of contacts: $expectedContacts\n";

if ($totalContacts >= $expectedContacts) {
    echo "\nSUCCESS: All expected contacts have been created!\n";
} else {
    echo "\nWARNING: Some contacts may be missing. Run 'php artisan contacts:initialize-all' to create them.\n";
} 