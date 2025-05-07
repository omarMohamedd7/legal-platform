<?php

namespace App\Http\Controllers;

use App\Models\CourtSession;
use App\Models\LegalCase;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CourtSessionController extends Controller
{
    /**
     * Create a new court session for a case.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validatedData = $request->validate([
                'legal_case_id' => 'required|exists:cases,case_id',
                'court_name' => 'required|string|max:255',
                'session_date' => 'required|date|after_or_equal:today',
                'session_time' => 'required|date_format:H:i',
                'notes' => 'nullable|string',
                'status' => ['sometimes', Rule::in(['upcoming', 'completed', 'cancelled'])],
            ]);

            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            $lawyer = $user->lawyer;

            // Check if the user is a lawyer
            if (!$lawyer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only lawyers can create court sessions.',
                    'user_type' => $user->role,
                ], 403);
            }

            // Get the legal case
            $case = LegalCase::find($validatedData['legal_case_id']);
            
            if (!$case) {
                return response()->json([
                    'error' => true,
                    'message' => 'Legal case not found.',
                    'case_id' => $validatedData['legal_case_id'],
                ], 404);
            }

            // Check if the lawyer is assigned to this case
            if ($case->assigned_lawyer_id !== $lawyer->lawyer_id) {
                return response()->json([
                    'error' => true,
                    'message' => 'You can only create court sessions for cases assigned to you.',
                    'case_lawyer_id' => $case->assigned_lawyer_id,
                    'your_lawyer_id' => $lawyer->lawyer_id,
                ], 403);
            }

            // Create the court session
            $courtSession = new CourtSession([
                'legal_case_id' => $validatedData['legal_case_id'],
                'lawyer_id' => $lawyer->lawyer_id,
                'court_name' => $validatedData['court_name'],
                'session_date' => $validatedData['session_date'],
                'session_time' => $validatedData['session_time'],
                'notes' => $validatedData['notes'] ?? null,
                'status' => $validatedData['status'] ?? 'upcoming',
            ]);

            $saved = $courtSession->save();
            
            if (!$saved) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to save court session.',
                    'session_data' => $courtSession->toArray(),
                ], 500);
            }

            // Send notification to the client (simplified for now)
            // Get the client through the case creation or assigned client
            $client = null;
            
            // If the case has a published case, get the client from there
            if ($case->publishedCase) {
                $client = Client::find($case->publishedCase->client_id);
            } else if ($case->caseRequest) {
                $client = Client::find($case->caseRequest->client_id);
            }

            if ($client) {
                // In a real application, you would send a notification here
                // You could use Laravel's notification system
                // Example: $client->user->notify(new CourtSessionCreated($courtSession));
            }

            return response()->json([
                'success' => true,
                'message' => 'Court session created successfully.',
                'court_session' => $courtSession,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update a court session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            // Validate the request
            $validatedData = $request->validate([
                'court_name' => 'sometimes|string|max:255',
                'session_date' => 'sometimes|date|after_or_equal:today',
                'session_time' => 'sometimes|date_format:H:i',
                'notes' => 'nullable|string',
                'status' => ['sometimes', Rule::in(['upcoming', 'completed', 'cancelled'])],
            ]);

            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            $lawyer = $user->lawyer;

            // Check if the user is a lawyer
            if (!$lawyer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only lawyers can update court sessions.',
                    'user_type' => $user->role,
                ], 403);
            }

            // Get the court session
            $courtSession = CourtSession::find($id);
            
            if (!$courtSession) {
                return response()->json([
                    'error' => true,
                    'message' => 'Court session not found.',
                    'session_id' => $id,
                ], 404);
            }

            // Check if the lawyer owns this court session
            if ($courtSession->lawyer_id !== $lawyer->lawyer_id) {
                return response()->json([
                    'error' => true,
                    'message' => 'You can only update your own court sessions.',
                    'session_lawyer_id' => $courtSession->lawyer_id,
                    'your_lawyer_id' => $lawyer->lawyer_id,
                ], 403);
            }

            // Update the court session
            $updated = $courtSession->update($validatedData);
            
            if (!$updated) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to update court session.',
                    'session_data' => $courtSession->toArray(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Court session updated successfully.',
                'court_session' => $courtSession,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Delete a court session.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            $lawyer = $user->lawyer;

            // Check if the user is a lawyer
            if (!$lawyer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only lawyers can delete court sessions.',
                    'user_type' => $user->role,
                ], 403);
            }

            // Get the court session
            $courtSession = CourtSession::find($id);
            
            if (!$courtSession) {
                return response()->json([
                    'error' => true,
                    'message' => 'Court session not found.',
                    'session_id' => $id,
                ], 404);
            }

            // Check if the lawyer owns this court session
            if ($courtSession->lawyer_id !== $lawyer->lawyer_id) {
                return response()->json([
                    'error' => true,
                    'message' => 'You can only delete your own court sessions.',
                    'session_lawyer_id' => $courtSession->lawyer_id,
                    'your_lawyer_id' => $lawyer->lawyer_id,
                ], 403);
            }

            // Delete the court session
            $deleted = $courtSession->delete();
            
            if (!$deleted) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to delete court session.',
                    'session_data' => $courtSession->toArray(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Court session deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get all court sessions for a specific case.
     *
     * @param  int  $caseId
     * @return \Illuminate\Http\Response
     */
    public function getCaseSessions($caseId)
    {
        try {
            // Check if the case exists
            $case = LegalCase::find($caseId);
            
            if (!$case) {
                return response()->json([
                    'error' => true,
                    'message' => 'Legal case not found.',
                    'case_id' => $caseId,
                ], 404);
            }

            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            // Check if the user is authorized to view these sessions
            $authorized = false;
            $reason = 'You are not authorized to view these court sessions.';
            
            // If the user is a lawyer assigned to the case
            if ($user->lawyer && $user->lawyer->lawyer_id === $case->assigned_lawyer_id) {
                $authorized = true;
            }
            
            // If the user is a client who owns the case
            $isClientCase = false;
            if ($user->client) {
                $clientId = $user->client->client_id;
                if ($case->publishedCase && $case->publishedCase->client_id === $clientId) {
                    $isClientCase = true;
                }
                if ($case->caseRequest && $case->caseRequest->client_id === $clientId) {
                    $isClientCase = true;
                }
            }
            
            if ($isClientCase) {
                $authorized = true;
            }
            
            if (!$authorized) {
                return response()->json([
                    'error' => true,
                    'message' => $reason,
                    'user_role' => $user->role,
                    'user_info' => [
                        'has_lawyer' => $user->lawyer ? true : false,
                        'has_client' => $user->client ? true : false,
                    ],
                    'case_info' => [
                        'assigned_lawyer_id' => $case->assigned_lawyer_id,
                    ],
                ], 403);
            }

            // Get all court sessions for the case
            $courtSessions = CourtSession::where('legal_case_id', $caseId)
                ->orderBy('session_date', 'asc')
                ->orderBy('session_time', 'asc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'case_id' => $caseId,
                'meta' => [
                    'current_page' => $courtSessions->currentPage(),
                    'last_page' => $courtSessions->lastPage(),
                    'per_page' => $courtSessions->perPage(),
                    'total' => $courtSessions->total(),
                ],
                'links' => [
                    'prev' => $courtSessions->previousPageUrl(),
                    'next' => $courtSessions->nextPageUrl(),
                ],
                'data' => $courtSessions->items(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get all court sessions for the authenticated lawyer.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLawyerSessions()
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not authenticated',
                ], 401);
            }
            
            $lawyer = $user->lawyer;

            // Check if the user is a lawyer
            if (!$lawyer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only lawyers can view their court sessions.',
                    'user_type' => $user->role,
                ], 403);
            }

            // Get all court sessions for the lawyer
            $courtSessions = CourtSession::where('lawyer_id', $lawyer->lawyer_id)
                ->orderBy('session_date', 'asc')
                ->orderBy('session_time', 'asc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'lawyer_id' => $lawyer->lawyer_id,
                'meta' => [
                    'current_page' => $courtSessions->currentPage(),
                    'last_page' => $courtSessions->lastPage(),
                    'per_page' => $courtSessions->perPage(),
                    'total' => $courtSessions->total(),
                ],
                'links' => [
                    'prev' => $courtSessions->previousPageUrl(),
                    'next' => $courtSessions->nextPageUrl(),
                ],
                'data' => $courtSessions->items(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
} 