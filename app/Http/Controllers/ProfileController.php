<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lawyer;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    /**
     * Update authenticated user's profile regardless of role
     */
    public function updateProfile(Request $request)
    {
        try {
            // Log the request data for debugging
            Log::info('Update profile request:', [
                'content_type' => $request->header('Content-Type'),
                'all_data' => $request->all(),
                'has_file' => $request->hasFile('profile_picture'),
                'files' => $request->allFiles()
            ]);
            
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير مصادق عليه.'], 401);
            }
            
            // Validation rules for the allowed fields
            $validationRules = [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ];
            
            // Validate request data
            $validated = $request->validate($validationRules);
            Log::info('Validated data:', $validated);
            
            // Update user data
            $userDataToUpdate = [];
            
            // Check for name in request (handle both JSON and form-data)
            if ($request->has('name') && !empty($request->input('name'))) {
                $userDataToUpdate['name'] = $request->input('name');
            }
            
            // Check for email in request (handle both JSON and form-data)
            if ($request->has('email') && !empty($request->input('email'))) {
                $userDataToUpdate['email'] = $request->input('email');
            }
            
            // Handle profile image upload
            if ($request->hasFile('profile_picture')) {
                $profileImage = $request->file('profile_picture');
                
                // Delete old image if exists
                if ($user->profile_image_url && file_exists(public_path($user->profile_image_url))) {
                    @unlink(public_path($user->profile_image_url));
                }
                
                $filename = time() . '_' . $user->id . '.' . $profileImage->getClientOriginalExtension();
                $uploadPath = public_path('profile_pics');
                
                // Make sure the directory exists
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $profileImage->move($uploadPath, $filename);
                $userDataToUpdate['profile_image_url'] = 'profile_pics/' . $filename;
            }
            
            Log::info('User data to update:', $userDataToUpdate);
            
            // Update user record
            if (!empty($userDataToUpdate)) {
                $updateResult = User::where('id', $user->id)->update($userDataToUpdate);
                Log::info('Update result:', ['affected_rows' => $updateResult]);
                
                // Refresh user data from database
                $user = User::find($user->id);
            } else {
                Log::warning('No data to update for user ID: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم تقديم أي بيانات للتحديث.'
                ], 400);
            }
            
            // Load the user with their profile info based on role
            if ($user->role === 'client') {
                $user->load('client');
            } elseif ($user->role === 'lawyer') {
                $user->load('lawyer');
            } elseif ($user->role === 'judge') {
                $user->load('judge');
            }
            
            // Return the updated user data using the UserResource
            return (new UserResource($user))
                ->withMessage('تم تحديث الملف الشخصي بنجاح.');
            
        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الملف الشخصي.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get role-specific profile information
     */
    private function getProfileInfo(User $user)
    {
        if ($user->role === 'client' && $user->client) {
            return [
                'client_id' => $user->client->client_id,
                'phone_number' => $user->client->phone_number,
                'city' => $user->client->city
            ];
        } elseif ($user->role === 'lawyer' && $user->lawyer) {
            return [
                'lawyer_id' => $user->lawyer->lawyer_id,
                'phone_number' => $user->lawyer->phone_number,
                'specialization' => $user->lawyer->specialization,
                'city' => $user->lawyer->city,
                'consult_fee' => $user->lawyer->consult_fee
            ];
        } elseif ($user->role === 'judge' && $user->judge) {
            return [
                'judge_id' => $user->judge->judge_id,
                'specialization' => $user->judge->specialization,
                'court_name' => $user->judge->court_name
            ];
        }
        
        return null;
    }

    /**
     * Test endpoint for profile updates
     */
    public function testProfileUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Test endpoint working',
                'request_data' => [
                    'content_type' => $request->header('Content-Type'),
                    'all_data' => $request->all(),
                    'has_name' => $request->has('name'),
                    'name_value' => $request->input('name'),
                    'has_email' => $request->has('email'),
                    'email_value' => $request->input('email'),
                    'has_file' => $request->hasFile('profile_picture'),
                    'method' => $request->method()
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error in test endpoint',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 