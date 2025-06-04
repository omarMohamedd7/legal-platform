<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ClientController extends Controller
{
    // عرض قائمة العملاء (اختياري)
    public function index()
    {
        $clients = Client::with('user')->get();
        return response()->json($clients);
    }

    // عرض عميل محدد
    public function show($id)
    {
        $client = Client::with('user')->findOrFail($id);
        return response()->json($client);
    }

    // تحديث بيانات عميل (اختياري)
    public function update(Request $request, $id)
    {
        $client = Client::with('user')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $client->user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
        ]);

        // تحديث بيانات المستخدم المرتبط
        $client->user->update([
            'name' => $validated['name'] ?? $client->user->name,
            'email' => $validated['email'] ?? $client->user->email,
        ]);

        // تحديث بيانات الزبون (بما في ذلك المدينة إذا كانت ضمن clients)
        $client->update([
            'phone' => $validated['phone'] ?? $client->phone,
            'city' => $validated['city'] ?? $client->city,
        ]);

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $client->load('user')
        ]);
    }

    // حذف عميل (اختياري)
    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        $client->user->delete(); // حذف المستخدم المرتبط
        $client->delete();       // حذف الزبون
    
        return response()->json(['message' => 'Client and related user deleted']);
    }
    
    /**
     * تحديث الملف الشخصي للعميل المصادق عليه
     * Update authenticated client's own profile with form data support for image upload
     */
    public function updateOwnProfile(Request $request)
    {
        try {
            // Log the request content type and data for debugging
            \Illuminate\Support\Facades\Log::info('Update profile request:', [
                'content_type' => $request->header('Content-Type'),
                'all_data' => $request->all(),
                'has_file' => $request->hasFile('profile_image'),
                'files' => $request->allFiles()
            ]);
            
            $user = Auth::user();
            
            // التحقق من أن المستخدم هو عميل
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. هذه الخدمة متاحة للعملاء فقط.'], 403);
            }
            
            $client = $user->client;
            
            if (!$client) {
                return response()->json(['message' => 'لم يتم العثور على ملف العميل.'], 404);
            }
            
            // Log current user and client data before update
            \Illuminate\Support\Facades\Log::info('Current user data:', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'client_id' => $client->client_id,
                'phone_number' => $client->phone_number,
                'city' => $client->city
            ]);
            
            // التحقق من البيانات المدخلة
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'phone_number' => 'nullable|string|max:20',
                'city' => 'nullable|string|max:255',
                'current_password' => 'nullable|string',
                'new_password' => 'nullable|string|min:6|required_with:current_password',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
            
            \Illuminate\Support\Facades\Log::info('Validated data:', $validated);
            
            // التحقق من كلمة المرور الحالية إذا كان يريد تغييرها
            if (isset($validated['current_password'])) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة.'], 400);
                }
            }
            
            // تحديث بيانات المستخدم
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
            
            // معالجة تحميل صورة الملف الشخصي
            if ($request->hasFile('profile_image')) {
                // Log file information
                \Illuminate\Support\Facades\Log::info('Profile image file info:', [
                    'original_name' => $request->file('profile_image')->getClientOriginalName(),
                    'size' => $request->file('profile_image')->getSize(),
                    'mime' => $request->file('profile_image')->getMimeType()
                ]);
                
                // حذف الصورة القديمة إذا وجدت
                if ($user->profile_image_url && file_exists(public_path($user->profile_image_url))) {
                    @unlink(public_path($user->profile_image_url));
                    \Illuminate\Support\Facades\Log::info('Deleted old profile image: ' . $user->profile_image_url);
                }
                
                $image = $request->file('profile_image');
                $filename = time() . '_' . $user->id . '.' . $image->getClientOriginalExtension();
                $uploadPath = public_path('uploads/profile_images');
                
                // Make sure the directory exists
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                    \Illuminate\Support\Facades\Log::info('Created directory: ' . $uploadPath);
                }
                
                try {
                    $image->move($uploadPath, $filename);
                    $userDataToUpdate['profile_image_url'] = 'uploads/profile_images/' . $filename;
                    \Illuminate\Support\Facades\Log::info('Image uploaded successfully to: ' . $uploadPath . '/' . $filename);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error uploading image: ' . $e->getMessage());
                }
            }
            
            \Illuminate\Support\Facades\Log::info('User data to update:', $userDataToUpdate);
            
            if (!empty($userDataToUpdate)) {
                $updateResult = User::where('id', $user->id)->update($userDataToUpdate);
                \Illuminate\Support\Facades\Log::info('User update result:', ['rows_affected' => $updateResult]);
                
                // Refresh user data from database to ensure we have latest values
                $user = User::find($user->id);
            }
            
            // تحديث بيانات العميل
            $clientDataToUpdate = [];
            if (isset($validated['phone_number'])) {
                $clientDataToUpdate['phone_number'] = $validated['phone_number'];
            }
            if (isset($validated['city'])) {
                $clientDataToUpdate['city'] = $validated['city'];
            }
            
            \Illuminate\Support\Facades\Log::info('Client data to update:', $clientDataToUpdate);
            
            if (!empty($clientDataToUpdate)) {
                $updateResult = Client::where('client_id', $client->client_id)->update($clientDataToUpdate);
                \Illuminate\Support\Facades\Log::info('Client update result:', ['rows_affected' => $updateResult]);
                
                // Refresh client data from database
                $client = Client::find($client->client_id);
            }
            
            // تحضير الرابط الكامل لصورة الملف الشخصي
            $profileImageUrl = $user->profile_image_url ? url($user->profile_image_url) : null;
            
            // Log the final data after update
            \Illuminate\Support\Facades\Log::info('Updated user data:', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'client_id' => $client->client_id,
                'phone_number' => $client->phone_number,
                'city' => $client->city,
                'profile_image_url' => $profileImageUrl
            ]);
            
            return response()->json([
                'message' => 'تم تحديث الملف الشخصي بنجاح.',
                'success' => true,
                'data_received' => $request->all(),
                'validated_data' => $validated,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image_url' => $profileImageUrl,
                    'client' => [
                        'client_id' => $client->client_id,
                        'phone_number' => $client->phone_number,
                        'city' => $client->city,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating profile: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث الملف الشخصي.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
