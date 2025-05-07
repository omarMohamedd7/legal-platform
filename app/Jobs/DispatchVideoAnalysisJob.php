<?php

namespace App\Jobs;

use App\Models\VideoAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchVideoAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The video analysis instance.
     *
     * @var \App\Models\VideoAnalysis
     */
    protected $videoAnalysis;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\VideoAnalysis  $videoAnalysis
     * @return void
     */
    public function __construct(VideoAnalysis $videoAnalysis)
    {
        $this->videoAnalysis = $videoAnalysis;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting video analysis for video ID: ' . $this->videoAnalysis->id);

            // Update status to Processing
            $this->videoAnalysis->status = 'Processing';
            $this->videoAnalysis->save();

            // Simulate AI processing time (2-5 seconds)
            sleep(rand(2, 5));

            // In a real implementation, this is where you would call the AI service
            // For now, we'll just simulate a random result
            $this->simulateAiAnalysis();

            Log::info('Completed video analysis for video ID: ' . $this->videoAnalysis->id);
        } catch (\Exception $e) {
            Log::error('Error during video analysis: ' . $e->getMessage());
            
            // Set status back to Pending so it can be retried
            $this->videoAnalysis->status = 'Pending';
            $this->videoAnalysis->save();
            
            throw $e;
        }
    }

    /**
     * Simulate AI analysis with random results.
     * This method would be replaced with actual AI service integration.
     *
     * @return void
     */
    private function simulateAiAnalysis()
    {
        // Generate a random result
        $resultOptions = ['Truthful', 'Deceptive', 'Inconclusive'];
        $randomIndex = array_rand($resultOptions);
        $result = $resultOptions[$randomIndex];

        // Generate a confidence score
        $confidenceScore = rand(65, 99);

        // Generate notes based on the result
        $notes = "AI-generated analysis indicates ";
        if ($result === 'Truthful') {
            $notes .= "the subject appears to be telling the truth with {$confidenceScore}% confidence. Facial expressions and micro-movements are consistent with truthful statements. Voice analysis shows normal stress levels.";
        } elseif ($result === 'Deceptive') {
            $notes .= "the subject may be deceptive with {$confidenceScore}% confidence. Detected inconsistencies in facial micro-expressions and elevated stress markers in voice patterns.";
        } else {
            $notes .= "inconclusive results. Confidence level: {$confidenceScore}%. Unable to determine truthfulness due to insufficient data or mixed signals.";
        }

        // Update the video analysis with results
        $this->videoAnalysis->status = 'Completed';
        $this->videoAnalysis->result = $result;
        $this->videoAnalysis->notes = $notes;
        $this->videoAnalysis->save();
    }
} 