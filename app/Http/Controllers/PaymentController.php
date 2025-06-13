<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Process payment for a consultation request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPayment(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'consultation_request_id' => 'required|exists:consultation_requests,id',
            'payment_method' => 'required|string',
            'transaction_id' => 'required|string|unique:payments,transaction_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = Auth::user();
        
        // Get consultation request
        $consultationRequest = ConsultationRequest::with(['client', 'lawyer'])
            ->findOrFail($request->consultation_request_id);
            
        // Check if user is the client of this consultation
        if (!$user->client_id || $user->client_id != $consultationRequest->client_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to consultation'
            ], 403);
        }
        
        // Check if consultation is in pending status
        if (!$consultationRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Consultation request is not in pending status'
            ], 400);
        }
        
        try {
            // Start database transaction
            DB::beginTransaction();
            
            // Create payment record
            $payment = new Payment();
            $payment->consultation_request_id = $consultationRequest->id;
            $payment->amount = $consultationRequest->price;
            $payment->payment_method = $request->payment_method;
            $payment->transaction_id = $request->transaction_id;
            $payment->status = 'successful';
            $payment->save();
            
            // Update consultation status
            $consultationRequest->status = ConsultationRequest::STATUS_PAID;
            $consultationRequest->save();
            
            // Create chat for the consultation
           
            
            // Commit transaction
            DB::commit();
            
            // Update Redis cache
          
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update Redis cache after payment processing
     *
     * @param ConsultationRequest $consultationRequest
     * @param Payment $payment
     * @param Chat $chat
     * @return void
     */
    private function updateRedisCache(ConsultationRequest $consultationRequest, Payment $payment, ): void
    {
        // Load relationships for caching
        $consultationRequest->load(['client', 'lawyer', 'payment']);
        
        // Update consultation in cache
        $consultationKey = "consultation:{$consultationRequest->id}";
        Redis::setex($consultationKey, 3600, json_encode($consultationRequest));
        
        // Remove from pending status and add to paid status
        $pendingKey = "consultations:status:" . ConsultationRequest::STATUS_PENDING;
        Redis::zrem($pendingKey, $consultationRequest->id);
        
        $paidKey = "consultations:status:" . ConsultationRequest::STATUS_PAID;
        Redis::zadd($paidKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($paidKey, 3600);
        
        // Update client's consultations list with new timestamp
        $clientConsultationsKey = "client:{$consultationRequest->client_id}:consultations";
        Redis::zadd($clientConsultationsKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($clientConsultationsKey, 3600);
        
        // Update lawyer's consultations list with new timestamp
        $lawyerConsultationsKey = "lawyer:{$consultationRequest->lawyer_id}:consultations";
        Redis::zadd($lawyerConsultationsKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($lawyerConsultationsKey, 3600);
        
        // Cache chat
        
        // Create chat channel for real-time messaging
        $chatChannelKey = "chat:consultation:{$consultationRequest->id}";
        
        // Add system message to chat
        $systemMessage = [
            'id' => uniqid(),
            'sender_id' => 0,
            'sender_name' => 'System',
            'content' => 'Chat started for paid consultation',
            'timestamp' => now()->timestamp,
            'read' => false
        ];
        
        Redis::zadd($chatChannelKey, $systemMessage['timestamp'], json_encode($systemMessage));
        Redis::expire($chatChannelKey, 604800); // 7 days
    }
    
    /**
     * Get payment details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPayment($id)
    {
        $payment = Payment::with(['consultationRequest'])
            ->findOrFail($id);
            
        // Check if user has access to this payment
        $user = Auth::user();
        $consultationRequest = $payment->consultationRequest;
        
        if ((!$user->client_id || $user->client_id != $consultationRequest->client_id) && 
            (!$user->lawyer_id || $user->lawyer_id != $consultationRequest->lawyer_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }
} 