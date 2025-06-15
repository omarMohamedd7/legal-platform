<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:list-test-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all test users for login testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->error('No users found in the database.');
            return 1;
        }
        
        $this->info('Test users available for login:');
        $this->info('All users have the password: "password"');
        $this->info('');
        
        $headers = ['ID', 'Name', 'Email', 'Role'];
        $rows = [];
        
        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->role
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->info('');
        $this->info('To login with these users, use the email and password "password"');
        
        return 0;
    }
}
