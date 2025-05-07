<?php

namespace App\Http\Controllers;

use App\Models\CaseOffer;
use App\Models\PublishedCase;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaseOfferController extends Controller
{
    /**
     * تقديم عرض على قضية منشورة
     */
    public function submitOffer(Request $request, $publishedCaseId)
    {
        $user = Auth::user();
        
        if (!$user || $user->role !== 'lawyer') {
            return response()->json(['message' => 'غير مصرح. يمكن للمحامين فقط تقديم العروض.'], 403);
        }
        
        $lawyer = $user->lawyer;
        
        if (!$lawyer) {
            return response()->json(['message' => 'لم يتم العثور على ملف المحامي.'], 404);
        }
        
        $publishedCase = PublishedCase::findOrFail($publishedCaseId);
        
        // التحقق مما إذا كانت القضية نشطة
        if ($publishedCase->status !== 'Active') {
            return response()->json([
                'message' => 'لا يمكن تقديم عرض لهذه القضية. فقط القضايا النشطة تقبل العروض.',
                'current_status' => $publishedCase->status
            ], 400);
        }
        
        // التحقق مما إذا كانت القضية مناسبة لتخصص وموقع المحامي
        if ($publishedCase->target_specialization && $publishedCase->target_specialization !== $lawyer->specialization) {
            return response()->json(['message' => 'غير مصرح. هذه القضية لا تناسب تخصصك.'], 403);
        }
        
       
        
        // التحقق مما إذا كان المحامي قد قدم عرضاً مسبقاً
        $existingOffer = CaseOffer::where('published_case_id', $publishedCaseId)
            ->where('lawyer_id', $lawyer->lawyer_id)
            ->first();
            
        if ($existingOffer) {
            return response()->json(['message' => 'لقد قدمت عرضاً بالفعل لهذه القضية.'], 400);
        }
        
        // التحقق من بيانات العرض
        $validated = $request->validate([
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
        ]);
        
        // إنشاء العرض
        $offer = CaseOffer::create([
            'published_case_id' => $publishedCaseId,
            'lawyer_id' => $lawyer->lawyer_id,
            'price' => $validated['price'],
            'description' => $validated['description'],
            'status' => 'Pending',
        ]);
        
        return response()->json([
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
            return response()->json(['message' => 'غير مصرح. يمكن للعملاء فقط رفض العروض.'], 403);
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
                'message' => 'لا يمكن رفض هذا العرض. فقط العروض في حالة الانتظار يمكن رفضها.',
                'current_status' => $offer->status
            ], 400);
        }
        
        // تحديث حالة العرض
        $offer->status = 'Rejected';
        $offer->save();
        
        return response()->json(['message' => 'تم رفض العرض بنجاح.']);
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
} 