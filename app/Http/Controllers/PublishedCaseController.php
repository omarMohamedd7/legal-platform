<?php

namespace App\Http\Controllers;

use App\Models\PublishedCase;
use App\Models\LegalCase;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class PublishedCaseController extends Controller
{
    protected $notificationService;
    
    /**
     * Create a new controller instance.
     *
     * @param NotificationService $notificationService
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * نشر قضية للعامة للمحامين
     * Publish a case for lawyers to view and make offers
     */
    public function publishCase(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط نشر القضايا.'], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
            }
            
            // التحقق من البيانات المدخلة
            $validated = $request->validate([
                'case_type' => 'required|in:Family Law,Criminal Law,Civil Law,Commercial Law,International Law',
                'description' => 'required|string',
                'target_city' => 'required|string',
            ]);
            
            // Auto-generate a unique case number
            $caseNumber = 'CASE-' . time() . '-' . rand(1000, 9999);
            
            // إنشاء القضية الأساسية أولاً
            $legalCase = LegalCase::create([
                'case_number' => $caseNumber,
                'case_type' => $validated['case_type'],
                'description' => $validated['description'],
                'status' => 'Pending', // حالة معلقة حتى يتم قبول عرض
                'created_by_id' => $user->id,
            ]);
            
            // ثم إنشاء قضية منشورة مرتبطة بها
            $publishedCase = PublishedCase::create([
                'case_id' => $legalCase->case_id,
                'client_id' => $client->client_id,
                'status' => 'Active', // حالة نشطة لاستقبال العروض
                'target_city' => $validated['target_city'],
                'target_specialization' => $validated['case_type'], // Set target_specialization to match case_type
            ]);
            
            // Send notifications to lawyers in the target city with matching specialization
            $targetLawyers = Lawyer::where('city', $validated['target_city'])
                ->where('specialization', $validated['case_type'])
                ->with('user')
                ->get();
                
            foreach ($targetLawyers as $lawyer) {
                if ($lawyer->user && $lawyer->user->fcm_token) {
                    $this->notificationService->sendToUser(
                        $lawyer->user,
                        'New Case Available',
                        "A new case matching your specialization is available in your city.",
                        [
                            'published_case_id' => $publishedCase->published_case_id,
                            'case_type' => $validated['case_type'],
                            'type' => 'new_published_case'
                        ]
                    );
                }
            }
            
            // Load the legal case relationship for the response
            $publishedCase->load('legalCase');
            
            return response()->json([
                'message' => 'تم نشر القضية بنجاح.',
                'published_case' => [
                    'published_case_id' => $publishedCase->published_case_id,
                    'status' => $publishedCase->status,
                    'target_city' => $publishedCase->target_city,
                    'target_specialization' => $publishedCase->target_specialization,
                    'created_at' => $publishedCase->created_at,
                    'case' => [
                        'case_id' => $legalCase->case_id,
                        'case_number' => $legalCase->case_number,
                        'case_type' => $legalCase->case_type,
                        'description' => $legalCase->description,
                        'status' => $legalCase->status,
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in publishCase: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء نشر القضية.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * الحصول على قائمة القضايا المنشورة المتاحة للمحامي
     * Get available published cases for a lawyer based on specialization and city
     */
    public function getAvailableCasesForLawyer()
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'lawyer') {
                return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط عرض القضايا المتاحة.'], 403);
            }
            
            $lawyer = $user->lawyer;
            
            if (!$lawyer) {
                return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
            }
            
            // الحصول على القضايا المنشورة المناسبة لتخصص وموقع المحامي
            // Only get cases that match lawyer's specialization AND city
            $publishedCases = PublishedCase::with(['legalCase', 'client.user'])
                ->where('status', 'Active')
                ->where('target_specialization', $lawyer->specialization)
                ->where('target_city', $lawyer->city) // Filter by lawyer's city
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            // Format the response
            $formattedCases = $publishedCases->map(function($publishedCase) use ($lawyer) {
                $hasOffered = $publishedCase->offers()->where('lawyer_id', $lawyer->lawyer_id)->exists() ? 1 : 0;
                return [
                    'published_case_id' => $publishedCase->published_case_id,
                    'has_offered' => $hasOffered,
                    'status' => $publishedCase->status,
                    'target_city' => $publishedCase->target_city,
                    'target_specialization' => $publishedCase->target_specialization,
                    'created_at' => $publishedCase->created_at,
                    'case' => $publishedCase->legalCase ? [
                        'case_id' => $publishedCase->legalCase->case_id,
                        'case_number' => $publishedCase->legalCase->case_number,
                        'case_type' => $publishedCase->legalCase->case_type,
                        'description' => $publishedCase->legalCase->description,
                        'status' => $publishedCase->legalCase->status,
                    ] : null,
                    'client' => $publishedCase->client ? [
                        'client_id' => $publishedCase->client->client_id,
                        'name' => $publishedCase->client->user->name,
                        'city' => $publishedCase->client->city
                    ] : null
                ];
            });
                
            // Create a paginated response while preserving pagination metadata
            return response()->json([
                'data' => $formattedCases,
                'current_page' => $publishedCases->currentPage(),
                'last_page' => $publishedCases->lastPage(),
                'per_page' => $publishedCases->perPage(),
                'total' => $publishedCases->total(),
                'links' => [
                    'prev' => $publishedCases->previousPageUrl(),
                    'next' => $publishedCases->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAvailableCasesForLawyer: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب القضايا المتاحة.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * الحصول على قائمة القضايا المنشورة للعميل
     * Get published cases for the authenticated client
     */
    public function getClientPublishedCases()
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط عرض قضاياهم المنشورة.'], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
            }
            
            $publishedCases = PublishedCase::with(['legalCase', 'offers.lawyer.user'])
                ->where('client_id', $client->client_id)
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            // Format the response
            $formattedCases = $publishedCases->map(function($publishedCase) {
                return [
                    'published_case_id' => $publishedCase->published_case_id,
                    'status' => $publishedCase->status,
                    'target_city' => $publishedCase->target_city,
                    'target_specialization' => $publishedCase->target_specialization,
                    'created_at' => $publishedCase->created_at,
                    'case' => $publishedCase->legalCase ? [
                        'case_id' => $publishedCase->legalCase->case_id,
                        'case_number' => $publishedCase->legalCase->case_number,
                        'case_type' => $publishedCase->legalCase->case_type,
                        'description' => $publishedCase->legalCase->description,
                        'status' => $publishedCase->legalCase->status,
                    ] : null,
                    'offers' => $publishedCase->offers->map(function($offer) {
                        return [
                            'offer_id' => $offer->offer_id,
                            'status' => $offer->status,
                            'message' => $offer->message,
                            'created_at' => $offer->created_at,
                            'lawyer' => $offer->lawyer ? [
                                'lawyer_id' => $offer->lawyer->lawyer_id,
                                'name' => $offer->lawyer->user->name,
                                'specialization' => $offer->lawyer->specialization,
                                'years_of_experience' => $offer->lawyer->years_of_experience
                            ] : null
                        ];
                    })
                ];
            });
                
            // Create a paginated response while preserving pagination metadata
            return response()->json([
                'data' => $formattedCases,
                'current_page' => $publishedCases->currentPage(),
                'last_page' => $publishedCases->lastPage(),
                'per_page' => $publishedCases->perPage(),
                'total' => $publishedCases->total(),
                'links' => [
                    'prev' => $publishedCases->previousPageUrl(),
                    'next' => $publishedCases->nextPageUrl(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getClientPublishedCases: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب القضايا المنشورة.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * الحصول على تفاصيل قضية منشورة محددة
     * Get details for a specific published case
     */
    public function show($id)
    {
        try {
            $publishedCase = PublishedCase::with(['legalCase', 'client.user', 'offers.lawyer.user'])
                ->findOrFail($id);
                
            $user = Auth::user();
            
            // التحقق من الصلاحيات: المحامي يمكنه الاطلاع فقط إذا كانت القضية متاحة له
            if ($user->role === 'lawyer') {
                $lawyer = $user->lawyer;
                
                // Check if specialization matches - no exceptions
                if ($publishedCase->target_specialization !== $lawyer->specialization) {
                    return response()->json(['message' => 'غير مصرح. هذه القضية لا تناسب تخصصك.'], 403);
                }
                
                // Check if city matches - lawyers can only view cases in their city
                if ($publishedCase->target_city !== $lawyer->city) {
                    return response()->json(['message' => 'غير مصرح. هذه القضية لمحامين في مدينة أخرى.'], 403);
                }
            }
            // إذا كان العميل، تأكد أنه صاحب القضية
            else if ($user->role === 'client' && $user->client->client_id !== $publishedCase->client_id) {
                return response()->json(['message' => 'غير مصرح. هذه ليست قضيتك.'], 403);
            }
            
            // Format the response
            $response = [
                'published_case_id' => $publishedCase->published_case_id,
                'status' => $publishedCase->status,
                'target_city' => $publishedCase->target_city,
                'target_specialization' => $publishedCase->target_specialization,
                'created_at' => $publishedCase->created_at,
                'case' => $publishedCase->legalCase ? [
                    'case_id' => $publishedCase->legalCase->case_id,
                    'case_number' => $publishedCase->legalCase->case_number,
                    'case_type' => $publishedCase->legalCase->case_type,
                    'description' => $publishedCase->legalCase->description,
                    'status' => $publishedCase->legalCase->status,
                ] : null,
                'client' => $publishedCase->client ? [
                    'client_id' => $publishedCase->client->client_id,
                    'name' => $publishedCase->client->user->name, 
                    'city' => $publishedCase->client->city
                ] : null
            ];
            
            // If the viewer is the client, include offers
            if ($user->role === 'client' && $user->client->client_id === $publishedCase->client_id) {
                $response['offers'] = $publishedCase->offers->map(function($offer) {
                    return [
                        'offer_id' => $offer->offer_id,
                        'status' => $offer->status,
                        'message' => $offer->message,
                        'created_at' => $offer->created_at,
                        'lawyer' => $offer->lawyer ? [
                            'lawyer_id' => $offer->lawyer->lawyer_id,
                            'name' => $offer->lawyer->user->name,
                            'specialization' => $offer->lawyer->specialization,
                            'years_of_experience' => $offer->lawyer->years_of_experience
                        ] : null
                    ];
                });
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in show published case: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء عرض القضية المنشورة.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * إغلاق قضية منشورة
     * Close a published case
     */
    public function closePublishedCase($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط إغلاق قضاياهم المنشورة.'], 403);
            }
            
            // Try to find the published case with improved error handling
            try {
                $publishedCase = PublishedCase::with('legalCase')->findOrFail($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'القضية المنشورة غير موجودة. يرجى التحقق من الرقم المعرف.',
                    'error' => 'Published case not found'
                ], 404);
            }
            
            // التأكد من أن العميل هو صاحب القضية
            if ($user->client->client_id !== $publishedCase->client_id) {
                return response()->json(['message' => 'غير مصرح. هذه ليست قضيتك.'], 403);
            }
            
            // لا يمكن إغلاق قضية غير نشطة
            if ($publishedCase->status !== 'Active') {
                return response()->json([
                    'message' => 'لا يمكن إغلاق هذه القضية. فقط القضايا النشطة يمكن إغلاقها.',
                    'current_status' => $publishedCase->status
                ], 400);
            }

            // التحقق من أن حالة القضية القانونية المرتبطة هي "معلقة"
            if (!$publishedCase->legalCase || $publishedCase->legalCase->status !== 'Pending') {
                return response()->json([
                    'message' => 'لا يمكن إغلاق هذه القضية. يمكن فقط إغلاق القضايا المنشورة عندما تكون القضية القانونية المرتبطة في حالة معلقة.',
                    'current_legal_case_status' => $publishedCase->legalCase ? $publishedCase->legalCase->status : 'غير موجودة'
                ], 400);
            }
            
            // تغيير حالة القضية المنشورة إلى "مغلقة"
            $publishedCase->status = 'Closed';
            $publishedCase->save();
            
            // تغيير حالة القضية القانونية المرتبطة إلى "مغلقة" أيضاً
            if ($publishedCase->legalCase) {
                $publishedCase->legalCase->status = 'Closed';
                $publishedCase->legalCase->save();
            }
            
            return response()->json([
                'message' => 'تم إغلاق القضية المنشورة بنجاح.',
                'published_case_id' => $publishedCase->published_case_id,
                'published_case_status' => $publishedCase->status,
                'legal_case_status' => $publishedCase->legalCase ? $publishedCase->legalCase->status : null,
                'updated_at' => $publishedCase->updated_at
            ]);
        } catch (\Exception $e) {
            Log::error('Error in closePublishedCase: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء إغلاق القضية المنشورة.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 