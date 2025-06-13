<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;
use Illuminate\Support\Facades\Log;

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
}
