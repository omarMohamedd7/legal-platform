<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LawyerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\JudgeController;
use App\Http\Controllers\ProfileController;
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
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfilePictureController;

// Apply standard rate limiting to all API routes
Route::middleware('throttle:standard')->group(function () {

    // Public routes
    // Login endpoint with stricter rate limiting
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    });
    
    Route::post('/register', [UserController::class, 'store']);
    
    // Public profile picture upload for registration
    Route::post('/register/profile-picture', [ProfilePictureController::class, 'uploadForUser']);
    
    
    // Password reset routes
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/verify-otp', [AuthController::class, 'verifyResetOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

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
        
        // Profile Picture routes
        Route::post('/profile-picture', [ProfilePictureController::class, 'upload']);
        Route::delete('/profile-picture', [ProfilePictureController::class, 'delete']);
        
        // Common profile update route for all user types
        Route::put('/update-profile', [ProfileController::class, 'updateProfile']);
        
        // Client routes
        Route::prefix('client')->group(function () {
            Route::get('/case-requests', [CaseRequestController::class, 'getClientRequests']);
            Route::get('/published-cases', [PublishedCaseController::class, 'getClientPublishedCases']);
            Route::get('/case-offers', [CaseOfferController::class, 'getClientCaseOffers']);
            Route::get('/city-lawyers', [LawyerController::class, 'getLawyersInClientCity']);
            Route::put('/update-profile', [ClientController::class, 'updateOwnProfile']);
        });
        
        // Lawyer routes
        Route::prefix('lawyer')->group(function () {
            Route::get('/case-requests', [CaseRequestController::class, 'getLawyerRequests']);
            Route::post('/case-requests/{id}/action', [CaseRequestController::class, 'action']);
            Route::get('/available-cases', [PublishedCaseController::class, 'getAvailableCasesForLawyer']);
            Route::get('/case-offers', [CaseOfferController::class, 'getLawyerOffers']);
            Route::get('/clients-cases', [LawyerController::class, 'getClientsCases']);
            Route::get('/cases', [LawyerController::class, 'getCases']);
            Route::get('/court-sessions', [CourtSessionController::class, 'getLawyerSessions']);
            Route::put('/update-profile', [LawyerController::class, 'updateOwnProfile']);
        });
        
        // Judge routes
        Route::prefix('judge')->group(function () {
            Route::get('/tasks', [JudgeTaskController::class, 'index']);
            Route::post('/tasks', [JudgeTaskController::class, 'store']);
            Route::put('/tasks/{id}', [JudgeTaskController::class, 'update']);
            Route::delete('/tasks/{id}', [JudgeTaskController::class, 'destroy']);
            Route::patch('/tasks/{id}/complete', [JudgeTaskController::class, 'markAsCompleted']);
            Route::put('/update-profile', [JudgeController::class, 'updateOwnProfile']);
        });
        
        // طلبات التوكيل المباشر (Direct Case Requests)
        Route::post('/case-requests', [CaseRequestController::class, 'store']);
        
        // النشر العام للقضايا (Published Cases)
        Route::post('/published-cases', [PublishedCaseController::class, 'publishCase']);
        Route::get('/published-cases/{id}', [PublishedCaseController::class, 'show']);
        Route::post('/published-cases/{id}/close', [PublishedCaseController::class, 'closePublishedCase']);
        Route::post('/published-cases/{id}/offers', [CaseOfferController::class, 'submitOffer']);
        
        //client accept or refuse offers
        Route::post('/case-offers/{id}/action', [CaseOfferController::class, 'processOfferAction']);
        
        // Lawyers listing routes
        Route::get('/lawyers', [LawyerController::class, 'getAllLawyers']);
        
        // Court Sessions routes
        Route::post('/court-sessions', [CourtSessionController::class, 'store']);
        Route::put('/court-sessions/{id}', [CourtSessionController::class, 'update']);
        Route::delete('/court-sessions/{id}', [CourtSessionController::class, 'destroy']);
        Route::get('/cases/{id}/court-sessions', [CourtSessionController::class, 'getCaseSessions']);
        
        // Case Attachments routes
        Route::post('/cases/{case_id}/attachments', [CaseAttachmentController::class, 'store']);
        Route::get('/cases/{case_id}/attachments', [CaseAttachmentController::class, 'index']);
        
        // Consultation Request routes with stricter rate limiting
        Route::middleware('throttle:sensitive')->group(function () {
            Route::post('/consultations/request', [ConsultationController::class, 'requestConsultation']);
        });
        Route::get('/consultations', [ConsultationController::class, 'index']);
        Route::get('/consultations/{id}', [ConsultationController::class, 'show']);
        
        // Payment routes with stricter rate limiting
        Route::middleware('throttle:sensitive')->group(function () {
            Route::post('/payments', [PaymentController::class, 'createPayment']);
        });
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        
        // Chat routes
        Route::get('/chats', [ChatController::class, 'index']);
        Route::post('/chats/consultation', [ChatController::class, 'getOrCreateConsultationChat']);
        Route::get('/chats/{id}/messages', [ChatController::class, 'getMessages']);
        Route::post('/chats/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/chats/{id}/close', [ChatController::class, 'closeChat']);

        // Video Analysis routes (Judges only)
        Route::post('/video-analyses', [VideoAnalysisController::class, 'store']);
        Route::get('/video-analyses', [VideoAnalysisController::class, 'index']);
        Route::get('/video-analyses/{id}', [VideoAnalysisController::class, 'show']);
    });
});



