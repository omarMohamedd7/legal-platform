<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Services\ProfilePictureService;

class ProfilePictureController extends Controller
{
    protected $profilePictureService;
    
    /**
     * Create a new controller instance.
     *
     * @param ProfilePictureService $profilePictureService
     * @return void
     */
    public function __construct(ProfilePictureService $profilePictureService)
    {
        $this->profilePictureService = $profilePictureService;
    }
    
    /**
     * Upload a profile picture for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Handle the file upload
            if ($request->hasFile('profile_picture')) {
                $image = $request->file('profile_picture');
                
                // Use the service to upload the profile picture
                $result = $this->profilePictureService->upload($user, $image);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture uploaded successfully',
                    'profile_image_url' => $result['full_url'],
                    'relative_path' => $result['relative_path']
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No file was uploaded'
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Upload a profile picture for a specific user or return a temporary URL for registration
     *
     * @param Request $request
     * @param int|null $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadForUser(Request $request, $userId = null)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle the file upload
            if ($request->hasFile('profile_picture')) {
                $image = $request->file('profile_picture');
                
                if ($userId) {
                    // If user ID is provided, find the user and update their profile picture
                    $user = User::findOrFail($userId);
                    $result = $this->profilePictureService->upload($user, $image);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Profile picture uploaded successfully',
                        'profile_image_url' => $result['full_url'],
                        'relative_path' => $result['relative_path']
                    ]);
                } else {
                    // For registration: just upload the file and return the path
                    // Create a unique filename
                    $filename = time() . '_registration.' . $image->getClientOriginalExtension();
                    
                    // Ensure the directory exists
                    $uploadPath = public_path('profile_pics');
                    if (!file_exists($uploadPath)) {
                        if (!mkdir($uploadPath, 0755, true)) {
                            throw new \Exception("Failed to create directory: $uploadPath");
                        }
                    }
                    
                    // Move the file
                    if (!$image->move($uploadPath, $filename)) {
                        throw new \Exception("Failed to move uploaded file");
                    }
                    
                    $relativePath = 'profile_pics/' . $filename;
                    $fullUrl = url($relativePath);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Profile picture uploaded successfully',
                        'profile_image_url' => $fullUrl,
                        'relative_path' => $relativePath
                    ]);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No file was uploaded'
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading the profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete the profile picture of the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Use the service to delete the profile picture
            $result = $this->profilePictureService->delete($user);
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No profile picture to delete'
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 