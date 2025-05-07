# Legal Books Library

This feature allows lawyers and judges to browse and download a list of pre-uploaded legal reference books.

## Features

- Browse a list of legal books
- View book details including cover images
- Download books in PDF or DOCX format
- Filter books by category

## Installation

1. Run the setup script:

```bash
setup-legal-books.bat
```

Or manually:

```bash
# Create storage symlink
php artisan storage:link

# Run migrations
php artisan migrate

# Run seeder
php artisan db:seed --class=LegalBookSeeder

# Generate book cover placeholders
php artisan legal-books:generate-covers
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/legal-books` | Get all legal books |
| GET | `/api/legal-books/categories` | Get all book categories |
| GET | `/api/legal-books/category/{category}` | Get books by category |
| GET | `/api/legal-books/{id}` | Get a specific book |
| GET | `/api/legal-books/{id}/download` | Download a book file |

## Response Structure

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Syrian Civil Law Reference",
      "author": "Dr. Ahmed Khalil",
      "category": "Civil Law",
      "description": "A comprehensive reference for Syrian civil law.",
      "file_path": "books/syrian_civil_law.pdf",
      "image_path": "books/covers/civil_law.jpg",
      "created_at": "2023-05-15T10:30:00.000000Z",
      "updated_at": "2023-05-15T10:30:00.000000Z",
      "download_url": "https://yourdomain.com/storage/books/syrian_civil_law.pdf",
      "image_url": "https://yourdomain.com/storage/books/covers/civil_law.jpg"
    }
  ]
}
```

## Adding Real Book Covers

The system generates text-based placeholder files for book covers initially. To use real images:

1. Add your image files to `storage/app/public/books/covers/`
2. Update the database records with correct image paths if needed

## Adding New Books

To add new books to the library:

1. Add the book file to `storage/app/public/books/`
2. Add the book cover image to `storage/app/public/books/covers/`
3. Create a new entry in the `legal_books` table with the appropriate file paths

Example:

```php
use App\Models\LegalBook;

LegalBook::create([
    'title' => 'New Legal Reference',
    'author' => 'Dr. John Smith',
    'category' => 'Administrative Law',
    'description' => 'A new legal reference book.',
    'file_path' => 'books/new_reference.pdf',
    'image_path' => 'books/covers/new_reference.jpg'
]);
``` 