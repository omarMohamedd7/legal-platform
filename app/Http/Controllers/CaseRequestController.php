<?php

namespace App\Http\Controllers;

use App\Models\CaseRequest;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaseRequestController extends Controller
{
    // إنشاء طلب توكيل جديد
    public function store(Request $request)
    {
        $case = LegalCase::create([
            'case_number' => $request->case_number,
            'case_type' => $request->case_type,
            'plaintiff_name' => $request->plaintiff_name,
            'defendant_name' => $request->defendant_name,
            'description' => $request->description,
            'status' => 'Pending', // الحالة مبدئياً معلقة
            'created_by_id' => Auth::id(),
            'assigned_lawyer_id' => $request->lawyer_id,
        ]);
        
        // ثم إنشاء طلب التوكيل وربطه بالقضية
        $caseRequest = CaseRequest::create([
            'client_id' => Auth::id(),
            'lawyer_id' => $request->lawyer_id,
            'details' => $request->details,
            'case_id' => $case->case_id,
            'status' => 'Pending',
        ]);
        
        return response()->json([
            'message' => 'Case request created successfully.',
            'request' => $caseRequest,
            'case' => $case
        ], 201);
    }

    // محامي يوافق على الطلب
    public function accept($id)
    {
        $caseRequest = CaseRequest::findOrFail($id);
    
        $user = Auth::user();
        if (!$user || $user->role !== 'lawyer' || $user->id !== $caseRequest->lawyer->user_id) {
            return response()->json(['message' => 'Unauthorized. Only the assigned lawyer can accept.'], 403);
        }
    
        if ($caseRequest->status !== 'Pending') {
            return response()->json([
                'message' => 'Cannot accept this request. Only pending requests can be accepted.',
                'current_status' => $caseRequest->status
            ], 400);
        }
    
        $caseRequest->status = 'Accepted';
        $caseRequest->save();
    
        // تفعيل القضية المرتبطة
        if ($caseRequest->case) {
            $caseRequest->case->status = 'Active';
            $caseRequest->case->save();
        }
    
        return response()->json(['message' => 'Case request accepted and case activated.']);
    }

    // محامي يرفض الطلب
    public function reject($id)
    {
        $caseRequest = CaseRequest::findOrFail($id);

        $user = Auth::user();
        if (!$user || $user->role !== 'lawyer' || $user->id !== $caseRequest->lawyer->user_id) {
            return response()->json(['message' => 'Unauthorized. Only the assigned lawyer can reject.'], 403);
        }

        // التحقق من أن الطلب في حالة انتظار فقط
        if ($caseRequest->status !== 'Pending') {
            return response()->json([
                'message' => 'Cannot reject this request. Only pending requests can be rejected.',
                'current_status' => $caseRequest->status
            ], 400);
        }

        $caseRequest->status = 'Rejected';
        $caseRequest->save();

        return response()->json(['message' => 'Case request rejected.']);
    }

    public function getClientRequests()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'client') {
            return response()->json(['message' => 'Unauthorized. Only clients can view their requests.'], 403);
        }

        $client = $user->client;

        if (!$client) {
            return response()->json([
                'message' => 'Client profile not found.',
                'user_id' => $user->id,
                'role' => $user->role
            ], 404);
        }

        $requests = CaseRequest::with(['lawyer.user', 'case'])
            ->where('client_id', $client->client_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($requests);
    }

    public function getLawyerRequests()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'lawyer') {
            return response()->json(['message' => 'Unauthorized. Only lawyers can view their requests.'], 403);
        }

        $lawyer = $user->lawyer;

        if (!$lawyer) {
            return response()->json([
                'message' => 'Lawyer profile not found.',
                'user_id' => $user->id,
                'role' => $user->role
            ], 404);
        }

        $requests = CaseRequest::with(['client.user', 'case'])
            ->where('lawyer_id', $lawyer->lawyer_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($requests);
    }
}