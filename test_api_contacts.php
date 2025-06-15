<?php

// This script tests the API response for the contacts endpoint
// Run with: php test_api_contacts.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Import models and classes
use App\Models\User;
use App\Models\Contact;
use App\Models\Client;
use App\Models\Lawyer;
use App\Http\Controllers\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Test with a specific user
$testEmail = 'ahmed@client.com'; // Change this to test different users
$user = User::where('email', $testEmail)->first();

if (!$user) {
    echo "User not found with email: $testEmail\n";
    exit(1);
}

echo "Testing API response for user: {$user->name} (ID: {$user->id})\n\n";

// Login as the user
Auth::login($user);

// Create an instance of the ChatController
$chatController = app()->make(ChatController::class);

// Call the getContacts method directly
$response = $chatController->getContacts();

// Get the response content
$content = json_decode($response->getContent(), true);

echo "Raw API Response:\n";
echo json_encode($content, JSON_PRETTY_PRINT) . "\n\n";

// Check if there are contacts
if (isset($content['data']) && is_array($content['data']) && count($content['data']) > 0) {
    echo "Found " . count($content['data']) . " contacts:\n";
    foreach ($content['data'] as $index => $contact) {
        echo ($index + 1) . ". {$contact['name']} (Role: {$contact['role']})\n";
        if (isset($contact['lastMessage']) && $contact['lastMessage']) {
            echo "   Last message: {$contact['lastMessage']}\n";
        } else {
            echo "   No messages yet\n";
        }
    }
} else {
    echo "No contacts found in the API response.\n";
    
    // Debug: Check if there are contacts in the database
    $dbContacts = Contact::where('user_id', $user->id)->get();
    echo "\nDatabase check: Found " . $dbContacts->count() . " contacts in the database.\n";
    
    foreach ($dbContacts as $contact) {
        $contactUser = User::find($contact->contact_user_id);
        echo "- Contact ID: {$contact->id}, User: " . ($contactUser ? $contactUser->name : "Unknown") . "\n";
    }
}

echo "\nDone testing API response.\n"; 