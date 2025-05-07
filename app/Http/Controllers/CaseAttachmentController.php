<?php

namespace App\Http\Controllers;

use App\Models\CaseAttachment;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CaseAttachmentController extends Controller
{
    /**
     * Upload and store a new attachment for a specific case.
     *
     * @param Request $request
     * @param int $caseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $caseId)
    {
        // Find the legal case
        $legalCase = LegalCase::findOrFail($caseId);
        
        // Authorization check - only client who created the case or assigned lawyer can upload
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        
        // Check if the user is authorized to upload attachments to this case
        $isAuthorized = false;
        
        if ($user->role === 'client' && $legalCase->created_by_id === $user->id) {
            $isAuthorized = true;
        } elseif ($user->role === 'lawyer' && $legalCase->assigned_lawyer_id === $user->lawyer->lawyer_id) {
            $isAuthorized = true;
        } elseif ($user->role === 'judge') {
            // Judges have full access
            $isAuthorized = true;
        }
        
        if (!$isAuthorized) {
            return response()->json([
                'message' => 'Forbidden. You are not authorized to upload attachments to this case.',
            ], 403);
        }
        
        // Validate the uploaded file
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // 10MB max
        ]);
        
        try {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            
            // Determine file type
            $extension = strtolower($file->getClientOriginalExtension());
            $fileType = 'doc';
            
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $fileType = 'image';
            } elseif ($extension === 'pdf') {
                $fileType = 'pdf';
            }
            
            // Store the file
            $path = $file->store('case_attachments', 'public');
            
            // Create attachment record
            $attachment = CaseAttachment::create([
                'case_id' => $caseId,
                'file_path' => $path,
                'file_type' => $fileType,
                'original_filename' => $originalFilename,
            ]);
            
            return response()->json([
                'message' => 'Attachment uploaded successfully',
                'attachment' => $attachment,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error uploading attachment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all attachments for a specific case.
     *
     * @param int $caseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($caseId)
    {
        // Find the legal case
        $legalCase = LegalCase::findOrFail($caseId);
        
        // Authorization check - only client who created the case or assigned lawyer can view
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        
        // Check if the user is authorized to view attachments for this case
        $isAuthorized = false;
        
        if ($user->role === 'client' && $legalCase->created_by_id === $user->id) {
            $isAuthorized = true;
        } elseif ($user->role === 'lawyer' && $legalCase->assigned_lawyer_id === $user->lawyer->lawyer_id) {
            $isAuthorized = true;
        } elseif ($user->role === 'judge') {
            // Judges have full access
            $isAuthorized = true;
        }
        
        if (!$isAuthorized) {
            return response()->json([
                'message' => 'Forbidden. You are not authorized to view attachments for this case.',
            ], 403);
        }
        
        // Get all attachments for the case
        $attachments = $legalCase->attachments;
        
        // Add full URL to each attachment
        $attachments->transform(function ($attachment) {
            $attachment->url = Storage::url($attachment->file_path);
            return $attachment;
        });
        
        return response()->json($attachments);
    }
} 