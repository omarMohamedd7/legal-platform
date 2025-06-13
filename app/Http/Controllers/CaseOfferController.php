<?php

namespace App\Http\Controllers;

use App\Models\CaseOffer;
use App\Models\PublishedCase;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class CaseOfferController extends Controller
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
     * تقديم عرض على قضية منشورة
     * Submit an offer for a published case
     * 
     * @param Request $request
     * @param int $publishedCaseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitOffer(Request $request, $publishedCaseId)
    {
        $user = Auth::user();
        
        if (!$user || $user->role !== 'lawyer') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. يمكن للمحامين فقط تقديم العروض.'
            ], 403);
        }
        
        $lawyer = $user->lawyer;
        
        if (!$lawyer) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف المحامي.'
            ], 404);
        }
        
        $publishedCase = PublishedCase::findOrFail($publishedCaseId);
        
        // التحقق مما إذا كانت القضية نشطة
        if ($publishedCase->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تقديم عرض لهذه القضية. فقط القضايا النشطة تقبل العروض.',
                'current_status' => $publishedCase->status
            ], 400);
        }
        
        // التحقق مما إذا كانت القضية مناسبة لتخصص وموقع المحامي
        if ($publishedCase->target_specialization && $publishedCase->target_specialization !== $lawyer->specialization) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. هذه القضية لا تناسب تخصصك.'
            ], 403);
        }
        
       
        
        // التحقق مما إذا كان المحامي قد قدم عرضاً مسبقاً
        $existingOffer = CaseOffer::where('published_case_id', $publishedCaseId)
            ->where('lawyer_id', $lawyer->lawyer_id)
            ->first();
            
        if ($existingOffer) {
            return response()->json([
                'success' => false,
                'message' => 'لقد قدمت عرضاً بالفعل لهذه القضية.'
            ], 400);
        }
        
        // التحقق من بيانات العرض
        $validated = $request->validate([
            'expected_price' => 'required|numeric|min:0',
            'message' => 'required|string',
        ]);
        
        // إنشاء العرض
        $offer = CaseOffer::create([
            'published_case_id' => $publishedCaseId,
            'lawyer_id' => $lawyer->lawyer_id,
            'expected_price' => $validated['expected_price'],
            'message' => $validated['message'],
            'status' => 'Pending',
        ]);
        
        // Send notification to the client
        $publishedCase = PublishedCase::with('client.user', 'legalCase')->find($publishedCaseId);
        $client = $publishedCase->client->user;
        if ($client && $client->fcm_token) {
            $this->notificationService->sendToUser(
                $client,
                'New Case Offer',
                "A lawyer has submitted an offer for your case: {$publishedCase->legalCase->case_number}",
                [
                    'published_case_id' => $publishedCaseId,
                    'offer_id' => $offer->offer_id,
                    'type' => 'new_case_offer'
                ]
            );
        }
        
        return response()->json([
            'success' => true,
            'message' => 'تم تقديم العرض بنجاح.',
            'offer' => $offer
        ], 201);
    }
    
    /**
     * قبول عرض من العميل
     */
    public function acceptOffer($offerId)
    {
        $user = Auth::user();
        
        if (!$user || $user->role !== 'client') {
            return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط قبول العروض.'], 403);
        }
        
        $client = $user->client;
        
        if (!$client) {
            return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
        }
        
        $offer = CaseOffer::with('publishedCase')->findOrFail($offerId);
        
        // التأكد من أن العميل هو صاحب القضية
        if ($client->client_id !== $offer->publishedCase->client_id) {
            return response()->json(['message' => 'غير مصرح. هذا العرض ليس لقضيتك.'], 403);
        }
        
        // التحقق مما إذا كان العرض في حالة انتظار
        if ($offer->status !== 'Pending') {
            return response()->json([
                'message' => 'لا يمكن قبول هذا العرض. فقط العروض في حالة الانتظار يمكن قبولها.',
                'current_status' => $offer->status
            ], 400);
        }
        
        // التحقق مما إذا كانت القضية المنشورة نشطة
        if ($offer->publishedCase->status !== 'Active') {
            return response()->json([
                'message' => 'لا يمكن قبول العروض لهذه القضية. القضية ليست نشطة.',
                'current_status' => $offer->publishedCase->status
            ], 400);
        }
        
        // بدء معاملة لضمان تحديث جميع البيانات بشكل متسق
        DB::beginTransaction();
        
        try {
            // تحديث حالة العرض
            $offer->status = 'Accepted';
            $offer->save();
            
            // تحديث حالة القضية المنشورة
            $offer->publishedCase->status = 'Closed';
            $offer->publishedCase->save();
            
            // تحديث حالة القضية الأساسية وإسناد المحامي
            $legalCase = $offer->publishedCase->legalCase;
            $legalCase->status = 'Active';
            $legalCase->assigned_lawyer_id = $offer->lawyer_id;
            $legalCase->save();
            
            // رفض جميع العروض الأخرى
            CaseOffer::where('published_case_id', $offer->published_case_id)
                ->where('offer_id', '!=', $offerId)
                ->update(['status' => 'Rejected']);
            
            // Send notification to the lawyer
            $lawyer = User::find($offer->lawyer->user_id);
            if ($lawyer && $lawyer->fcm_token) {
                $this->notificationService->sendToUser(
                    $lawyer,
                    'Offer Accepted',
                    "Your offer for case {$legalCase->case_number} has been accepted.",
                    [
                        'offer_id' => $offer->offer_id,
                        'case_id' => $legalCase->case_id,
                        'type' => 'offer_accepted'
                    ]
                );
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'تم قبول العرض بنجاح وتفعيل القضية.',
                'offer' => $offer->load(['lawyer.user', 'publishedCase.legalCase'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'حدث خطأ أثناء قبول العرض.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * رفض عرض من العميل
     */
    public function rejectOffer($offerId)
    {
        $user = Auth::user();
        
        if (!$user || $user->role !== 'client') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. يمكن للعملاء فقط رفض العروض.'
            ], 403);
        }
        
        $client = $user->client;
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف العميل.'
            ], 404);
        }
        
        $offer = CaseOffer::with(['publishedCase', 'lawyer.user'])->findOrFail($offerId);
        
        // التحقق مما إذا كان العرض ينتمي إلى قضية العميل
        if ($offer->publishedCase->client_id !== $client->client_id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. يمكنك فقط رفض العروض المقدمة على قضاياك.'
            ], 403);
        }
        
        // التحقق مما إذا كان العرض معلقاً
        if ($offer->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض هذا العرض. العرض ليس في حالة معلقة.',
                'current_status' => $offer->status
            ], 400);
        }
        
        // تحديث حالة العرض
        $offer->status = 'Rejected';
        $offer->save();
        
        // Send notification to the lawyer
        $lawyer = $offer->lawyer->user;
        if ($lawyer && $lawyer->fcm_token) {
            $this->notificationService->sendToUser(
                $lawyer,
                'Offer Rejected',
                "Your offer has been rejected by the client.",
                [
                    'offer_id' => $offer->offer_id,
                    'published_case_id' => $offer->published_case_id,
                    'type' => 'offer_rejected'
                ]
            );
        }
        
        return response()->json([
            'success' => true,
            'message' => 'تم رفض العرض بنجاح.',
            'offer' => $offer
        ]);
    }
    
    /**
     * الحصول على قائمة العروض للمحامي
     */
    public function getLawyerOffers()
    {
        $user = Auth::user();
        
        if (!$user || $user->role !== 'lawyer') {
            return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط عرض عروضهم.'], 403);
        }
        
        $lawyer = $user->lawyer;
        
        if (!$lawyer) {
            return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
        }
        
        $offers = CaseOffer::with(['publishedCase.legalCase', 'publishedCase.client.user'])
            ->where('lawyer_id', $lawyer->lawyer_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json($offers);
    }
    
    /**
     * الحصول على تفاصيل عرض محدد
     */
    public function show($id)
    {
        $offer = CaseOffer::with(['publishedCase.legalCase', 'lawyer.user', 'publishedCase.client.user'])
            ->findOrFail($id);
        
        $user = Auth::user();
        
        // التحقق من الصلاحيات: إما المحامي صاحب العرض أو العميل صاحب القضية
        if ($user->role === 'lawyer' && $user->lawyer->lawyer_id !== $offer->lawyer_id) {
            return response()->json(['message' => 'غير مصرح. هذا ليس عرضك.'], 403);
        } else if ($user->role === 'client' && $user->client->client_id !== $offer->publishedCase->client_id) {
            return response()->json(['message' => 'غير مصرح. هذا العرض ليس لقضيتك.'], 403);
        }
        
        return response()->json($offer);
    }

    /**
     * الحصول على كل العروض المقدمة على قضايا العميل المنشورة
     * Get all offers submitted to the client's published cases
     */
    public function getClientCaseOffers(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'client') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح. يمكن للعملاء فقط عرض العروض المقدمة على قضاياهم.'
                ], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف العميل.'
                ], 404);
            }
            
            // Validate query parameters
            $validated = $request->validate([
                'published_case_id' => 'nullable|integer|exists:published_cases,published_case_id',
                'status' => 'nullable|in:Pending,Accepted,Rejected',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);
            
            // Get all published cases for this client
            $query = CaseOffer::with([
                'lawyer.user', 
                'publishedCase.legalCase'
            ])
            ->whereHas('publishedCase', function($query) use ($client) {
                $query->where('client_id', $client->client_id);
            });
            
            // Filter by published case if specified
            if (isset($validated['published_case_id'])) {
                $query->where('published_case_id', $validated['published_case_id']);
            }
            
            // Filter by status if specified
            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            
            // Get paginated results
            $perPage = $validated['per_page'] ?? 10;
            $offers = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Format response data
            $formattedOffers = $offers->map(function($offer) {
                return [
                    'offer_id' => $offer->offer_id,
                    'status' => $offer->status,
                    'expected_price' => $offer->expected_price,
                    'message' => $offer->message,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                    'lawyer' => [
                        'lawyer_id' => $offer->lawyer->lawyer_id,
                        'name' => $offer->lawyer->user->name,
                        'email' => $offer->lawyer->user->email,
                        'profile_image_url' => $offer->lawyer->user->profile_image_url ? url($offer->lawyer->user->profile_image_url) : null,
                        'specialization' => $offer->lawyer->specialization,
                        'city' => $offer->lawyer->city,
                        'consult_fee' => $offer->lawyer->consult_fee,
                    ],
                    'published_case' => [
                        'published_case_id' => $offer->publishedCase->published_case_id,
                        'status' => $offer->publishedCase->status,
                        'case' => [
                            'case_id' => $offer->publishedCase->legalCase->case_id,
                            'case_number' => $offer->publishedCase->legalCase->case_number,
                            'case_type' => $offer->publishedCase->legalCase->case_type,
                            'plaintiff_name' => $offer->publishedCase->legalCase->plaintiff_name,
                            'defendant_name' => $offer->publishedCase->legalCase->defendant_name,
                            'description' => $offer->publishedCase->legalCase->description,
                            'status' => $offer->publishedCase->legalCase->status,
                        ]
                    ]
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedOffers,
                'pagination' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting client case offers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب العروض.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process an offer action (accept or reject)
     * 
     * @param Request $request
     * @param int $offerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function processOfferAction(Request $request, $offerId)
    {
        // Validate the action parameter
        $validated = $request->validate([
            'action' => 'required|string|in:accept,reject',
        ]);
        
        $user = Auth::user();
        
        if (!$user || $user->role !== 'client') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. يمكن للعملاء فقط قبول أو رفض العروض.'
            ], 403);
        }
        
        $client = $user->client;
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف العميل.'
            ], 404);
        }
        
        $offer = CaseOffer::with(['publishedCase.legalCase', 'lawyer.user'])->findOrFail($offerId);
        
        // التأكد من أن العميل هو صاحب القضية
        if ($client->client_id !== $offer->publishedCase->client_id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح. هذا العرض ليس لقضيتك.'
            ], 403);
        }
        
        // التحقق مما إذا كان العرض في حالة انتظار
        if ($offer->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن معالجة هذا العرض. فقط العروض في حالة الانتظار يمكن معالجتها.',
                'current_status' => $offer->status
            ], 400);
        }
        
        // Process based on action
        if ($validated['action'] === 'accept') {
            // التحقق مما إذا كانت القضية المنشورة نشطة
            if ($offer->publishedCase->status !== 'Active') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن قبول العروض لهذه القضية. القضية ليست نشطة.',
                    'current_status' => $offer->publishedCase->status
                ], 400);
            }
            
            // بدء معاملة لضمان تحديث جميع البيانات بشكل متسق
            DB::beginTransaction();
            
            try {
                // تحديث حالة العرض
                $offer->status = 'Accepted';
                $offer->save();
                
                // تحديث حالة القضية المنشورة
                $offer->publishedCase->status = 'Closed';
                $offer->publishedCase->save();
                
                // تحديث حالة القضية الأساسية وإسناد المحامي
                $legalCase = $offer->publishedCase->legalCase;
                $legalCase->status = 'Active';
                $legalCase->assigned_lawyer_id = $offer->lawyer_id;
                $legalCase->save();
                
                // رفض جميع العروض الأخرى
                CaseOffer::where('published_case_id', $offer->published_case_id)
                    ->where('offer_id', '!=', $offerId)
                    ->update(['status' => 'Rejected']);
                
                // Send notification to the lawyer
                $lawyer = $offer->lawyer->user;
                if ($lawyer && $lawyer->fcm_token) {
                    $this->notificationService->sendToUser(
                        $lawyer,
                        'Offer Accepted',
                        "Your offer for case {$legalCase->case_number} has been accepted.",
                        [
                            'offer_id' => $offer->offer_id,
                            'case_id' => $legalCase->case_id,
                            'type' => 'offer_accepted'
                        ]
                    );
                }
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'تم قبول العرض بنجاح وتفعيل القضية.',
                    'offer' => $offer->load(['lawyer.user', 'publishedCase.legalCase'])
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء قبول العرض.',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else { // reject
            // تحديث حالة العرض
            $offer->status = 'Rejected';
            $offer->save();
            
            // Send notification to the lawyer
            $lawyer = $offer->lawyer->user;
            if ($lawyer && $lawyer->fcm_token) {
                $this->notificationService->sendToUser(
                    $lawyer,
                    'Offer Rejected',
                    "Your offer for case {$offer->publishedCase->legalCase->case_number} has been rejected.",
                    [
                        'offer_id' => $offer->offer_id,
                        'published_case_id' => $offer->published_case_id,
                        'type' => 'offer_rejected'
                    ]
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'تم رفض العرض بنجاح.'
            ]);
        }
    }
} 