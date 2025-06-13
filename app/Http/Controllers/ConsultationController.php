<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use App\Models\Lawyer;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class ConsultationController extends Controller
{
    /**
     * Request a consultation with a lawyer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestConsultation(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'lawyer_id' => 'required|exists:lawyers,lawyer_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated client
        $client = Client::where('client_id', Auth::id())->first();
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found'
            ], 404);
        }

        // Get lawyer
        $lawyer = Lawyer::findOrFail($request->lawyer_id);
        
        // Check if there's an existing pending consultation request
        $existingRequest = ConsultationRequest::where('client_id', $client->client_id)
            ->where('lawyer_id', $lawyer->lawyer_id)
            ->where('status', ConsultationRequest::STATUS_PENDING)
            ->first();
            
        if ($existingRequest) {
            // Return from Redis cache if available
            $cacheKey = "consultation:{$existingRequest->id}";
            $cachedRequest = Redis::get($cacheKey);
            
            if ($cachedRequest) {
                return response()->json([
                    'success' => true,
                    'message' => 'Existing consultation request found',
                    'data' => json_decode($cachedRequest, true)
                ]);
            }
            
            // Load relationships
            $existingRequest->load(['client', 'lawyer']);
            
            // Cache the result
            Redis::setex($cacheKey, 3600, json_encode($existingRequest));
            
            return response()->json([
                'success' => true,
                'message' => 'Existing consultation request found',
                'data' => $existingRequest
            ]);
        }

        // Create new consultation request
        $consultationRequest = new ConsultationRequest();
        $consultationRequest->client_id = $client->client_id;
        $consultationRequest->lawyer_id = $lawyer->lawyer_id;
        $consultationRequest->price = $lawyer->consultation_fee;
        $consultationRequest->status = ConsultationRequest::STATUS_PENDING;
        $consultationRequest->save();
        
        // Load relationships
        $consultationRequest->load(['client', 'lawyer']);
        
        // Cache the new consultation request
        $cacheKey = "consultation:{$consultationRequest->id}";
        Redis::setex($cacheKey, 3600, json_encode($consultationRequest));
        
        // Add to client's consultations list
        $clientConsultationsKey = "client:{$client->client_id}:consultations";
        Redis::zadd($clientConsultationsKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($clientConsultationsKey, 3600);
        
        // Add to lawyer's consultations list
        $lawyerConsultationsKey = "lawyer:{$lawyer->lawyer_id}:consultations";
        Redis::zadd($lawyerConsultationsKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($lawyerConsultationsKey, 3600);
        
        // Add to status-based list
        $statusKey = "consultations:status:" . ConsultationRequest::STATUS_PENDING;
        Redis::zadd($statusKey, now()->timestamp, $consultationRequest->id);
        Redis::expire($statusKey, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Consultation request created successfully',
            'data' => $consultationRequest
        ]);
    }
    
    /**
     * Get consultation request details
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConsultation($id)
    {
        // Try to get from Redis cache first
        $cacheKey = "consultation:{$id}";
        $cachedConsultation = Redis::get($cacheKey);
        
        if ($cachedConsultation) {
            $consultation = json_decode($cachedConsultation, true);
            
            // Check if user has access to this consultation
            if (!$this->userHasAccessToConsultation($consultation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to consultation'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => $consultation
            ]);
        }
        
        // Get from database if not in cache
        $consultation = ConsultationRequest::with(['client', 'lawyer', 'payment'])
            ->findOrFail($id);
            
        // Check if user has access to this consultation
        if (!$this->userHasAccessToConsultation($consultation->toArray())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to consultation'
            ], 403);
        }
        
        // Cache the result
        Redis::setex($cacheKey, 3600, json_encode($consultation));
        
        return response()->json([
            'success' => true,
            'data' => $consultation
        ]);
    }
    
    /**
     * Check if the authenticated user has access to the consultation
     *
     * @param array $consultation
     * @return bool
     */
    private function userHasAccessToConsultation(array $consultation): bool
    {
        $user = Auth::user();
        
        // Check if user is the client or lawyer of this consultation
        return ($user->client_id && $user->client_id == $consultation['client_id']) || 
               ($user->lawyer_id && $user->lawyer_id == $consultation['lawyer_id']);
    }
} 