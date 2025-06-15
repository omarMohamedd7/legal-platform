<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CreateTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-test-notification {user_id : The ID of the user to send the notification to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test notification for a user';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }
        
        $notification = $notificationService->sendNotification(
            $userId,
            'test',
            'This is a test notification',
            ['test_data' => 'This is test data']
        );
        
        $this->info("Test notification created for user {$userId}");
        $this->table(
            ['ID', 'Type', 'Message', 'Read'],
            [[$notification['id'], $notification['type'], $notification['message'], $notification['read'] ? 'Yes' : 'No']]
        );
        
        return 0;
    }
}
