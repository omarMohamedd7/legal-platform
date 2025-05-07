<?php

namespace App\Http\Controllers;

use App\Models\Lawyer;
use Illuminate\Http\Request;

class LawyerController extends Controller
{
                         

    // ✅ عرض كل المحامين
    public function index()
    {
        $lawyers = Lawyer::with('user')->get();
        return response()->json($lawyers);
    }

    // ✅ عرض محامي محدد
    public function show($id)
    {
        $lawyer = Lawyer::with('user')->findOrFail($id);
        return response()->json($lawyer);
    }

    // ✅ تعديل محامي
    public function update(Request $request, $id)
    {
        $lawyer = Lawyer::with('user')->findOrFail($id);
    
        // تحقق من صحة البيانات
        $validated = $request->validate([
            'specialization' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'consult_fee' => 'nullable|numeric|min:0',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $lawyer->user->id,
            'profile_pic' => 'nullable|string|max:255', // أو image حسب حالتك
        ]);
    
        // تحديث جدول lawyers
        $lawyer->update([
            'specialization' => $validated['specialization'] ?? $lawyer->specialization,
            'phone' => $validated['phone'] ?? $lawyer->phone,
            'city' => $validated['city'] ?? $lawyer->city,
            'consult_fee' => $validated['consult_fee'] ?? $lawyer->consult_fee,
        ]);
    
        // تحديث جدول users المرتبط
        $lawyer->user->update([
            'name' => $validated['name'] ?? $lawyer->user->name,
            'email' => $validated['email'] ?? $lawyer->user->email,
            'profile_pic' => $validated['profile_pic'] ?? $lawyer->user->profile_pic,
        ]);
    
        return response()->json([
            'message' => 'Lawyer updated successfully',
            'lawyer' => $lawyer->load('user')
        ]);
    }
    

    // ✅ حذف محامي
    public function destroy($id)
{
    $lawyer = Lawyer::findOrFail($id);
    $lawyer->user->delete(); // حذف المستخدم المرتبط
    $lawyer->delete();       // حذف المحامي

    return response()->json(['message' => 'Lawyer and related user deleted']);
}

}
