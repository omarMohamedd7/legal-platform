<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LawyerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\JudgeController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\CaseRequestController;
use App\Http\Controllers\PublishedCaseController;
use App\Http\Controllers\CaseOfferController;
use App\Http\Controllers\CourtSessionController;
use App\Http\Controllers\LegalBookController;
use App\Http\Controllers\VideoAnalysisController;
use App\Http\Controllers\CaseAttachmentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\JudgeTaskController;

// Apply standard rate limiting to all API routes
Route::middleware('throttle:standard')->group(function () {

    // Public routes
    // Login endpoint with stricter rate limiting
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });
    
    Route::post('/register', [UserController::class, 'store']);
    
    // Password reset routes
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/ me', [AuthController::class, 'resetPassword']);

    // Legal Books Library routes (public)
    Route::get('/legal-books', [LegalBookController::class, 'index']);
    Route::get('/legal-books/categories', [LegalBookController::class, 'categories']);
    Route::get('/legal-books/category/{category}', [LegalBookController::class, 'byCategory']);
    Route::get('/legal-books/{id}', [LegalBookController::class, 'show']);
    Route::get('/legal-books/{id}/download', [LegalBookController::class, 'download']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);

        // User profile
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user', function (Request $request) {
            return $request->user()->load('client', 'lawyer', 'judge');
        });
        
        // Users routes
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Lawyers routes
        Route::get('/lawyers', [LawyerController::class, 'index']);
        Route::get('/lawyers/{id}', [LawyerController::class, 'show']);
        Route::put('/lawyers/{id}', [LawyerController::class, 'update']);
        Route::delete('/lawyers/{id}', [LawyerController::class, 'destroy']);
        
        // Clients routes
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{id}', [ClientController::class, 'show']);
        Route::put('/clients/{id}', [ClientController::class, 'update']);
        Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
        
        // Judges routes
        Route::get('/judges', [JudgeController::class, 'index']);
        Route::get('/judges/{id}', [JudgeController::class, 'show']);
        Route::put('/judges/{id}', [JudgeController::class, 'update']);
        Route::delete('/judges/{id}', [JudgeController::class, 'destroy']);
        
        // Cases routes
        Route::get('/cases', [LegalCaseController::class, 'index']);
        Route::post('/cases', [LegalCaseController::class, 'store']);
        Route::get('/cases/{id}', [LegalCaseController::class, 'show']);
        Route::put('/cases/{id}', [LegalCaseController::class, 'update']);
        Route::delete('/cases/{id}', [LegalCaseController::class, 'destroy']);
        
        // طلبات التوكيل المباشر (Direct Case Requests)
        Route::post('/case-requests', [CaseRequestController::class, 'store']);
        Route::post('/case-requests/{id}/accept', [CaseRequestController::class, 'accept']);
        Route::post('/case-requests/{id}/reject', [CaseRequestController::class, 'reject']);
        Route::get('/client/case-requests', [CaseRequestController::class, 'getClientRequests']);
        Route::get('/lawyer/case-requests', [CaseRequestController::class, 'getLawyerRequests']);
        
        // النشر العام للقضايا (Published Cases)
        Route::post('/published-cases', [PublishedCaseController::class, 'publishCase']);
        Route::get('/lawyer/available-cases', [PublishedCaseController::class, 'getAvailableCasesForLawyer']);
        Route::get('/client/published-cases', [PublishedCaseController::class, 'getClientPublishedCases']);
        Route::get('/published-cases/{id}', [PublishedCaseController::class, 'show']);
        Route::post('/published-cases/{id}/close', [PublishedCaseController::class, 'closePublishedCase']);
        
        // عروض المحامين على القضايا المنشورة (Case Offers)
        Route::post('/published-cases/{id}/offers', [CaseOfferController::class, 'submitOffer']);
        Route::post('/case-offers/{id}/accept', [CaseOfferController::class, 'acceptOffer']);
        Route::post('/case-offers/{id}/reject', [CaseOfferController::class, 'rejectOffer']);
        Route::get('/lawyer/case-offers', [CaseOfferController::class, 'getLawyerOffers']);
        Route::get('/case-offers/{id}', [CaseOfferController::class, 'show']);
        
        // Court Sessions routes
        Route::post('/court-sessions', [CourtSessionController::class, 'store']);
        Route::put('/court-sessions/{id}', [CourtSessionController::class, 'update']);
        Route::delete('/court-sessions/{id}', [CourtSessionController::class, 'destroy']);
        Route::get('/cases/{id}/court-sessions', [CourtSessionController::class, 'getCaseSessions']);
        Route::get('/lawyer/court-sessions', [CourtSessionController::class, 'getLawyerSessions']);
        
        // Case Attachments routes
        Route::post('/cases/{case_id}/attachments', [CaseAttachmentController::class, 'store']);
        Route::get('/cases/{case_id}/attachments', [CaseAttachmentController::class, 'index']);
        
        // Consultation Request routes with stricter rate limiting
        Route::middleware('throttle:sensitive')->group(function () {
            Route::post('/consultations/request', [ConsultationController::class, 'requestConsultation']);
        });
        
        // Payment routes with stricter rate limiting
        Route::middleware('throttle:sensitive')->group(function () {
            Route::post('/payments', [PaymentController::class, 'createPayment']);
        });
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        
        // Judge Tasks routes
        Route::get('/judge/tasks', [JudgeTaskController::class, 'index']);
        Route::post('/judge/tasks', [JudgeTaskController::class, 'store']);
        Route::put('/judge/tasks/{id}', [JudgeTaskController::class, 'update']);
        Route::delete('/judge/tasks/{id}', [JudgeTaskController::class, 'destroy']);
        Route::patch('/judge/tasks/{id}/complete', [JudgeTaskController::class, 'markAsCompleted']);

        // Video Analysis routes (Judges only)
        Route::post('/video-analyses', [VideoAnalysisController::class, 'store']);
        Route::get('/video-analyses', [VideoAnalysisController::class, 'index']);
        Route::get('/video-analyses/{id}', [VideoAnalysisController::class, 'show']);
    });
});



