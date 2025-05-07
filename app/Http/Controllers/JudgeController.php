<?php

namespace App\Http\Controllers;

use App\Models\Judge;
use Illuminate\Http\Request;

class JudgeController extends Controller
{
    // ✅ عرض كل القضاة
    public function index()
    {
        $judges = Judge::with('user')->get();
        return response()->json($judges);
    }

    // ✅ عرض قاضٍ محدد
    public function show($id)
    {
        $judge = Judge::with('user')->findOrFail($id);
        return response()->json($judge);
    }

    // ✅ تعديل بيانات القاضي
    public function update(Request $request, $id)
    {
        $judge = Judge::findOrFail($id);

        $validated = $request->validate([
            'specialization' => 'sometimes|required|string|max:255',
            'court_name' => 'sometimes|required|string|max:255',
        ]);

        $judge->update($validated);

        return response()->json(['message' => 'Judge updated', 'judge' => $judge]);
    }

    // ✅ حذف قاضٍ
    public function destroy($id)
    {
        $judge = Judge::with('user')->findOrFail($id);
    
        // Delete the associated user
        $judge->user->delete();
    
        // Delete the judge (optional since user deletion will cascade if foreign key is set)
        $judge->delete();
    
        return response()->json(['message' => 'Judge and associated user deleted']);
    }
    
}
