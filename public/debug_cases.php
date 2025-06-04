<?php
// This is a temporary debug script - REMOVE BEFORE GOING TO PRODUCTION

// Set up Laravel environment
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get all published cases
use App\Models\PublishedCase;
use Illuminate\Support\Facades\DB;

echo "<h1>Published Cases Debug Information</h1>";
echo "<pre>";

// Check if the table exists
$tables = DB::select('SHOW TABLES');
$tableNames = [];
foreach ($tables as $table) {
    $tableNames[] = array_values((array)$table)[0];
}

echo "Tables in database: " . implode(", ", $tableNames) . "\n\n";

if (!in_array('published_cases', $tableNames)) {
    echo "ERROR: published_cases table does not exist! Please run migrations.\n";
    exit;
}

// Get count of published cases
$count = PublishedCase::count();
echo "Total published cases in the database: {$count}\n\n";

// Get all published cases
$cases = PublishedCase::all();
if ($cases->isEmpty()) {
    echo "No published cases found in the database.\n";
    echo "You need to create at least one published case before you can close it.\n";
} else {
    echo "List of all published cases:\n";
    foreach ($cases as $case) {
        echo "ID: {$case->published_case_id}, Client ID: {$case->client_id}, Status: {$case->status}, Created: {$case->created_at}\n";
    }
}

echo "</pre>";

echo "<h2>How to Fix</h2>";
echo "<ol>";
echo "<li>Make sure you are using a valid published case ID that exists in the database</li>";
echo "<li>Try publishing a new case if there are no cases in the database</li>";
echo "<li>Make sure you are logged in as the client who owns the case you're trying to close</li>";
echo "</ol>"; 