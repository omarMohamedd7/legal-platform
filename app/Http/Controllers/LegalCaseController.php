<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Stmt\Case_;

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
        // التحقق من أن المستخدم هو عميل أو قاضي
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
            ]);

            // Check if assigned lawyer's specialization matches case type
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

            $legalCase = LegalCase::create([
                'case_number' => $validated['case_number'],
                'plaintiff_name' => $validated['plaintiff_name'] ?? null,
                'defendant_name' => $validated['defendant_name'] ?? null,
                'case_type' => $validated['case_type'],
                'description' => $validated['description'] ?? null,
                'status' => 'Pending',
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
        ]);

        // Check if assigned lawyer's specialization matches case type
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

        $case->update($validated);
        return response()->json(['message' => 'Case updated', 'case' => $case]);
    }

    // حذف قضية
    public function destroy($id)
    {
        $case = LegalCase::findOrFail($id);
        $case->delete();
        return response()->json(['message' => 'Case deleted']);
    }
}
