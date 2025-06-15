<?php

// This script debugs the contacts functionality
// Run with: php debug_contacts.php

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
use Illuminate\Support\Facades\Auth;

// Test with a specific user
$testEmail = 'ahmed@client.com'; // Change this to test different users
$user = User::where('email', $testEmail)->first();

if (!$user) {
    echo "User not found with email: $testEmail\n";
    exit(1);
}

echo "Testing for user: {$user->name} (ID: {$user->id})\n\n";

// Check if user is a client or lawyer
$isClient = Client::where('user_id', $user->id)->exists();
$isLawyer = Lawyer::where('user_id', $user->id)->exists();

echo "User role: " . ($isClient ? "Client" : ($isLawyer ? "Lawyer" : "Unknown")) . "\n\n";

if (!$isClient && !$isLawyer) {
    echo "ERROR: Only clients and lawyers can use the chat feature\n";
    exit(1);
}

// Debug the contact lookup
echo "DEBUG: Contacts query\n";
echo "SELECT * FROM contacts WHERE user_id = {$user->id}\n\n";

// Get all contacts where the user is the user
$contacts = Contact::where('user_id', $user->id)->get();
echo "Raw contacts count: " . $contacts->count() . "\n";

if ($contacts->count() == 0) {
    echo "WARNING: No contacts found for this user!\n";
    echo "Try running: php artisan contacts:initialize-all\n";
    exit(1);
}

// Check if contactUser relation is working
echo "\nChecking contactUser relation:\n";
foreach ($contacts as $contact) {
    $contactUser = User::find($contact->contact_user_id);
    echo "Contact ID: {$contact->id}, User ID: {$contact->user_id}, Contact User ID: {$contact->contact_user_id}\n";
    
    if ($contactUser) {
        echo "  Contact user found: {$contactUser->name} ({$contactUser->email})\n";
        
        // Check if the contact user has a client or lawyer record
        $isContactClient = Client::where('user_id', $contactUser->id)->exists();
        $isContactLawyer = Lawyer::where('user_id', $contactUser->id)->exists();
        
        echo "  Contact user role: " . ($isContactClient ? "Client" : ($isContactLawyer ? "Lawyer" : "Unknown")) . "\n";
    } else {
        echo "  ERROR: Contact user not found for ID: {$contact->contact_user_id}\n";
    }
    
    // Check if last_message_date is set
    echo "  Last message date: " . ($contact->last_message_date ? $contact->last_message_date : "NULL") . "\n";
    
    // Check if the virtual attributes are working
    try {
        echo "  Name attribute: " . $contact->name . "\n";
        echo "  Role attribute: " . $contact->role . "\n";
        echo "  Last message: " . ($contact->last_message ? $contact->last_message->message : "NULL") . "\n";
    } catch (\Exception $e) {
        echo "  ERROR with virtual attributes: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Now try to simulate the getContacts method
echo "Simulating getContacts method:\n";
$simulatedContacts = Contact::where('user_id', $user->id)
    ->with('contactUser')
    ->get()
    ->map(function ($contact) {
        try {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'role' => $contact->role,
                'lastMessageDate' => $contact->last_message_date,
                'lastMessage' => $contact->last_message ? $contact->last_message->message : null
            ];
        } catch (\Exception $e) {
            return [
                'id' => $contact->id,
                'error' => $e->getMessage()
            ];
        }
    });

echo "Simulated contacts result:\n";
print_r($simulatedContacts->toArray());

echo "\nDone debugging contacts.\n"; 