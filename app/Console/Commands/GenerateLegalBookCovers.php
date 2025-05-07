<?php

namespace App\Console\Commands;

use App\Models\LegalBook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateLegalBookCovers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legal-books:generate-covers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate placeholder cover images for legal books';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Generating book cover placeholders...');

        // Get all books
        $books = LegalBook::all();
        
        if ($books->isEmpty()) {
            $this->warn('No books found in the database.');
            return 0;
        }

        // Ensure the covers directory exists
        Storage::disk('public')->makeDirectory('books/covers');

        $count = 0;
        foreach ($books as $book) {
            if (!$book->image_path) {
                continue;
            }

            // Generate a placeholder file for the image
            $placeholderPath = 'books/covers/' . basename($book->image_path);
            
            if (!Storage::disk('public')->exists($placeholderPath)) {
                // Create a placeholder text file
                $placeholderContent = "Book Cover Placeholder\n\n";
                $placeholderContent .= "Title: {$book->title}\n";
                $placeholderContent .= "Author: {$book->author}\n";
                $placeholderContent .= "Category: {$book->category}\n\n";
                $placeholderContent .= "This file serves as a placeholder for the actual book cover image.\n";
                $placeholderContent .= "In a production environment, please replace this with an actual image file.";
                
                Storage::disk('public')->put($placeholderPath, $placeholderContent);
                
                // Update the image path to point to our placeholder
                $book->image_path = $placeholderPath;
                $book->save();
                
                $this->info("Created cover placeholder for: {$book->title}");
                $count++;
            } else {
                $this->info("Placeholder already exists for: {$book->title}");
            }
        }

        $this->info("Generated {$count} book cover placeholders.");
        
        $this->info("\nAdditional steps for image generation:");
        $this->info("1. Install the Intervention/Image package: composer require intervention/image");
        $this->info("2. Replace placeholder files in storage/app/public/books/covers/ with actual image files");
        
        return 0;
    }
} 