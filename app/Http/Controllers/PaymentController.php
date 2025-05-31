<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Create a new payment for a consultation request.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function createPayment(Request $request): JsonResponse
    {
        // Ensure user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Validate the request
        $validated = $request->validate([
            'consultation_request_id' => [
                'required',
                'exists:consultation_requests,id',
                Rule::unique('payments', 'consultation_request_id'),
            ],
            'payment_method' => 'required|in:credit_card,paypal,visa,mastercard,bank_transfer',
            'transaction_id' => 'nullable|string',
        ]);

        // Begin a database transaction
        DB::beginTransaction();
        
        try {
            // Get the consultation request
            $consultationRequest = ConsultationRequest::findOrFail($validated['consultation_request_id']);
            
            // Check if the authenticated user is the client who made the request
            if ($consultationRequest->client_id !== $user->id) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You can only make payments for your own consultation requests'
                ], 403);
            }
            
            // Check if the consultation request is already paid
            if ($consultationRequest->status === ConsultationRequest::STATUS_PAID) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This consultation request has already been paid'
                ], 422);
            }
            
            // Check if there's already a payment for this consultation
            if ($consultationRequest->payment) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'A payment already exists for this consultation request'
                ], 422);
            }
            
            // Create the payment record
            $payment = Payment::create([
                'consultation_request_id' => $consultationRequest->id,
                'amount' => $consultationRequest->price,
                'payment_method' => $validated['payment_method'],
                'status' => 'successful', // In a real implementation, this would be set after payment gateway confirmation
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);
            
            // Update the consultation request status to paid
            $consultationRequest->status = ConsultationRequest::STATUS_PAID;
            $consultationRequest->save();
            
            // Create a chat session for the consultation
            $chat = $this->createChatSession($consultationRequest);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment' => [
                        'payment_id' => $payment->payment_id,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    ],
                    'consultation_request' => [
                        'id' => $consultationRequest->id,
                        'status' => $consultationRequest->status,
                    ],
                    'chat' => [
                        'id' => $chat->id,
                        'status' => $chat->status,
                    ],
                ],
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get payment details.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $payment = Payment::with('consultationRequest')->findOrFail($id);
        
        // Ensure the authenticated user is authorized to view this payment
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        
        $consultationRequest = $payment->consultationRequest;
        
        if ($user->id !== $consultationRequest->client_id && 
            $user->id !== $consultationRequest->lawyer_id && 
            $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->payment_id,
                'consultation_request_id' => $payment->consultation_request_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $payment->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
    
    /**
     * Create a chat session for a paid consultation.
     *
     * @param ConsultationRequest $consultationRequest
     * @return Chat
     */
    private function createChatSession(ConsultationRequest $consultationRequest): Chat
    {
        // Load the client and lawyer relationships if not already loaded
        if (!$consultationRequest->relationLoaded('client')) {
            $consultationRequest->load('client');
        }
        
        if (!$consultationRequest->relationLoaded('lawyer')) {
            $consultationRequest->load('lawyer');
        }
        
        // Get the user IDs from the client and lawyer models
        $clientUserId = $consultationRequest->client->user_id;
        $lawyerUserId = $consultationRequest->lawyer->user_id;
        
        return Chat::firstOrCreate(
            [
                'client_id' => $clientUserId,
                'lawyer_id' => $lawyerUserId,
                'consultation_request_id' => $consultationRequest->id,
            ],
            [
                'status' => Chat::STATUS_ACTIVE,
                'last_message_at' => now(),
            ]
        );
    }
} 