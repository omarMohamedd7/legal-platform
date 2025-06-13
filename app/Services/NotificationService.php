<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class NotificationService
{
    protected $messaging;

    /**
     * Notification retention period in seconds
     */
    private const NOTIFICATION_RETENTION = 2592000; // 30 days

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(config('services.firebase.config'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, array $data = [])
    {
        if (!$user->fcm_token) {
            Log::warning("User {$user->id} has no FCM token.");
            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(MessagingNotification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
        } catch (Exception $e) {
            Log::error("FCM Error: " . $e->getMessage());
        }
    }

    public function sendToMany(array $tokens, string $title, string $body, array $data = [])
    {
        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification(MessagingNotification::create($title, $body))
                    ->withData($data);

                $this->messaging->send($message);
            } catch (Exception $e) {
                Log::error("FCM Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Send a notification to a user
     *
     * @param int $userId
     * @param string $type
     * @param string $message
     * @param array $data
     * @return array
     */
    public function sendNotification(int $userId, string $type, string $message, array $data = []): array
    {
        $notificationId = Str::uuid()->toString();
        $timestamp = now()->timestamp;
        
        $notification = [
            'id' => $notificationId,
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'read' => false,
            'created_at' => $timestamp
        ];
        
        // Store notification in Redis sorted set with timestamp as score
        Redis::zadd(
            "user:{$userId}:notifications", 
            $timestamp, 
            json_encode($notification)
        );
        
        // Set expiry on notifications
        Redis::expire("user:{$userId}:notifications", self::NOTIFICATION_RETENTION);
        
        // Publish notification to Redis channel for real-time updates
        Redis::publish(
            "user-notifications:{$userId}", 
            json_encode($notification)
        );
        
        return $notification;
    }
    
    /**
     * Get user notifications
     *
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @param bool $onlyUnread
     * @return array
     */
    public function getNotifications(int $userId, int $limit = 20, int $offset = 0, bool $onlyUnread = false): array
    {
        // Get notifications from Redis sorted set with pagination
        $notificationsData = Redis::zrevrange(
            "user:{$userId}:notifications", 
            $offset, 
            $offset + $limit - 1
        );
        
        $notifications = [];
        foreach ($notificationsData as $notificationJson) {
            $notification = json_decode($notificationJson, true);
            
            // Filter unread if requested
            if ($onlyUnread && $notification['read']) {
                continue;
            }
            
            $notifications[] = $notification;
        }
        
        return [
            'notifications' => $notifications,
            'total' => Redis::zcard("user:{$userId}:notifications"),
            'unread' => $this->countUnreadNotifications($userId)
        ];
    }
    
    /**
     * Mark notification as read
     *
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function markAsRead(int $userId, string $notificationId): bool
    {
        // Get all notifications
        $notificationsData = Redis::zrange(
            "user:{$userId}:notifications", 
            0, 
            -1
        );
        
        foreach ($notificationsData as $notificationJson) {
            $notification = json_decode($notificationJson, true);
            
            if ($notification['id'] === $notificationId) {
                // Mark as read
                $notification['read'] = true;
                
                // Get score (timestamp) of the notification
                $score = Redis::zscore("user:{$userId}:notifications", $notificationJson);
                
                // Remove old notification
                Redis::zremrangebyscore(
                    "user:{$userId}:notifications", 
                    $score, 
                    $score
                );
                
                // Add updated notification with same score
                Redis::zadd(
                    "user:{$userId}:notifications", 
                    $score, 
                    json_encode($notification)
                );
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mark all notifications as read
     *
     * @param int $userId
     * @return int
     */
    public function markAllAsRead(int $userId): int
    {
        // Get all notifications
        $notificationsData = Redis::zrange(
            "user:{$userId}:notifications", 
            0, 
            -1
        );
        
        $count = 0;
        foreach ($notificationsData as $notificationJson) {
            $notification = json_decode($notificationJson, true);
            
            if (!$notification['read']) {
                // Mark as read
                $notification['read'] = true;
                
                // Get score (timestamp) of the notification
                $score = Redis::zscore("user:{$userId}:notifications", $notificationJson);
                
                // Remove old notification
                Redis::zremrangebyscore(
                    "user:{$userId}:notifications", 
                    $score, 
                    $score
                );
                
                // Add updated notification with same score
                Redis::zadd(
                    "user:{$userId}:notifications", 
                    $score, 
                    json_encode($notification)
                );
                
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Count unread notifications
     *
     * @param int $userId
     * @return int
     */
    public function countUnreadNotifications(int $userId): int
    {
        // Get all notifications
        $notificationsData = Redis::zrange(
            "user:{$userId}:notifications", 
            0, 
            -1
        );
        
        $unreadCount = 0;
        foreach ($notificationsData as $notificationJson) {
            $notification = json_decode($notificationJson, true);
            if (!$notification['read']) {
                $unreadCount++;
            }
        }
        
        return $unreadCount;
    }
    
    /**
     * Delete notification
     *
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification(int $userId, string $notificationId): bool
    {
        // Get all notifications
        $notificationsData = Redis::zrange(
            "user:{$userId}:notifications", 
            0, 
            -1
        );
        
        foreach ($notificationsData as $notificationJson) {
            $notification = json_decode($notificationJson, true);
            
            if ($notification['id'] === $notificationId) {
                // Get score (timestamp) of the notification
                $score = Redis::zscore("user:{$userId}:notifications", $notificationJson);
                
                // Remove notification
                Redis::zremrangebyscore(
                    "user:{$userId}:notifications", 
                    $score, 
                    $score
                );
                
                return true;
            }
        }
        
        return false;
    }
}
