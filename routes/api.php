<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LawyerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\JudgeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CaseRequestController;
use App\Http\Controllers\PublishedCaseController;
use App\Http\Controllers\CaseOfferController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\JudgeTaskController;
use App\Http\Controllers\VideoAnalysisController;
use App\Http\Controllers\LegalBookController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;

Route::middleware('throttle:standard')->group(function () {
    // Auth
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    });
    Route::post('/register', [UserController::class, 'store']);
    Route::post('/register/profile-picture', [ProfilePictureController::class, 'uploadForUser']);
    Route::post('/verify-account', [UserController::class, 'verifyAccount']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password/verify-otp', [AuthController::class, 'verifyResetOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('save-fcm-token', [UserController::class, 'saveFcmToken']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user', function (Request $request) {
            return $request->user()->load('client', 'lawyer', 'judge');
        });
        

        // Profile
        Route::middleware('auth:sanctum')->put('/profilee', [ProfileController::class, 'updateProfile']);
        Route::middleware('auth:sanctum')->post('/profile/picture', [ProfilePictureController::class, 'upload']);
        Route::middleware('auth:sanctum')->delete('/profile/picture', [ProfilePictureController::class, 'delete']);

        
      
        // Published Cases
        Route::get('/published-cases', [PublishedCaseController::class, 'index']);
        Route::post('/published-cases', [PublishedCaseController::class, 'publishCase']);
        Route::get('/published-cases/{id}', [PublishedCaseController::class, 'show']);
        Route::post('/published-cases/{id}/close', [PublishedCaseController::class, 'closePublishedCase']);
        Route::post('/published-cases/{id}/offers', [CaseOfferController::class, 'submitOffer']);
        Route::get('/client/published-cases', [PublishedCaseController::class, 'getClientPublishedCases']);

        // Case Requests
        Route::post('/case-requests', [CaseRequestController::class, 'store']);
        Route::get('/client/case-requests', [CaseRequestController::class, 'getClientRequests']);
        Route::get('/lawyer/case-requests', [CaseRequestController::class, 'getLawyerRequests']);
        Route::post('/lawyer/case-requests/{id}/action', [CaseRequestController::class, 'action']);

        // Lawyer
        Route::get('/lawyer/available-cases', [PublishedCaseController::class, 'getAvailableCasesForLawyer']);
        Route::get('/lawyer/clients-cases', [LawyerController::class, 'getClientsCases']);
        Route::get('/lawyer/cases', [LawyerController::class, 'getCases']);
        Route::get('/lawyer/cases/{caseId}/attachment', [LegalCaseController::class, 'getCaseAttachment']);
        Route::get('/lawyers', [LawyerController::class, 'getAllLawyers']);

        // Client
        Route::get('/client/city-lawyers', [LawyerController::class, 'getLawyersInClientCity']);
        Route::get('/client/case-offers', [CaseOfferController::class, 'getClientCaseOffers']);
        Route::get('/client/cases', [ClientController::class, 'getCases']);
        Route::get('/client/active-cases', [ClientController::class, 'getActiveCases']);

        // Case Offers
        Route::post('/case-offers/{id}/action', [CaseOfferController::class, 'processOfferAction']);

        // Judge Tasks
        Route::get('/judge/tasks', [JudgeTaskController::class, 'index']);
        Route::post('/judge/tasks', [JudgeTaskController::class, 'store']);
        Route::put('/judge/tasks/{id}', [JudgeTaskController::class, 'update']);
        Route::delete('/judge/tasks/{id}', [JudgeTaskController::class, 'destroy']);
        Route::patch('/judge/tasks/{id}/complete', [JudgeTaskController::class, 'markAsCompleted']);

        // Video Analysis
        Route::post('/video-analyses', [VideoAnalysisController::class, 'store']);
        Route::get('/video-analyses', [VideoAnalysisController::class, 'index']);
        Route::get('/video-analyses/{id}', [VideoAnalysisController::class, 'show']);
        Route::get('/legal-books', [LegalBookController::class, 'index']);
        Route::get('/legal-books/categories', [LegalBookController::class, 'getBooksByCategory']);
        Route::get('/legal-books/category/{category}', [LegalBookController::class, 'getBooksByCategory']);
        Route::get('/legal-books/{id}', [LegalBookController::class, 'show']);
        Route::get('/legal-books/{id}/download', [LegalBookController::class, 'download']);

        // Video Analysis endpoints
Route::post('/video-analyses', [VideoAnalysisController::class, 'store']);
Route::get('/video-analyses', [VideoAnalysisController::class, 'index']);
Route::get('/video-analyses/{id}', [VideoAnalysisController::class, 'show']);
Route::get('/video-analyses/judge/{judgeId}', [VideoAnalysisController::class, 'getJudgeResults']);

        // Consultation routes
        Route::post('/consultations/request', [ConsultationController::class, 'requestConsultation']);
        
        // Payment endpoint
        Route::post('/payments', [PaymentController::class, 'processPayment']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    });
});



