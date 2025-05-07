<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use App\Models\Lawyer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\ConsultationRequestResource;
use App\Http\Resources\ConsultationRequestCollection;

class ConsultationController extends Controller
{
    /**
     * Get all consultation requests for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized'
            ], 401);
        }

        $query = ConsultationRequest::query();
        
        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'lawyer') {
            $query->where('lawyer_id', $user->id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Only clients and lawyers can view consultations'
            ], 403);
        }
        
        $consultations = $query->with(['client', 'lawyer'])->paginate(10);
        
        return new ConsultationRequestCollection($consultations);
    }

    /**
     * Request a legal consultation with a lawyer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestConsultation(Request $request)
    {
        // Ensure the authenticated user is a client
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if ($user->role !== 'client') {
            return response()->json([
                'success' => false,
                'message' => 'Only clients can request consultations'
            ], 403);
        }

        // Validate the request - now expecting lawyer_id from the lawyers table
        $validated = $request->validate([
            'lawyer_id' => 'required|exists:lawyers,lawyer_id',
        ]);

        // Get the lawyer profile directly using the lawyer_id
        $lawyerProfile = Lawyer::find($validated['lawyer_id']);
        if (!$lawyerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Lawyer profile not found'
            ], 404);
        }
        
        // Get the lawyer's user record
        $lawyer = User::find($lawyerProfile->user_id);
        if (!$lawyer || $lawyer->role !== 'lawyer') {
            return response()->json([
                'success' => false,
                'message' => 'Lawyer user account not found'
            ], 404);
        }

        // Prevent clients from requesting consultations from themselves
        if ($user->id === $lawyer->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot request a consultation with yourself'
            ], 422);
        }

        // Check if the consultation fee is set
        if ($lawyerProfile->consult_fee === null) {
            return response()->json([
                'success' => false,
                'message' => 'This lawyer has not set a consultation fee'
            ], 422);
        }

        try {
            // Create the consultation request
            $consultationRequest = ConsultationRequest::create([
                'client_id' => $user->id,
                'lawyer_id' => $lawyer->id, // Using the User ID of the lawyer
                'price' => $lawyerProfile->consult_fee,
                'status' => 'pending',
            ]);

            $consultationRequest->load('client', 'lawyer');

            return (new ConsultationRequestResource($consultationRequest))
                ->withMessage('Consultation request created successfully');
                
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create consultation request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Show a specific consultation request.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $consultation = ConsultationRequest::with(['client', 'lawyer'])->findOrFail($id);
        
        // Ensure the user can only see their own consultations
        if ($user->role === 'client' && $consultation->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view your own consultation requests'
            ], 403);
        } elseif ($user->role === 'lawyer' && $consultation->lawyer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only view consultations assigned to you'
            ], 403);
        }
        
        return new ConsultationRequestResource($consultation);
    }
} 