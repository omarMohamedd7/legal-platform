<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Client;
use App\Models\Lawyer;
use App\Models\Judge;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;

class UserController extends Controller
{
    // عرض كل المستخدمين (اختياري)
    public function index()
    {
        return new UserCollection(User::paginate(10));
    }

    public function store(Request $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info('Registration attempt', [
                'has_file' => $request->hasFile('profile_picture'),
                'content_type' => $request->header('Content-Type'),
                'all_files' => $request->allFiles(),
                'all_inputs' => $request->except(['profile_picture', 'password'])
            ]);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'role' => ['required', Rule::in(['client', 'lawyer', 'judge'])],
                'city' => 'nullable|string|max:255',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'phone_number' => 'nullable|string|max:20',
                'specialization' => 'nullable|string|max:255',
                'court_name' => 'nullable|string|max:255',
                'consult_fee' => 'nullable|numeric|min:0',
            ]);
            
            // Handle profile image upload if provided
            $profileImageUrl = null;
            if ($request->hasFile('profile_picture')) {
                try {
                    $image = $request->file('profile_picture');
                    \Illuminate\Support\Facades\Log::info('Processing profile image', [
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $image->getClientMimeType(),
                        'size' => $image->getSize()
                    ]);
                    
                    $filename = time() . '.' . $image->getClientOriginalExtension();
                    
                    // Ensure directory exists
                    $uploadPath = public_path('uploads/profile_images');
                    if (!file_exists($uploadPath)) {
                        if (!mkdir($uploadPath, 0755, true)) {
                            throw new \Exception("Failed to create directory: $uploadPath");
                        }
                    }
                    
                    // Move the file
                    $image->move($uploadPath, $filename);
                    
                    $profileImageUrl = 'uploads/profile_images/' . $filename;
                    \Illuminate\Support\Facades\Log::info('Image uploaded successfully', [
                        'path' => $profileImageUrl
                    ]);
                } catch (\Exception $e) {
                    // Log the error but continue with registration without the image
                    \Illuminate\Support\Facades\Log::error('Profile image upload failed: ' . $e->getMessage(), [
                        'exception' => $e
                    ]);
                    // Continue with registration without the profile image
                }
            } else {
                \Illuminate\Support\Facades\Log::info('No profile picture provided in request');
            }
    
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'profile_image_url' => $profileImageUrl,
            ]);
            
            \Illuminate\Support\Facades\Log::info('User created', [
                'user_id' => $user->id,
                'profile_image_url' => $user->profile_image_url
            ]);
    
            if ($validated['role'] === 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'phone_number' => $validated['phone_number'] ?? null,
                    'city' => $validated['city'] ?? null,
                ]);
            }
    
            if ($validated['role'] === 'lawyer') {
                Lawyer::create([
                    'user_id' => $user->id,
                    'phone_number' => $validated['phone_number'] ?? null,
                    'specialization' => $validated['specialization'] ?? null,
                    'consult_fee' => $validated['consult_fee'] ?? null,
                    'city' => $validated['city'] ?? null,
                ]);
            }
    
            if ($validated['role'] === 'judge') {
                Judge::create([
                    'user_id' => $user->id,
                    'specialization' => $validated['specialization'] ?? null,
                    'court_name' => $validated['court_name'] ?? null,
                ]);
            }
    
            // Load the appropriate relationship based on user role
            if ($validated['role'] === 'client') {
                $user->load('client');
            } elseif ($validated['role'] === 'lawyer') {
                $user->load('lawyer');
            } elseif ($validated['role'] === 'judge') {
                $user->load('judge');
            }
            
            // Refresh user to get updated data
            $user = $user->fresh();
    
            return (new UserResource($user))
                ->withMessage('User registered successfully');
                
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // عرض مستخدم معين
    public function show($id)
    {
        $user = User::findOrFail($id);
        $user->load($this->getUserRelation($user));
        return new UserResource($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['sometimes', Rule::in(['client', 'lawyer', 'judge'])],
            'city' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'profile_image_url' => 'nullable|string',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        
        // Handle profile image upload if provided
        if ($request->hasFile('profile_image')) {
            try {
                $image = $request->file('profile_image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                
                // Ensure directory exists
                $uploadPath = public_path('uploads/profile_images');
                if (!file_exists($uploadPath)) {
                    if (!mkdir($uploadPath, 0755, true)) {
                        throw new \Exception("Failed to create directory: $uploadPath");
                    }
                }
                
                // Move the file
                if (!$image->move($uploadPath, $filename)) {
                    throw new \Exception("Failed to move uploaded file");
                }
                
                $validated['profile_image_url'] = 'uploads/profile_images/' . $filename;
            } catch (\Exception $e) {
                // Log the error but continue with update without the image
                \Illuminate\Support\Facades\Log::error('Profile image upload failed: ' . $e->getMessage());
                // Continue with update without changing the profile image
                unset($validated['profile_image_url']);
            }
        }

        $user->update($validated);
        
        // Load the appropriate relationship based on user role
        $user->load($this->getUserRelation($user));

        return (new UserResource($user))
            ->withMessage('User updated successfully');
    }

    // حذف مستخدم
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
    
    /**
     * Get the appropriate relation to load based on user role
     * 
     * @param User $user
     * @return string
     */
    private function getUserRelation(User $user): string
    {
        return match($user->role) {
            'client' => 'client',
            'lawyer' => 'lawyer',
            'judge' => 'judge',
            default => '',
        };
    }
}

