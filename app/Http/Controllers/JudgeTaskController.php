<?php

namespace App\Http\Controllers;

use App\Models\JudgeTask;
use App\Http\Resources\JudgeTaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JudgeTaskController extends Controller
{
    /**
     * Display a listing of the judge's tasks.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        // Check if user is authenticated and is a judge
        $user = Auth::user();
        if (!$user || $user->role !== 'judge') {
            return response()->json(['message' => 'Unauthorized. Only judges can access this resource.'], 403);
        }

        // Get the judge's ID
        $judge = $user->judge;
        if (!$judge) {
            return response()->json(['message' => 'Judge profile not found.'], 404);
        }

        // Get all tasks for the authenticated judge
        $tasks = JudgeTask::where('judge_id', $judge->judge_id)
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        // Update status for any overdue tasks
        foreach ($tasks as $task) {
            $task->checkAndUpdateStatus();
        }

        // Return the tasks using the resource
        return response()->json([
            'message' => 'Tasks retrieved successfully',
            'data' => JudgeTaskResource::collection($tasks)
        ]);
    }

    /**
     * Store a newly created task in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Check if user is authenticated and is a judge
        $user = Auth::user();
        if (!$user || $user->role !== 'judge') {
            return response()->json(['message' => 'Unauthorized. Only judges can create tasks.'], 403);
        }

        // Get the judge's ID
        $judge = $user->judge;
        if (!$judge) {
            return response()->json(['message' => 'Judge profile not found.'], 404);
        }

        // Validate the request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'execution_date' => 'required|date|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'task_type' => 'required|string|max:50',
            'reminder_enabled' => 'boolean',
        ]);

        // Create the task for the authenticated judge
        $task = JudgeTask::create([
            'judge_id' => $judge->judge_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'date' => $validated['execution_date'],
            'time' => $validated['start_time'],
            'task_type' => $validated['task_type'],
            'reminder_enabled' => $validated['reminder_enabled'] ?? false,
            'status' => 'pending',
        ]);

        // Return the created task
        return response()->json([
            'message' => 'Task created successfully',
            'data' => new JudgeTaskResource($task)
        ], 201);
    }

    /**
     * Update the specified task in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Check if user is authenticated and is a judge
        $user = Auth::user();
        if (!$user || $user->role !== 'judge') {
            return response()->json(['message' => 'Unauthorized. Only judges can update tasks.'], 403);
        }

        // Find the task
        $task = JudgeTask::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        // Check if the task belongs to the authenticated judge
        if ($task->judge_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only update your own tasks.'], 403);
        }

        // Validate the request
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'sometimes|required|date|date_format:Y-m-d',
            'time' => 'sometimes|required|date_format:H:i',
            'status' => 'sometimes|in:pending,completed',
        ]);

        // Update the task
        $task->update($validated);

        // Return the updated task using the resource
        return new JudgeTaskResource($task);
    }

    /**
     * Remove the specified task from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // Check if user is authenticated and is a judge
        $user = Auth::user();
        if (!$user || $user->role !== 'judge') {
            return response()->json(['message' => 'Unauthorized. Only judges can delete tasks.'], 403);
        }

        // Find the task
        $task = JudgeTask::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        // Check if the task belongs to the authenticated judge
        if ($task->judge_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only delete your own tasks.'], 403);
        }

        // Delete the task
        $task->delete();

        // Return a success message
        return response()->json(['message' => 'Task deleted successfully.']);
    }

    /**
     * Mark a task as completed.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsCompleted($id)
    {
        // Check if user is authenticated and is a judge
        $user = Auth::user();
        if (!$user || $user->role !== 'judge') {
            return response()->json(['message' => 'Unauthorized. Only judges can mark tasks as completed.'], 403);
        }

        // Find the task
        $task = JudgeTask::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        // Check if the task belongs to the authenticated judge
        if ($task->judge_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only update your own tasks.'], 403);
        }

        // Check if the task is already completed
        if ($task->status === 'completed') {
            return response()->json(['message' => 'Task is already marked as completed.'], 422);
        }

        // Mark the task as completed
        $task->status = 'completed';
        $task->save();

        // Return the updated task using the resource
        return new JudgeTaskResource($task);
    }
} 