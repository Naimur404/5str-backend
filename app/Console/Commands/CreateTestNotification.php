<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\TestNotification;

class CreateTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test {user_id?} {--title=} {--message=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test notification for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $title = $this->option('title') ?? 'Test Notification';
        $message = $this->option('message') ?? 'This is a test notification from the command line.';
        
        if (!$userId) {
            // Get first admin user
            $user = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super-admin']);
            })->first();
            
            if (!$user) {
                $this->error('No admin users found. Please create an admin user first.');
                return 1;
            }
            
            $userId = $user->id;
            $this->info("Using admin user: {$user->name} (ID: {$userId})");
        } else {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        }
        
        // Send notification using Laravel's notification system
        $user->notify(new TestNotification($title, $message, 'high'));
        
        $this->info("✅ Test notification sent to user: {$user->name}");
        $this->info("Title: {$title}");
        $this->info("Message: {$message}");
        
        return 0;
    }
}
