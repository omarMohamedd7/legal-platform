<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Exception;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    protected $messaging;

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
        
        // Store notification directly in database
        $notification = Notification::create([
            'id' => $notificationId,
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'read' => false,
        ]);
        
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'message' => $notification->message,
            'data' => $notification->data,
            'read' => $notification->read,
            'created_at' => $notification->created_at->timestamp
        ];
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
        $query = Notification::where('user_id', $userId);
        
        if ($onlyUnread) {
            $query->where('read', false);
        }
        
        $total = $query->count();
        $unread = Notification::where('user_id', $userId)->where('read', false)->count();
        
        $notifications = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->toArray();
        
        return [
            'notifications' => $notifications,
            'total' => $total,
            'unread' => $unread
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
        $notification = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->first();
        
        if ($notification) {
            $notification->read = true;
            $notification->save();
            return true;
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
        $count = Notification::where('user_id', $userId)
            ->where('read', false)
            ->update(['read' => true]);
        
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
        return Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();
    }
    
    /**
     * Delete a notification
     *
     * @param int $userId
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification(int $userId, string $notificationId): bool
    {
        $deleted = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->delete();
        
        return $deleted > 0;
    }
}
