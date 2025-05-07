<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchVideoAnalysisJob;
use App\Models\VideoAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VideoAnalysisController extends Controller
{
    /**
     * Create a new video analysis request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:50000', // 50MB max size
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get the authenticated judge
            $user = Auth::user();
            $judge = $user->judge;

            if (!$judge) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only judges can submit video analysis requests',
                ], 403);
            }

            // Get the uploaded video file
            $videoFile = $request->file('video');

            // Validate video duration (max 10 seconds)
            $videoPath = $videoFile->getPathname();
            $duration = $this->getVideoDuration($videoPath);

            if ($duration > 10) {
                return response()->json([
                    'error' => true,
                    'message' => 'Video duration must not exceed 10 seconds',
                    'duration' => $duration,
                ], 422);
            }

            // Create directory if it doesn't exist
            Storage::disk('public')->makeDirectory('ai_videos');

            // Store the video file
            $filename = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
            $path = $videoFile->storeAs('ai_videos', $filename, 'public');

            // Create a new video analysis record
            $videoAnalysis = VideoAnalysis::create([
                'judge_id' => $judge->judge_id,
                'file_path' => $path,
                'status' => 'Pending',
            ]);

            // Dispatch the video analysis job
            DispatchVideoAnalysisJob::dispatch($videoAnalysis);

            return response()->json([
                'success' => true,
                'message' => 'Video analysis request submitted successfully',
                'data' => $videoAnalysis,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all video analyses for the authenticated judge.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            // Get the authenticated judge
            $user = Auth::user();
            $judge = $user->judge;

            if (!$judge) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only judges can view video analyses',
                ], 403);
            }

            // Get all video analyses for the judge
            $videoAnalyses = VideoAnalysis::where('judge_id', $judge->judge_id)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'status', 'result', 'created_at', 'file_path']);

            return response()->json([
                'success' => true,
                'data' => $videoAnalyses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific video analysis.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            // Get the authenticated judge
            $user = Auth::user();
            $judge = $user->judge;

            if (!$judge) {
                return response()->json([
                    'error' => true,
                    'message' => 'Only judges can view video analyses',
                ], 403);
            }

            // Get the video analysis
            $videoAnalysis = VideoAnalysis::where('id', $id)
                ->where('judge_id', $judge->judge_id)
                ->first();

            if (!$videoAnalysis) {
                return response()->json([
                    'error' => true,
                    'message' => 'Video analysis not found or unauthorized',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $videoAnalysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the duration of a video file.
     *
     * @param  string  $path
     * @return int
     */
    private function getVideoDuration($path)
    {
        // In a real application, you would use a library like FFmpeg to get the video duration
        // For this example, we'll simulate a random duration between 2 and 15 seconds
        
        // For demonstration purposes only - in a real app, use a proper video analysis library
        // such as FFmpeg or getID3 to determine the actual video duration
        
        // Simulate a video duration check
        $randomDuration = rand(2, 15);
        
        // 70% chance of returning a valid duration (<= 10 seconds)
        if (rand(1, 100) <= 70) {
            return min($randomDuration, 10);
        }
        
        return $randomDuration;
    }
} 