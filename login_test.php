<?php
/**
 * Simple script to test login API
 * 
 * Usage: 
 * 1. Run this script from the command line: php login_test.php
 * 2. Or use a tool like Postman to test the API directly
 */

// API base URL - replace with your actual URL
$baseUrl = 'http://localhost:8000/api';

// User credentials from the seeder
$email = 'ahmed@client.com'; // Client user
// $email = 'fahad@lawyer.com'; // Lawyer user
$password = 'password';

// Login request
$loginData = [
    'email' => $email,
    'password' => $password
];

echo "Attempting to login with email: $email\n";

// Make login request
$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginData));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Process response
$responseData = json_decode($response, true);
echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
print_r($responseData);

// Check if login was successful and we got a token
if ($httpCode === 200 && isset($responseData['data']['token'])) {
    $token = $responseData['data']['token'];
    echo "\nLogin successful! Your access token is:\n$token\n";
    echo "\nUse this token in the Authorization header for subsequent requests:\n";
    echo "Authorization: Bearer $token\n";
    
    // Example: Get user profile with the token
    echo "\nFetching user profile...\n";
    $ch = curl_init("$baseUrl/me");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    
    $profileResponse = curl_exec($ch);
    $profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Profile HTTP Status Code: $profileHttpCode\n";
    echo "Profile Response:\n";
    print_r(json_decode($profileResponse, true));
    
    // Example: Get chat contacts with the token
    echo "\nFetching chat contacts...\n";
    $ch = curl_init("$baseUrl/contacts");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    
    $contactsResponse = curl_exec($ch);
    $contactsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Contacts HTTP Status Code: $contactsHttpCode\n";
    echo "Contacts Response:\n";
    print_r(json_decode($contactsResponse, true));
} else if (isset($responseData['requires_verification']) && $responseData['requires_verification'] === true) {
    echo "\nEmail verification required. An OTP has been sent to your email.\n";
    echo "To verify your OTP, make a POST request to $baseUrl/login/verify-otp with:\n";
    echo "- email: $email\n";
    echo "- otp: [the OTP code from your email]\n";
} else {
    echo "\nLogin failed. Please check your credentials.\n";
} 