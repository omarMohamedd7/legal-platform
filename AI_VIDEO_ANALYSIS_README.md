# AI Video Analysis System for Judges

This feature allows judges to upload short video clips (max 10 seconds) of defendants. These videos are analyzed by an AI system to determine if the person appears to be lying or telling the truth.

## Features

- Upload short videos of defendants (max 10 seconds)
- AI-powered truthfulness analysis
- View analysis results with confidence scores
- Detailed explanation of the analysis
- View history of all submitted videos

## Installation

1. Run the setup script:

```bash
setup-video-analysis.bat
```

Or manually:

```bash
# Create storage directory
mkdir storage\app\public\ai_videos

# Create storage symlink
php artisan storage:link

# Run migrations
php artisan migrate
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/video-analyses` | Upload a video for analysis |
| GET | `/api/video-analyses` | List all analyses for the authenticated judge |
| GET | `/api/video-analyses/{id}` | View details of a specific analysis |

## Request Format

**Upload Video (POST /api/video-analyses)**

This endpoint accepts a multipart form-data request with the following field:

- `video`: The video file to analyze (mp4, mov, or avi format, max 10 seconds)

Example curl command:
```bash
curl -X POST "http://localhost:8000/api/video-analyses" \
  -H "Authorization: Bearer {your_token}" \
  -F "video=@/path/to/your/video.mp4"
```

## Response Format

```json
{
  "success": true,
  "data": {
    "id": 1,
    "judge_id": 1,
    "file_path": "ai_videos/1621234567_60a1b2c3d4e5f.mp4",
    "status": "Completed",
    "result": "Truthful",
    "notes": "AI-generated analysis indicates the subject appears to be telling the truth with 87% confidence. Facial expressions and micro-movements are consistent with truthful statements. Voice analysis shows normal stress levels.",
    "created_at": "2023-05-17T10:30:00.000000Z",
    "updated_at": "2023-05-17T10:30:05.000000Z",
    "video_url": "http://localhost:8000/storage/ai_videos/1621234567_60a1b2c3d4e5f.mp4"
  }
}
```

## Analysis States

The `status` field can have the following values:

- `Pending`: The video has been uploaded but analysis has not yet started
- `Processing`: The AI system is currently analyzing the video
- `Completed`: Analysis is complete and results are available

## Result Types

The `result` field can have the following values:

- `Truthful`: The AI system determined the subject is likely telling the truth
- `Deceptive`: The AI system determined the subject is likely being deceptive
- `Inconclusive`: The AI system could not reach a clear determination

## Security

- Only authenticated users with the Judge role can access these endpoints
- Judges can only view their own video analyses
- Videos are stored securely in a non-public location

## Implementation Notes

The current implementation simulates AI analysis with random results. In a production environment, you would integrate with a real AI service for truthfulness analysis.

The video duration checking is also simulated. In a real implementation, you would use a library like FFmpeg or getID3 to accurately determine video duration. 