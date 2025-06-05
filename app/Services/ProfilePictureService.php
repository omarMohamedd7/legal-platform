<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class ProfilePictureService
{
    /**
     * Upload a profile picture for a user
     *
     * @param User $user
     * @param UploadedFile $file
     * @return array
     */
    public function upload(User $user, UploadedFile $file): array
    {
        // Create a unique filename
        $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
        
        // Ensure the directory exists
        $uploadPath = public_path('profile_pics');
        if (!file_exists($uploadPath)) {
            if (!mkdir($uploadPath, 0755, true)) {
                throw new \Exception("Failed to create directory: $uploadPath");
            }
        }
        
        // Move the file to the public/profile_pics directory
        if (!$file->move($uploadPath, $filename)) {
            throw new \Exception("Failed to move uploaded file");
        }
        
        // Store the relative path in the database
        $relativePath = 'profile_pics/' . $filename;
        $user->profile_image_url = $relativePath;
        $user->save();
        
        // Generate the full URL
        $fullUrl = url($relativePath);
        
        return [
            'relative_path' => $relativePath,
            'full_url' => $fullUrl
        ];
    }
    
    /**
     * Delete a user's profile picture
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        if (!$user->profile_image_url) {
            return false;
        }
        
        // Get the full path
        $fullPath = public_path($user->profile_image_url);
        
        // Delete the file if it exists
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        // Clear the profile_image_url field
        $user->profile_image_url = null;
        $user->save();
        
        return true;
    }
    
    /**
     * Get the full URL of a user's profile picture
     *
     * @param User $user
     * @return string|null
     */
    public function getFullUrl(User $user): ?string
    {
        if (!$user->profile_image_url) {
            return null;
        }
        
        return url($user->profile_image_url);
    }
} 