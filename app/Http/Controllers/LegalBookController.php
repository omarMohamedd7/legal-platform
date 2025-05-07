<?php

namespace App\Http\Controllers;

use App\Models\LegalBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegalBookController extends Controller
{
    /**
     * Display a listing of legal books.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $books = LegalBook::orderBy('created_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'meta' => [
                    'current_page' => $books->currentPage(),
                    'last_page' => $books->lastPage(),
                    'per_page' => $books->perPage(),
                    'total' => $books->total(),
                ],
                'links' => [
                    'prev' => $books->previousPageUrl(),
                    'next' => $books->nextPageUrl(),
                ],
                'data' => $books->items(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to retrieve legal books: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified legal book.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $book = LegalBook::find($id);
            
            if (!$book) {
                return response()->json([
                    'error' => true,
                    'message' => 'Legal book not found',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $book,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to retrieve legal book: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the specified legal book file.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function download($id)
    {
        try {
            $book = LegalBook::find($id);
            
            if (!$book) {
                return response()->json([
                    'error' => true,
                    'message' => 'Legal book not found',
                ], 404);
            }
            
            $filePath = storage_path('app/public/' . $book->file_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Book file not found on server',
                ], 404);
            }
            
            // Determine file extension to set proper content type
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $contentType = 'application/octet-stream';
            
            if ($extension === 'pdf') {
                $contentType = 'application/pdf';
            } elseif ($extension === 'docx') {
                $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
            
            return response()->download($filePath, basename($book->file_path), [
                'Content-Type' => $contentType,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to download book: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get categories of legal books.
     *
     * @return \Illuminate\Http\Response
     */
    public function categories()
    {
        try {
            $categories = LegalBook::select('category')
                ->distinct()
                ->whereNotNull('category')
                ->pluck('category');
            
            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to retrieve book categories: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get books by category.
     *
     * @param  string  $category
     * @return \Illuminate\Http\Response
     */
    public function byCategory($category)
    {
        try {
            $books = LegalBook::where('category', $category)
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            if ($books->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No books found in this category',
                    'data' => [],
                ]);
            }
            
            return response()->json([
                'success' => true,
                'meta' => [
                    'current_page' => $books->currentPage(),
                    'last_page' => $books->lastPage(),
                    'per_page' => $books->perPage(),
                    'total' => $books->total(),
                ],
                'links' => [
                    'prev' => $books->previousPageUrl(),
                    'next' => $books->nextPageUrl(),
                ],
                'data' => $books->items(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to retrieve books by category: ' . $e->getMessage(),
            ], 500);
        }
    }
} 