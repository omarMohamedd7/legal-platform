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
            $user = Auth::user();
            
            // التحقق من أن المستخدم هو عميل
            if (!$user || $user->role !== 'client') {
                return response()->json(['message' => 'غير مصرح. هذه الخدمة متاحة للعملاء فقط.'], 403);
            }
            
        // Use the common profile update method
        return app(ProfileController::class)->updateProfile($request);
    }
}
