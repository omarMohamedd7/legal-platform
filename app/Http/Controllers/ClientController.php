<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    // عرض قائمة العملاء (اختياري)
    public function index()
    {
        $clients = Client::with('user')->get();
        return response()->json($clients);
    }

    // عرض عميل محدد
    public function show($id)
    {
        $client = Client::with('user')->findOrFail($id);
        return response()->json($client);
    }

    // تحديث بيانات عميل (اختياري)
    public function update(Request $request, $id)
    {
        $client = Client::with('user')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $client->user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
        ]);

        // تحديث بيانات المستخدم المرتبط
        $client->user->update([
            'name' => $validated['name'] ?? $client->user->name,
            'email' => $validated['email'] ?? $client->user->email,
        ]);

        // تحديث بيانات الزبون (بما في ذلك المدينة إذا كانت ضمن clients)
        $client->update([
            'phone' => $validated['phone'] ?? $client->phone,
            'city' => $validated['city'] ?? $client->city,
        ]);

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $client->load('user')
        ]);
    }

    // حذف عميل (اختياري)
    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        $client->user->delete(); // حذف المستخدم المرتبط
        $client->delete();       // حذف الزبون
    
        return response()->json(['message' => 'Client and related user deleted']);
    }
    
    /**
     * تحديث الملف الشخصي للعميل المصادق عليه
     * Update authenticated client's own profile with form data support for image upload
     */
    public function updateOwnProfile(Request $request)
    {
            $user = Auth::user();
            
            // التحقق من أن المستخدم هو عميل
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. هذه الخدمة متاحة للعملاء فقط.'], 403);
            }
            
        // Use the common profile update method
        return app(ProfileController::class)->updateProfile($request);
    }

    /**
     * Get all cases for the authenticated client with optional status filtering
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCases(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if authenticated user is a client
            if (!$user || $user->role !== 'client') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. هذه الخدمة متاحة للعملاء فقط.'
                ], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف العميل.'
                ], 404);
            }
            
            // Validate query parameters (optional status filter)
            $validated = $request->validate([
                'status' => 'nullable|in:pending,active,closed',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);
            
            // Get cases created by this client - using the user ID since created_by_id refers to the user ID
            $query = LegalCase::with(['assignedLawyer.user'])
                ->where('created_by_id', $user->id);
            
            // Apply status filter if provided (optional)
            if (isset($validated['status'])) {
                $statusMap = [
                    'pending' => ['Pending'],
                    'active' => ['Active'],
                    'closed' => ['Closed', 'Completed', 'Cancelled', 'Rejected']
                ];
                
                $statuses = $statusMap[$validated['status']];
                $query->whereIn('status', $statuses);
            }
            
            // Get paginated results
            $perPage = $validated['per_page'] ?? 10;
            $cases = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format the response data
            $formattedCases = $cases->map(function($case) {
                return [
                    'case_id' => $case->case_id,
                    'case_number' => $case->case_number,
                    'case_type' => $case->case_type,
                    'plaintiff_name' => $case->plaintiff_name,
                    'defendant_name' => $case->defendant_name,
                    'description' => $case->description,
                    'status' => $case->status,
                    'created_at' => $case->created_at,
                    'updated_at' => $case->updated_at,
                    'lawyer' => $case->assignedLawyer ? [
                        'lawyer_id' => $case->assignedLawyer->lawyer_id,
                        'name' => $case->assignedLawyer->user->name,
                        'specialization' => $case->assignedLawyer->specialization,
                    ] : null,
                    'has_attachments' => !empty($case->attachments)
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedCases,
                'pagination' => [
                    'current_page' => $cases->currentPage(),
                    'last_page' => $cases->lastPage(),
                    'per_page' => $cases->perPage(),
                    'total' => $cases->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving client cases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب قضايا العميل.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active cases for the authenticated client
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveCases(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if authenticated user is a client
            if (!$user || $user->role !== 'client') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. هذه الخدمة متاحة للعملاء فقط.'
                ], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف العميل.'
                ], 404);
            }
            
            // Validate pagination parameter
            $validated = $request->validate([
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);
            
            // Get only active cases created by this client
            $query = LegalCase::with(['assignedLawyer.user'])
                ->where('created_by_id', $user->id)
                ->where('status', 'Active');
            
            // Get paginated results
            $perPage = $validated['per_page'] ?? 10;
            $cases = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format the response data
            $formattedCases = $cases->map(function($case) {
                return [
                    'case_id' => $case->case_id,
                    'case_number' => $case->case_number,
                    'case_type' => $case->case_type,
                    'plaintiff_name' => $case->plaintiff_name,
                    'defendant_name' => $case->defendant_name,
                    'description' => $case->description,
                    'created_at' => $case->created_at,
                    'updated_at' => $case->updated_at,
                    'lawyer' => $case->assignedLawyer ? [
                        'lawyer_id' => $case->assignedLawyer->lawyer_id,
                        'name' => $case->assignedLawyer->user->name,
                        'specialization' => $case->assignedLawyer->specialization,
                    ] : null,
                    'has_attachments' => !empty($case->attachments)
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedCases,
                'pagination' => [
                    'current_page' => $cases->currentPage(),
                    'last_page' => $cases->lastPage(),
                    'per_page' => $cases->perPage(),
                    'total' => $cases->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving client active cases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب القضايا النشطة للعميل.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
