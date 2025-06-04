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
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'role' => ['required', Rule::in(['client', 'lawyer', 'judge'])],
                'city' => 'nullable|string|max:255',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Accept image file
                'profile_image_url' => 'nullable|string', // Also allow string URL if provided directly
                'phone_number' => 'nullable|string|max:20', // فقط للـ client و lawyer
                'specialization' => 'nullable|string|max:255', // فقط للقاضي
                'court_name' => 'nullable|string|max:255',
                'consult_fee' => 'nullable|numeric|min:0',
            ]);
            
            // Handle profile image upload if provided
            $profileImageUrl = null;
            if ($request->hasFile('profile_image')) {
                $image = $request->file('profile_image');
                $filename = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/profile_images'), $filename);
                $profileImageUrl = 'uploads/profile_images/' . $filename;
            } elseif (isset($validated['profile_image_url'])) {
                $profileImageUrl = $validated['profile_image_url'];
            }
    
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'profile_image_url' => $profileImageUrl,
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
    
            return (new UserResource($user))
                ->withMessage('User registered successfully');
                
        } catch (\Exception $e) {
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
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Accept image file
            'profile_image_url' => 'nullable|string', // Also allow string URL if provided directly
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        
        // Handle profile image upload if provided
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $filename = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/profile_images'), $filename);
            $validated['profile_image_url'] = 'uploads/profile_images/' . $filename;
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

