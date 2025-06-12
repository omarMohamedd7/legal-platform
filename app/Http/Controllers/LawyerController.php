<?php

namespace App\Http\Controllers;

use App\Models\Lawyer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LawyerController extends Controller
{
                         

    // ✅ عرض كل المحامين
    public function index()
    {
        $lawyers = Lawyer::with('user')->get();
        return response()->json($lawyers);
    }

    // ✅ عرض محامي محدد
    public function show($id)
    {
        $lawyer = Lawyer::with('user')->findOrFail($id);
        return response()->json($lawyer);
    }

    // ✅ تعديل محامي
    public function update(Request $request, $id)
    {
        $lawyer = Lawyer::with('user')->findOrFail($id);
    
        // تحقق من صحة البيانات
        $validated = $request->validate([
            'specialization' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'consult_fee' => 'nullable|numeric|min:0',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $lawyer->user->id,
            'profile_image_url' => 'nullable|string|max:255', // Updated field name for consistency
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Added for image upload
        ]);
    
        // تحديث جدول lawyers
        $lawyer->update([
            'specialization' => $validated['specialization'] ?? $lawyer->specialization,
            'phone' => $validated['phone'] ?? $lawyer->phone,
            'city' => $validated['city'] ?? $lawyer->city,
            'consult_fee' => $validated['consult_fee'] ?? $lawyer->consult_fee,
        ]);
        
        // Handle profile image upload if provided
        $profileImageUrl = null;
        if ($request->hasFile('profile_picture')) {
            $image = $request->file('profile_picture');
            $filename = time() . '.' . $image->getClientOriginalExtension();
            
            // Ensure directory exists
            $uploadPath = public_path('uploads/profile_images');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $image->move($uploadPath, $filename);
            $profileImageUrl = 'uploads/profile_images/' . $filename;
            
            // Update user with new profile image URL
            $lawyer->user->profile_image_url = $profileImageUrl;
        } elseif (isset($validated['profile_image_url'])) {
            $lawyer->user->profile_image_url = $validated['profile_image_url'];
        }
    
        // تحديث جدول users المرتبط
        $lawyer->user->update([
            'name' => $validated['name'] ?? $lawyer->user->name,
            'email' => $validated['email'] ?? $lawyer->user->email,
        ]);
    
        return response()->json([
            'message' => 'Lawyer updated successfully',
            'lawyer' => $lawyer->load('user')
        ]);
    }
    

    // ✅ حذف محامي
    public function destroy($id)
{
    $lawyer = Lawyer::findOrFail($id);
    $lawyer->user->delete(); // حذف المستخدم المرتبط
    $lawyer->delete();       // حذف المحامي

    return response()->json(['message' => 'Lawyer and related user deleted']);
}

    /**
     * تحديث الملف الشخصي للمحامي المصادق عليه
     * Update authenticated lawyer's own profile with form data support for image upload
     */
    public function updateOwnProfile(Request $request)
    {
            $user = Auth::user();
            
            // التحقق من أن المستخدم هو محامي
            if (!$user || $user->role !== 'lawyer') {
                return response()->json(['message' => 'غير مصرح. هذه الخدمة متاحة للمحامين فقط.'], 403);
            }
            
        // Use the common profile update method
        return app(ProfileController::class)->updateProfile($request);
    }

    /**
     * Get lawyers in the same city as the authenticated client
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLawyersInClientCity(Request $request)
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
            
            if (!$client->city) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم تحديد مدينة للعميل. يرجى تحديث الملف الشخصي.'
                ], 400);
            }
            
            // Get lawyers in the same city as the client
            $lawyers = Lawyer::with(['user' => function($query) {
                $query->select('id', 'name', 'email', 'profile_image_url', 'role');
            }])
            ->where('city', $client->city)
            ->paginate(10);
            
            // Format the response data
            $formattedLawyers = $lawyers->map(function($lawyer) {
                return [
                    'lawyer_id' => $lawyer->lawyer_id,
                    'name' => $lawyer->user->name,
                    'email' => $lawyer->user->email,
                    'specialization' => $lawyer->specialization,
                    'city' => $lawyer->city,
                    'consult_fee' => $lawyer->consult_fee,
                    'profile_image_url' => $lawyer->user->profile_image_url ? url($lawyer->user->profile_image_url) : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedLawyers,
                'pagination' => [
                    'current_page' => $lawyers->currentPage(),
                    'last_page' => $lawyers->lastPage(),
                    'per_page' => $lawyers->perPage(),
                    'total' => $lawyers->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting lawyers in client city: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المحامين.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all lawyers with optional filtering by city and specialization
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllLawyers(Request $request)
    {
        try {
            // Validate input parameters
            $validated = $request->validate([
                'city' => 'nullable|string',
                'specialization' => 'nullable|string',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);
            
            // Start building the query
            $query = Lawyer::with(['user' => function($query) {
                $query->select('id', 'name', 'email', 'profile_image_url', 'role');
            }]);
            
            // Apply city filter if provided
            if (isset($validated['city']) && !empty($validated['city'])) {
                $query->where('city', $validated['city']);
            }
            
            // Apply specialization filter if provided
            if (isset($validated['specialization']) && !empty($validated['specialization'])) {
                $query->where('specialization', $validated['specialization']);
            }
            
            // Get paginated results
            $perPage = $validated['per_page'] ?? 10;
            $lawyers = $query->paginate($perPage);
            
            // Format the response data
            $formattedLawyers = $lawyers->map(function($lawyer) {
                return [
                    'lawyer_id' => $lawyer->lawyer_id,
                    'name' => $lawyer->user->name,
                    'email' => $lawyer->user->email,
                    'specialization' => $lawyer->specialization,
                    'city' => $lawyer->city,
                    'consult_fee' => $lawyer->consult_fee,
                    'profile_image_url' => $lawyer->user->profile_image_url ? url($lawyer->user->profile_image_url) : null,
                ];
            });
            
            // Get unique cities and specializations for filter options
            $cities = Lawyer::distinct()->pluck('city')->filter()->values();
            $specializations = Lawyer::distinct()->pluck('specialization')->filter()->values();
            
            return response()->json([
                'success' => true,
                'data' => $formattedLawyers,
                'filter_options' => [
                    'cities' => $cities,
                    'specializations' => $specializations
                ],
                'pagination' => [
                    'current_page' => $lawyers->currentPage(),
                    'last_page' => $lawyers->lastPage(),
                    'per_page' => $lawyers->perPage(),
                    'total' => $lawyers->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting all lawyers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المحامين.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active cases with client information for a specific lawyer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientsCases(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if authenticated user is a lawyer
            if (!$user || $user->role !== 'lawyer') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. هذه الخدمة متاحة للمحامين فقط.'
                ], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف المحامي.'
                ], 404);
            }
            
            // Get all active cases assigned to this lawyer
            $cases = \App\Models\LegalCase::with(['createdBy.client'])
                ->where('assigned_lawyer_id', $lawyer->lawyer_id)
                ->where('status', 'active')
                ->get();
            
            // Format the response data
            $formattedCases = $cases->map(function($case) {
                $client = $case->createdBy->client ?? null;
                
                return [
                    'case_id' => $case->case_id,
                    'case_number' => $case->case_number,
                    'case_type' => $case->case_type,
                    'status' => $case->status,
                    'description' => $case->description,
                    'client' => $client ? [
                        'id' => $client->client_id,
                        'name' => $case->createdBy->name,
                        'phone_number' => $client->phone_number,
                        'city' => $client->city,
                        'profile_image_url' => $case->createdBy->profile_image_url ? url($case->createdBy->profile_image_url) : null,
                    ] : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedCases
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting lawyer clients cases: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب القضايا.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all lawyer cases for frontend filtering
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCases(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if authenticated user is a lawyer
            if (!$user || $user->role !== 'lawyer') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. هذه الخدمة متاحة للمحامين فقط.'
                ], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف المحامي.'
                ], 404);
            }
            
            // Validate query parameters (optional status filter)
            $validated = $request->validate([
                'status' => 'nullable|in:active,closed',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);
            
            // Start building the query
            $query = \App\Models\LegalCase::with(['createdBy.client'])
                ->where('assigned_lawyer_id', $lawyer->lawyer_id);
            
            // Apply status filter if provided (optional)
            if (isset($validated['status'])) {
                // Map 'active' and 'closed' to the actual statuses in the database
                $statusMap = [
                    'active' => ['Active'],
                    'closed' => ['Closed', 'Completed', 'Cancelled', 'Rejected']
                ];
                
                $statuses = $statusMap[$validated['status']];
                $query->whereIn('status', $statuses);
            } else {
                // Default: only show 'Active' cases
                $query->where('status', 'Active');
            }
            
            // Get paginated results
            $perPage = $validated['per_page'] ?? 10;
            $cases = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format the response data
            $formattedCases = $cases->map(function($case) {
                return [
                    'case_id' => $case->case_id,
                    'case_type' => $case->case_type,
                    'client_name' => $case->createdBy->name ?? 'Unknown',
                    'case_number' => $case->case_number,
                    'status' => strtolower($case->status),
                    'description' => $case->description
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
            \Illuminate\Support\Facades\Log::error('Error getting lawyer cases: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب القضايا.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
