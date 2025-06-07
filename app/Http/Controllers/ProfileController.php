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

class ProfileController extends Controller
{
    /**
     * Update authenticated user's profile regardless of role
     */
    public function updateProfile(Request $request)
    {
        try {
            // Log the request content type and data for debugging
            Log::info('Update profile request:', [
                'content_type' => $request->header('Content-Type'),
                'all_data' => $request->all(),
                'has_file' => $request->hasFile('profile_picture') || $request->hasFile('profile_image'),
                'files' => $request->allFiles()
            ]);
            
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['message' => 'المستخدم غير مصادق عليه.'], 401);
            }
            
            // Common validation rules for all user types
            $validationRules = [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'current_password' => 'nullable|string',
                'new_password' => 'nullable|string|min:6|required_with:current_password',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Alternative field name
            ];
            
            // Role-specific validation rules
            if ($user->role === 'client') {
                $validationRules['phone_number'] = 'nullable|string|max:20';
                $validationRules['city'] = 'nullable|string|max:255';
            } elseif ($user->role === 'lawyer') {
                $validationRules['phone_number'] = 'nullable|string|max:20';
                $validationRules['specialization'] = 'nullable|string|max:255';
                $validationRules['city'] = 'nullable|string|max:255';
                $validationRules['consult_fee'] = 'nullable|numeric|min:0';
            } elseif ($user->role === 'judge') {
                $validationRules['specialization'] = 'nullable|string|max:255';
                $validationRules['court_name'] = 'nullable|string|max:255';
            }
            
            // Validate request data
            $validated = $request->validate($validationRules);
            
            Log::info('Validated data:', $validated);
            
            // Check current password if trying to change password
            if (isset($validated['current_password'])) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة.'], 400);
                }
            }
            
            // Update user data
            $userDataToUpdate = [];
            if (isset($validated['name'])) {
                $userDataToUpdate['name'] = $validated['name'];
            }
            if (isset($validated['email'])) {
                $userDataToUpdate['email'] = $validated['email'];
            }
            if (isset($validated['new_password'])) {
                $userDataToUpdate['password'] = Hash::make($validated['new_password']);
            }
            
            // Handle profile image upload
            $profileImage = $request->hasFile('profile_picture') 
                ? $request->file('profile_picture') 
                : ($request->hasFile('profile_image') ? $request->file('profile_image') : null);
                
            if ($profileImage) {
                // Log file information
                Log::info('Profile image file info:', [
                    'original_name' => $profileImage->getClientOriginalName(),
                    'size' => $profileImage->getSize(),
                    'mime' => $profileImage->getMimeType()
                ]);
                
                // Delete old image if exists
                if ($user->profile_image_url && file_exists(public_path($user->profile_image_url))) {
                    @unlink(public_path($user->profile_image_url));
                    Log::info('Deleted old profile image: ' . $user->profile_image_url);
                }
                
                $filename = time() . '_' . $user->id . '.' . $profileImage->getClientOriginalExtension();
                $uploadPath = public_path('uploads/profile_images');
                
                // Make sure the directory exists
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                    Log::info('Created directory: ' . $uploadPath);
                }
                
                try {
                    $profileImage->move($uploadPath, $filename);
                    $userDataToUpdate['profile_image_url'] = 'uploads/profile_images/' . $filename;
                    Log::info('Image uploaded successfully to: ' . $uploadPath . '/' . $filename);
                } catch (\Exception $e) {
                    Log::error('Error uploading image: ' . $e->getMessage());
                }
            }
            
            Log::info('User data to update:', $userDataToUpdate);
            
            // Update user record
            if (!empty($userDataToUpdate)) {
                $updateResult = User::where('id', $user->id)->update($userDataToUpdate);
                Log::info('User update result:', ['rows_affected' => $updateResult]);
                
                // Refresh user data from database
                $user = User::find($user->id);
            }
            
            // Update role-specific profile data
            $profileDataToUpdate = [];
            $profileModel = null;
            $profileId = null;
            
            if ($user->role === 'client' && $user->client) {
                $profileModel = Client::class;
                $profileId = ['client_id' => $user->client->client_id];
                
                if (isset($validated['phone_number'])) {
                    $profileDataToUpdate['phone_number'] = $validated['phone_number'];
                }
                if (isset($validated['city'])) {
                    $profileDataToUpdate['city'] = $validated['city'];
                }
            } elseif ($user->role === 'lawyer' && $user->lawyer) {
                $profileModel = Lawyer::class;
                $profileId = ['lawyer_id' => $user->lawyer->lawyer_id];
                
                if (isset($validated['phone_number'])) {
                    $profileDataToUpdate['phone_number'] = $validated['phone_number'];
                }
                if (isset($validated['specialization'])) {
                    $profileDataToUpdate['specialization'] = $validated['specialization'];
                }
                if (isset($validated['city'])) {
                    $profileDataToUpdate['city'] = $validated['city'];
                }
                if (isset($validated['consult_fee'])) {
                    $profileDataToUpdate['consult_fee'] = $validated['consult_fee'];
                }
            } elseif ($user->role === 'judge' && $user->judge) {
                $profileModel = Judge::class;
                $profileId = ['judge_id' => $user->judge->judge_id];
                
                if (isset($validated['specialization'])) {
                    $profileDataToUpdate['specialization'] = $validated['specialization'];
                }
                if (isset($validated['court_name'])) {
                    $profileDataToUpdate['court_name'] = $validated['court_name'];
                }
            }
            
            // Update profile data if we have changes
            if ($profileModel && $profileId && !empty($profileDataToUpdate)) {
                Log::info('Profile data to update:', [
                    'model' => $profileModel,
                    'id' => $profileId,
                    'data' => $profileDataToUpdate
                ]);
                
                $updateResult = $profileModel::where(key($profileId), reset($profileId))->update($profileDataToUpdate);
                Log::info('Profile update result:', ['rows_affected' => $updateResult]);
            }
            
            // Prepare full profile image URL
            $profileImageUrl = $user->profile_image_url ? url($user->profile_image_url) : null;
            
            // Load the user with their profile info
            $user->load('client', 'lawyer', 'judge');
            
            // Return the updated user data
            return response()->json([
                'message' => 'تم تحديث الملف الشخصي بنجاح.',
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image_url' => $profileImageUrl,
                    'profile_info' => $this->getProfileInfo($user)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
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
} 