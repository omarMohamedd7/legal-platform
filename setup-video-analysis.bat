@echo off
echo Setting up Video Analysis feature...

echo Creating storage directory for AI videos...
mkdir storage\app\public\ai_videos

echo Ensuring storage link is created...
php artisan storage:link

echo Running migrations...
php artisan migrate

echo Done! Video Analysis feature is ready.
echo.
echo API endpoints:
echo - POST /api/video-analyses (upload a video for analysis)
echo - GET /api/video-analyses (list all analyses)
echo - GET /api/video-analyses/{id} (view a specific analysis)
echo.
echo Note: This API is only accessible to users with the Judge role. 