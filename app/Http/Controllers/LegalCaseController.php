<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LegalCaseController extends Controller
{
    // عرض جميع القضايا
    public function index()
    {
        $cases = LegalCase::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($cases);
    }

    // إنشاء قضية جديدة
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. You must be logged in.',
            ], 401);
        }

        if (!in_array($user->role, ['client', 'judge'])) {
            return response()->json([
                'message' => 'Forbidden. Only clients and judges can create cases.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'case_number' => 'required|string|unique:cases,case_number',
                'plaintiff_name' => 'nullable|string|max:255',
                'defendant_name' => 'nullable|string|max:255',
                'case_type' => 'required|in:Family Law,Criminal Law,Civil Law,Commercial Law,International Law',
                'description' => 'nullable|string',
                'assigned_lawyer_id' => 'nullable|exists:lawyers,lawyer_id',
                'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            // تحقق من تخصص المحامي
            if (isset($validated['assigned_lawyer_id'])) {
                $lawyer = \App\Models\Lawyer::find($validated['assigned_lawyer_id']);
                if ($lawyer && $lawyer->specialization !== $validated['case_type']) {
                    return response()->json([
                        'message' => 'The assigned lawyer\'s specialization does not match the case type',
                        'lawyer_specialization' => $lawyer->specialization,
                        'case_type' => $validated['case_type']
                    ], 422);
                }
            }

            // رفع المرفق إن وجد
            $attachmentObject = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('case_attachments', 'public');

                $attachmentObject = [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'type' => $file->getClientMimeType(),
                    'name' => $file->getClientOriginalName(),
                    'uploaded_by' => $user->id,
                    'uploaded_at' => now()->toIso8601String(),
                ];
            }

            $legalCase = LegalCase::create([
                'case_number' => $validated['case_number'],
                'plaintiff_name' => $validated['plaintiff_name'] ?? null,
                'defendant_name' => $validated['defendant_name'] ?? null,
                'case_type' => $validated['case_type'],
                'description' => $validated['description'] ?? null,
                'status' => 'Pending',
                'attachments' => $attachmentObject,
                'created_by_id' => $user->id,
                'assigned_lawyer_id' => $validated['assigned_lawyer_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Case created successfully',
                'case' => $legalCase,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating case',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // عرض قضية محددة
    public function show($id)
    {
        $case = LegalCase::findOrFail($id);
        return response()->json($case);
    }

    // تعديل قضية
    public function update(Request $request, $id)
    {
        $case = LegalCase::findOrFail($id);

        $validated = $request->validate([
            'case_number' => 'sometimes|unique:cases,case_number,' . $id,
            'plaintiff_name' => 'nullable|string|max:255',
            'defendant_name' => 'nullable|string|max:255',
            'case_type' => 'in:Family Law,Criminal Law,Civil Law,Commercial Law,International Law',
            'description' => 'nullable|string',
            'status' => 'in:Pending,Active,Closed',
            'assigned_lawyer_id' => 'nullable|exists:lawyers,lawyer_id',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        // تحقق من تخصص المحامي
        if (isset($validated['assigned_lawyer_id'])) {
            $lawyer = \App\Models\Lawyer::find($validated['assigned_lawyer_id']);
            if ($lawyer && $lawyer->specialization !== $validated['case_type']) {
                return response()->json([
                    'message' => 'The assigned lawyer\'s specialization does not match the case type',
                    'lawyer_specialization' => $lawyer->specialization,
                    'case_type' => $validated['case_type']
                ], 422);
            }
        }

        // تعديل المرفق إذا أُرسل
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('case_attachments', 'public');

            $case->attachments = [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'type' => $file->getClientMimeType(),
                'name' => $file->getClientOriginalName(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $case->update($validated);

        return response()->json([
            'message' => 'Case updated',
            'case' => $case
        ]);
    }

    // حذف قضية
    public function destroy($id)
    {
        $case = LegalCase::findOrFail($id);
        $case->delete();
        return response()->json(['message' => 'Case deleted']);
    }

    /**
     * Get all attachments for a case by case ID.
     * Only accessible by the assigned lawyer, the client who created the case, or a judge.
     *
     * @param int $caseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCaseAttachment($caseId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You must be logged in.'
            ], 401);
        }

        $case = \App\Models\LegalCase::findOrFail($caseId);

        $isAuthorized = false;
        if ($user->role === 'client' && $case->created_by_id === $user->id) {
            $isAuthorized = true;
        } elseif ($user->role === 'lawyer' && $case->assigned_lawyer_id === optional($user->lawyer)->lawyer_id) {
            $isAuthorized = true;
        } elseif ($user->role === 'judge') {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You are not authorized to view this attachment.'
            ], 403);
        }

        $attachments = $case->attachments ?? [];
        if (!is_array($attachments) || empty($attachments)) {
            return response()->json([
                'success' => false,
                'message' => 'No attachments found.'
            ], 404);
        }

        // Add file URL to each attachment
        $attachments = array_map(function ($attachment) {
            if (isset($attachment['file_path'])) {
                $attachment['url'] = Storage::url($attachment['file_path']);
            }
            return $attachment;
        }, $attachments);

        return response()->json([
            'success' => true,
            'attachments' => $attachments
        ]);
    }
}