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
use Illuminate\Support\Facades\DB;
use App\Services\OtpService;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected $otpService;
    protected $chatController;
    
    public function __construct(OtpService $otpService, ChatController $chatController)
    {
        $this->otpService = $otpService;
        $this->chatController = $chatController;
    }

    // عرض كل المستخدمين (اختياري)
    public function index()
    {
        return new UserCollection(User::paginate(10));
    }
    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);
    
        $user = auth('sanctum')->user();
        
        // Add validation to ensure we have an authenticated user
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
    
        // Ensure $user is actually a User model instance
        if (!$user instanceof \App\Models\User) {
            return response()->json([
                'message' => 'Invalid user object'
            ], 500);
        }
    
        $user->fcm_token = $request->fcm_token;
        $user->save();
    
        return response()->json(['message' => 'Token saved']);
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
                'city' => [
                    'nullable', 
                    'string',
                    'max:255',
                    Rule::requiredIf(fn() => $request->input('role') === 'lawyer')
                ],
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'phone_number' => 'nullable|string|max:20',
                'specialization' => [
                    'nullable', 
                    'string',
                    Rule::requiredIf(fn() => $request->input('role') === 'lawyer'),
                    Rule::in(['Family Law', 'Civil Law', 'Criminal Law', 'Commercial Law', 'International Law'])
                ],
                'court_name' => 'nullable|string|max:255',
                'consult_fee' => 'nullable|numeric|min:0',
                'fcm_token' => 'nullable|string',
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
    
            // Use a database transaction to ensure atomicity
            return DB::transaction(function () use ($validated, $profileImageUrl, $request) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => $validated['role'],
                    'profile_image_url' => $profileImageUrl,
                    'fcm_token' => $validated['fcm_token'] ?? null,
                    // Do not set email_verified_at - user needs to verify with OTP
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
                    // Make sure we have the required fields for a lawyer
                    if (!isset($validated['specialization']) || !isset($validated['city'])) {
                        throw new \Illuminate\Validation\ValidationException(
                            Validator::make([], []), 
                            response()->json([
                                'success' => false,
                                'message' => 'Specialization and city are required for lawyers',
                                'errors' => [
                                    'specialization' => ['Specialization is required for lawyers'],
                                    'city' => ['City is required for lawyers']
                                ]
                            ], 422)
                        );
                    }
                    
                    Lawyer::create([
                        'user_id' => $user->id,
                        'phone_number' => $validated['phone_number'] ?? null,
                        'specialization' => $validated['specialization'],
                        'consult_fee' => $validated['consult_fee'] ?? 0,
                        'city' => $validated['city'],
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
                
                // Generate and send OTP for account verification
                $otp = $this->otpService->generateOtp($user);
                $this->otpService->sendOtpEmail($user, $otp, 'authentication');
                
                // Initialize contacts for the new user if they are a client or lawyer
                if ($validated['role'] === 'client' || $validated['role'] === 'lawyer') {
                    // Temporarily authenticate the user for contact initialization
                    Auth::login($user);
                    $this->chatController->initializeContacts();
                    Auth::logout();
                }
                
                // Refresh user to get updated data
                $user = $user->fresh();
        
                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully. Please verify your account with the OTP sent to your email.',
                    'data' => [
                        'user' => new UserResource($user),
                        'email' => $user->email,
                        'requires_verification' => true
                    ]
                ], 201);
            });
                
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

    /**
     * Verify OTP and activate user account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = User::where('email', $request->email)->firstOrFail();
            
            // Check if account is already verified
            if ($user->email_verified_at) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account is already verified',
                    'data' => [
                        'user' => new UserResource($user),
                    ]
                ]);
            }
            
            if (!$this->otpService->verifyOtp($user, $request->otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ], 401);
            }
            
            // Mark email as verified
            $user->update([
                'email_verified_at' => now(),
            ]);
            
            // Load the appropriate relationship based on user role
            if ($user->role === 'client') {
                $user->load('client');
            } elseif ($user->role === 'lawyer') {
                $user->load('lawyer');
            } elseif ($user->role === 'judge') {
                $user->load('judge');
            }
            
            // Generate token for automatic login after verification
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Account verified successfully',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Account verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Account verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

