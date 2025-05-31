<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use App\Models\Lawyer;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
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
     * @return JsonResponse|ConsultationRequestCollection
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        if (!in_array($user->role, ['client', 'lawyer'])) {
            return $this->forbiddenResponse('Only clients and lawyers can view consultations');
        }
        
        $query = ConsultationRequest::query();
        
        if ($user->role === 'client') {
            $client = Client::where('user_id', $user->id)->first();
            if (!$client) {
                return $this->notFoundResponse('Client profile not found');
            }
            $query->where('client_id', $client->client_id);
        } else {
            $lawyer = Lawyer::where('user_id', $user->id)->first();
            if (!$lawyer) {
                return $this->notFoundResponse('Lawyer profile not found');
            }
            $query->where('lawyer_id', $lawyer->lawyer_id);
        }
        
        $consultations = $query->with(['client', 'lawyer'])->paginate(10);
        
        return new ConsultationRequestCollection($consultations);
    }

    /**
     * Request a legal consultation with a lawyer.
     *
     * @param  Request  $request
     * @return JsonResponse|ConsultationRequestResource
     */
    public function requestConsultation(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        if ($user->role !== 'client') {
            return $this->forbiddenResponse('Only clients can request consultations');
        }

        try {
            $validated = $request->validate([
                'lawyer_id' => 'required|exists:lawyers,lawyer_id',
            ]);

            // Get the client record for the authenticated user
            $client = Client::where('user_id', $user->id)->first();
            if (!$client) {
                return $this->notFoundResponse('Client profile not found');
            }

            $lawyerProfile = Lawyer::find($validated['lawyer_id']);
            if (!$lawyerProfile) {
                return $this->notFoundResponse('Lawyer profile not found');
            }
            
            $lawyer = User::find($lawyerProfile->user_id);
            if (!$lawyer || $lawyer->role !== 'lawyer') {
                return $this->notFoundResponse('Lawyer user account not found');
            }

            if ($user->id === $lawyer->id) {
                return $this->validationErrorResponse('You cannot request a consultation with yourself');
            }

            if ($lawyerProfile->consult_fee === null) {
                return $this->validationErrorResponse('This lawyer has not set a consultation fee');
            }

            // Use client_id from the Client model
            $consultationRequest = $this->createConsultationRequest($client->client_id, $lawyerProfile->lawyer_id, $lawyerProfile->consult_fee);
            $consultationRequest->load('client', 'lawyer');

            return (new ConsultationRequestResource($consultationRequest))
                ->withMessage('Consultation request created successfully');
                
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create consultation request', $e->getMessage());
        }
    }
    
    /**
     * Show a specific consultation request.
     *
     * @param int $id
     * @return JsonResponse|ConsultationRequestResource
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }
        
        try {
            $consultation = ConsultationRequest::with(['client', 'lawyer'])->findOrFail($id);
            
            if (!$this->canViewConsultation($user, $consultation)) {
                return $this->forbiddenResponse('You can only view your own consultation requests');
            }
            
            return new ConsultationRequestResource($consultation);
        } catch (\Exception $e) {
            return $this->notFoundResponse('Consultation request not found');
        }
    }
    
    /**
     * Create a new consultation request.
     *
     * @param int $clientId The client_id from the clients table
     * @param int $lawyerId The lawyer_id from the lawyers table
     * @param float $price
     * @return ConsultationRequest
     */
    private function createConsultationRequest(int $clientId, int $lawyerId, float $price): ConsultationRequest
    {
        return ConsultationRequest::create([
            'client_id' => $clientId,
            'lawyer_id' => $lawyerId,
            'price' => $price,
            'status' => ConsultationRequest::STATUS_PENDING,
        ]);
    }
    
    /**
     * Check if user can view the consultation.
     *
     * @param User $user
     * @param ConsultationRequest $consultation
     * @return bool
     */
    private function canViewConsultation(User $user, ConsultationRequest $consultation): bool
    {
        if ($user->role === 'client') {
            $client = Client::where('user_id', $user->id)->first();
            return $client && $consultation->client_id === $client->client_id;
        }
        
        if ($user->role === 'lawyer') {
            $lawyer = Lawyer::where('user_id', $user->id)->first();
            return $lawyer && $consultation->lawyer_id === $lawyer->lawyer_id;
        }
        
        return false;
    }
    
    /**
     * Return unauthorized response.
     *
     * @return JsonResponse
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false, 
            'message' => 'Unauthorized'
        ], 401);
    }
    
    /**
     * Return forbidden response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }
    
    /**
     * Return not found response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function notFoundResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }
    
    /**
     * Return validation error response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function validationErrorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 422);
    }
    
    /**
     * Return server error response.
     *
     * @param string $message
     * @param string|null $error
     * @return JsonResponse
     */
    private function serverErrorResponse(string $message, ?string $error = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($error) {
            $response['error'] = $error;
        }
        
        return response()->json($response, 500);
    }
} 