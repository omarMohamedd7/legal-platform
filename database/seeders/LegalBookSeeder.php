<?php

namespace Database\Seeders;

use App\Models\LegalBook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class LegalBookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ensure the books directory exists in storage
        Storage::disk('public')->makeDirectory('books');
        Storage::disk('public')->makeDirectory('books/covers');

        // Sample books data
        $books = [
            [
                'title' => 'Syrian Civil Law Reference',
                'author' => 'Dr. Ahmed Khalil',
                'category' => 'Civil Law',
                'description' => 'A comprehensive reference for Syrian civil law.',
                'file_path' => 'books/syrian_civil_law.pdf',
                'image_path' => 'books/covers/civil_law.jpg'
            ],
            [
                'title' => 'Criminal Law Basics',
                'author' => 'Dr. Hani Al-Khatib',
                'category' => 'Criminal Law',
                'description' => 'Introductory guide to criminal law procedures.',
                'file_path' => 'books/criminal_law_basics.pdf',
                'image_path' => 'books/covers/criminal_law.jpg'
            ],
            [
                'title' => 'Family Law in Syria',
                'author' => 'Dr. Leila Mansour',
                'category' => 'Family Law',
                'description' => 'A detailed overview of family law and related cases.',
                'file_path' => 'books/family_law_syria.pdf',
                'image_path' => 'books/covers/family_law.jpg'
            ],
            [
                'title' => 'Commercial Law Principles',
                'author' => 'Dr. Mahmoud Zaki',
                'category' => 'Commercial Law',
                'description' => 'Essential principles of commercial and business law.',
                'file_path' => 'books/commercial_law_principles.pdf',
                'image_path' => 'books/covers/commercial_law.jpg'
            ],
            [
                'title' => 'Constitutional Law Fundamentals',
                'author' => 'Dr. Omar Haddad',
                'category' => 'Constitutional Law',
                'description' => 'An in-depth study of constitutional law.',
                'file_path' => 'books/constitutional_law.pdf',
                'image_path' => 'books/covers/constitutional_law.jpg'
            ],
            [
                'title' => 'Labor Law Guide',
                'author' => 'Dr. Samira Al-Abed',
                'category' => 'Labor Law',
                'description' => 'Comprehensive guide to labor laws and employer-employee relations.',
                'file_path' => 'books/labor_law_guide.docx',
                'image_path' => 'books/covers/labor_law.jpg'
            ],
            [
                'title' => 'Administrative Law Handbook',
                'author' => 'Dr. Faisal Al-Jabri',
                'category' => 'Administrative Law',
                'description' => 'Handbook for understanding administrative law and procedures.',
                'file_path' => 'books/administrative_law_handbook.pdf',
                'image_path' => 'books/covers/administrative_law.jpg'
            ],
        ];

        // Insert books into database
        foreach ($books as $book) {
            // Create sample placeholder files if they don't exist (for development)
            if (!Storage::disk('public')->exists($book['file_path'])) {
                // Create a simple text file as a placeholder
                Storage::disk('public')->put(
                    $book['file_path'], 
                    "This is a placeholder for {$book['title']} by {$book['author']}.\n\nIn a production environment, this would be the actual {$book['category']} book file."
                );
            }
            
            // Create placeholder cover images if they don't exist
            if (!Storage::disk('public')->exists($book['image_path'])) {
                // Create a placeholder image for the book cover
                // We'll create a text file for now, but in a real environment
                // you would use an actual image file or a library to generate one
                Storage::disk('public')->put(
                    $book['image_path'],
                    "This is a placeholder for the cover image of {$book['title']}.\nIn a production environment, this would be an actual image file."
                );
            }
            
            LegalBook::create($book);
        }
    }
} 