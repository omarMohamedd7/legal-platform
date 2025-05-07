<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Create a new payment for a consultation request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPayment(Request $request)
    {
        // Ensure user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
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
                    'message' => 'You can only make payments for your own consultation requests'
                ], 403);
            }
            
            // Check if the consultation request is already paid
            if ($consultationRequest->status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'message' => 'This consultation request has already been paid'
                ], 422);
            }
            
            // Check if there's already a payment for this consultation
            if ($consultationRequest->payment) {
                DB::rollBack();
                return response()->json([
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
            $consultationRequest->status = 'paid';
            $consultationRequest->save();
            
            // Here you would typically activate the chat between client and lawyer
            // This is just a placeholder for that logic
            
            DB::commit();
            
            return response()->json([
                'message' => 'Payment processed successfully',
                'payment' => $payment,
                'consultation_request' => $consultationRequest,
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get payment details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $payment = Payment::with('consultationRequest')->findOrFail($id);
        
        // Ensure the authenticated user is authorized to view this payment
        $user = Auth::user();
        $consultationRequest = $payment->consultationRequest;
        
        if ($user->id !== $consultationRequest->client_id && 
            $user->id !== $consultationRequest->lawyer_id && 
            $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($payment);
    }
} 