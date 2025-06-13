<?php

namespace App\Http\Controllers;

use App\Models\VideoAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VideoAnalysisController extends Controller
{
    /**
     * AI endpoint URL
     */
    protected $aiEndpointUrl;
    
    /**
     * Constructor to initialize properties from environment variables
     */
    public function __construct()
    {
        $this->aiEndpointUrl = env('AI_VIDEO_ANALYSIS_ENDPOINT', 'http://192.168.0.24:8000') . '/analyze';
    }

    /**
     * Create a new video analysis request and process with AI.
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
            $videoName = $videoFile->getClientOriginalName();

            // Create directory if it doesn't exist
            Storage::disk('public')->makeDirectory('ai_videos');

            // Store the video file
            $filename = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
            $path = $videoFile->storeAs('ai_videos', $filename, 'public');
            
            // Get the full path to the stored file
            $fullPath = Storage::disk('public')->path($path);

            // Create a new video analysis record
            $videoAnalysis = VideoAnalysis::create([
                'judge_id' => $judge->judge_id,
                'file_path' => $path,
                'video_name' => $videoName,
            ]);

            // Send to AI endpoint and process result
            $aiResult = $this->processWithAI($fullPath, $videoAnalysis->id);

            if (!$aiResult['success']) {
                return response()->json([
                    'error' => true,
                    'message' => 'AI processing failed: ' . $aiResult['message'],
                    'video_analysis_id' => $videoAnalysis->id
                ], 500);
            }

            // Update video analysis with AI results
            $videoAnalysis->update([
                'duration' => $aiResult['data']['duration'],
                'analysis_date' => Carbon::now(),
                'prediction' => $aiResult['data']['prediction'],
                'confidence' => $aiResult['data']['confidence'],
                'summary' => $aiResult['data']['summary'] ?? null,
            ]);

            // Reload the model to get fresh data
            $videoAnalysis->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Video analysis completed successfully',
                'data' => $videoAnalysis,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Video analysis error: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process video with AI endpoint
     *
     * @param string $videoPath
     * @param int $videoAnalysisId
     * @return array
     */
    private function processWithAI($videoPath, $videoAnalysisId)
    {
        // Check if we're in testing mode
        if (env('AI_VIDEO_ANALYSIS_MOCK', false)) {
            return $this->mockAIProcessing($videoPath);
        }
        
        try {
            // Create a file resource to send to the AI API
            $videoFile = fopen($videoPath, 'r');

            // Send the video to the AI endpoint with a 50-second timeout
            $response = Http::timeout(50)
                ->attach('video', $videoFile)
                ->post($this->aiEndpointUrl, [
                    'video_analysis_id' => $videoAnalysisId
                ]);

            // Close the file resource
            fclose($videoFile);

            // Check if the request was successful
            if ($response->successful()) {
                $data = $response->json();
                
                // Validate required fields in the response
                if (!isset($data['video_name']) || 
                    !isset($data['duration']) || 
                    !isset($data['prediction']) || 
                    !isset($data['confidence'])) {
                    
                    Log::error('AI response missing required fields: ' . json_encode($data));
                    return [
                        'success' => false,
                        'message' => 'AI response missing required fields'
                    ];
                }
                
                return [
                    'success' => true,
                    'data' => [
                        'video_name' => $data['video_name'],
                        'duration' => $data['duration'],
                        'prediction' => $data['prediction'],
                        'confidence' => $data['confidence'],
                        'summary' => $data['summary'] ?? null
                    ]
                ];
            }
            
            Log::error('AI endpoint error: ' . $response->body());
            return [
                'success' => false,
                'message' => 'AI endpoint returned error: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('AI processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'AI processing error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate mock AI processing results for testing
     * 
     * @param string $videoPath
     * @return array
     */
    private function mockAIProcessing($videoPath)
    {
        // Simulate processing delay
        sleep(2);
        
        // Extract filename from path for demo purposes
        $pathInfo = pathinfo($videoPath);
        $filename = $pathInfo['basename'];
        
        // Generate mock AI analysis results
        $mockResults = [
            'video_name' => $filename,
            'duration' => rand(60, 300), // Random duration between 1-5 minutes
            'prediction' => $this->getRandomPrediction(),
            'confidence' => rand(65, 98) + (rand(0, 99) / 100), // Random confidence between 65-98%
            'summary' => $this->generateMockSummary()
        ];
        
        Log::info('Mock AI processing completed', $mockResults);
        
        return [
            'success' => true,
            'data' => $mockResults
        ];
    }
    
    /**
     * Get a random prediction for mock results
     * 
     * @return string
     */
    private function getRandomPrediction()
    {
        $predictions = ['Positive', 'Neutral', 'Negative', 'Attentive', 'Distracted'];
        return $predictions[array_rand($predictions)];
    }
    
    /**
     * Generate a mock summary for testing
     * 
     * @return string
     */
    private function generateMockSummary()
    {
        $summaries = [
            'The judge displayed positive body language and maintained good eye contact throughout the session.',
            'The judge appeared neutral and professional during the proceedings.',
            'The judge seemed slightly distracted at times but maintained overall professionalism.',
            'The judge showed excellent attentiveness to all parties and maintained a fair demeanor.',
            'The judge demonstrated appropriate courtroom management and clear communication.'
        ];
        
        return $summaries[array_rand($summaries)];
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
                ->get();

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
     * Get all video analysis results for a specific judge.
     *
     * @param  int  $judgeId
     * @return \Illuminate\Http\Response
     */
    public function getJudgeResults($judgeId)
    {
        try {
            // Validate the judge exists
            $validator = Validator::make(['judge_id' => $judgeId], [
                'judge_id' => 'required|exists:judges,judge_id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Judge not found',
                    'errors' => $validator->errors(),
                ], 404);
            }

            // Get all video analyses for the judge with AI results
            $videoAnalyses = VideoAnalysis::where('judge_id', $judgeId)
                ->whereNotNull('prediction')
                ->orderBy('analysis_date', 'desc')
                ->with('judge.user:id,name,email')
                ->get();

            // Calculate statistics
            $totalAnalyses = $videoAnalyses->count();
            $averageConfidence = $totalAnalyses > 0 ? $videoAnalyses->avg('confidence') : 0;
            
            // Group by prediction
            $predictionCounts = $videoAnalyses->groupBy('prediction')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'average_confidence' => $group->avg('confidence')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'analyses' => $videoAnalyses,
                    'statistics' => [
                        'total_analyses' => $totalAnalyses,
                        'average_confidence' => round($averageConfidence, 2),
                        'prediction_breakdown' => $predictionCounts
                    ]
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
} 