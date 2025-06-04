<?php

namespace App\Http\Controllers;

use App\Models\CaseRequest;
use App\Models\LegalCase;
use App\Models\CaseAttachment;
use App\Models\Lawyer;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CaseRequestController extends Controller
{
    /**
     * Create a direct case request from a client to a specific lawyer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
{
    try {
        $user = Auth::user();

        if (!$user || $user->role !== 'client') {
            return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط إنشاء طلبات قضايا.'], 403);
        }

        $client = $user->client;

        if (!$client) {
            return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
        }

        // Validate input
        $validated = $request->validate([
            'lawyer_id' => 'required|exists:lawyers,lawyer_id',
            'case_number' => 'nullable|string',
            'plaintiff_name' => 'nullable|string|max:255',
            'defendant_name' => 'nullable|string|max:255',
            'description' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        // Get the lawyer
        $lawyer = Lawyer::findOrFail($validated['lawyer_id']);

        // Generate a case number if not provided
        $caseNumber = $validated['case_number'] ?? 'CASE-' . time() . '-' . rand(1000, 9999);

        // Handle single attachment
        $attachment = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('case_attachments', 'public');
            $attachment = [
                'path' => $path,
                'type' => $file->getClientMimeType(),
                'name' => $file->getClientOriginalName(),
                'uploaded_by' => $user->id,
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        // Create the legal case
        $legalCase = LegalCase::create([
            'case_number' => $caseNumber,
            'case_type' => $lawyer->specialization,
            'plaintiff_name' => $validated['plaintiff_name'] ?? null,
            'defendant_name' => $validated['defendant_name'] ?? null,
            'description' => $validated['description'],
            'status' => 'Pending',
            'attachments' => $attachment,
            'created_by_id' => $user->id,
            'assigned_lawyer_id' => $lawyer->lawyer_id,
        ]);

        // Create the case request
        $caseRequest = CaseRequest::create([
            'client_id' => $client->client_id,
            'lawyer_id' => $lawyer->lawyer_id,
            'case_id' => $legalCase->case_id,
            'attachments' => $attachment,
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'تم إنشاء طلب القضية بنجاح.',
            'request' => [
                'request_id' => $caseRequest->request_id,
                'status' => $caseRequest->status,
                'created_at' => $caseRequest->created_at,
                'attachments' => $caseRequest->attachments,
            ],
            'case' => [
                'case_id' => $legalCase->case_id,
                'case_number' => $legalCase->case_number,
                'case_type' => $legalCase->case_type,
                'plaintiff_name' => $legalCase->plaintiff_name,
                'defendant_name' => $legalCase->defendant_name,
                'description' => $legalCase->description,
                'status' => $legalCase->status,
                'attachments' => $legalCase->attachments,
            ],
            'lawyer' => [
                'lawyer_id' => $lawyer->lawyer_id,
                'name' => $lawyer->user->name,
                'specialization' => $lawyer->specialization,
            ],
        ], 201);

    } catch (\Exception $e) {
        Log::error('Error creating case request: ' . $e->getMessage());
        return response()->json([
            'message' => 'حدث خطأ أثناء إنشاء طلب القضية.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Accept a case request (lawyer endpoint)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'lawyer') {
                return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط قبول الطلبات.'], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
            }
            
            $caseRequest = CaseRequest::with('case')->findOrFail($id);
            
            // Verify that this request belongs to the authenticated lawyer
            if ($caseRequest->lawyer_id !== $lawyer->lawyer_id) {
                return response()->json(['message' => 'غير مصرح. يمكنك فقط قبول الطلبات الموجهة إليك.'], 403);
            }
            
            // Verify the request is still pending
            if ($caseRequest->status !== 'Pending') {
                return response()->json([
                    'message' => 'لا يمكن قبول هذا الطلب. يمكن قبول الطلبات المعلقة فقط.',
                    'current_status' => $caseRequest->status
                ], 400);
            }
            
            // Update the request status
            $caseRequest->status = 'Accepted';
            $caseRequest->save();
            
            // Update the associated case status
            if ($caseRequest->case) {
                $caseRequest->case->status = 'Active';
                $caseRequest->case->save();
            }
            
            return response()->json([
                'message' => 'تم قبول طلب القضية وتنشيط القضية.',
                'request' => [
                    'request_id' => $caseRequest->request_id,
                    'status' => $caseRequest->status,
                    'updated_at' => $caseRequest->updated_at,
                ],
                'case' => [
                    'case_id' => $caseRequest->case->case_id,
                    'status' => $caseRequest->case->status,
                    'updated_at' => $caseRequest->case->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error accepting case request: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء قبول طلب القضية.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a case request (lawyer endpoint)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'lawyer') {
                return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط رفض الطلبات.'], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
            }
            
            $caseRequest = CaseRequest::findOrFail($id);
            
            // Verify that this request belongs to the authenticated lawyer
            if ($caseRequest->lawyer_id !== $lawyer->lawyer_id) {
                return response()->json(['message' => 'غير مصرح. يمكنك فقط رفض الطلبات الموجهة إليك.'], 403);
            }
            
            // Verify the request is still pending
            if ($caseRequest->status !== 'Pending') {
                return response()->json([
                    'message' => 'لا يمكن رفض هذا الطلب. يمكن رفض الطلبات المعلقة فقط.',
                    'current_status' => $caseRequest->status
                ], 400);
            }
            
            // Update the request status
            $caseRequest->status = 'Rejected';
            $caseRequest->save();
            
            return response()->json([
                'message' => 'تم رفض طلب القضية.',
                'request' => [
                    'request_id' => $caseRequest->request_id,
                    'status' => $caseRequest->status,
                    'updated_at' => $caseRequest->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting case request: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء رفض طلب القضية.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all case requests for the authenticated client
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientRequests()
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط عرض طلباتهم.'], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
            }
            
            $requests = CaseRequest::with(['lawyer.user', 'case'])
                ->where('client_id', $client->client_id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            // Format the response
            $formattedRequests = $requests->map(function($request) {
                return [
                    'request_id' => $request->request_id,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'case' => $request->case ? [
                        'case_id' => $request->case->case_id,
                        'case_number' => $request->case->case_number,
                        'case_type' => $request->case->case_type,
                        'plaintiff_name' => $request->case->plaintiff_name,
                        'defendant_name' => $request->case->defendant_name,
                        'description' => $request->case->description,
                        'status' => $request->case->status,
                    ] : null,
                    'lawyer' => $request->lawyer ? [
                        'lawyer_id' => $request->lawyer->lawyer_id,
                        'name' => $request->lawyer->user->name,
                        'specialization' => $request->lawyer->specialization,
                    ] : null,
                ];
            });
            
            return response()->json([
                'data' => $formattedRequests,
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving client requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات العميل.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all case requests for the authenticated lawyer
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLawyerRequests()
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'lawyer') {
                return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط عرض طلباتهم.'], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
            }
            
            $requests = CaseRequest::with(['client.user', 'case'])
                ->where('lawyer_id', $lawyer->lawyer_id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            // Format the response
            $formattedRequests = $requests->map(function($request) {
                return [
                    'request_id' => $request->request_id,
                    'status' => $request->status,
                    'created_at' => $request->created_at,
                    'case' => $request->case ? [
                        'case_id' => $request->case->case_id,
                        'case_number' => $request->case->case_number,
                        'case_type' => $request->case->case_type,
                        'plaintiff_name' => $request->case->plaintiff_name,
                        'defendant_name' => $request->case->defendant_name,
                        'description' => $request->case->description,
                        'status' => $request->case->status,
                    ] : null,
                    'client' => $request->client ? [
                        'client_id' => $request->client->client_id,
                        'name' => $request->client->user->name,
                    ] : null,
                ];
            });
            
            return response()->json([
                'data' => $formattedRequests,
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving lawyer requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب طلبات المحامي.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}