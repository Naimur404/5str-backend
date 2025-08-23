<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\DatabaseNotification;

class SendFilamentNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filament:notify {user_id?} {--title=} {--body=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a Filament notification to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $title = $this->option('title') ?? 'Test Notification';
        $body = $this->option('body') ?? 'This is a test notification from Filament.';
        
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
        
        // Create Filament notification
        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-bell')
            ->color('success')
            ->sendToDatabase($user);
        
        $this->info("✅ Filament notification sent to user: {$user->name}");
        $this->info("Title: {$title}");
        $this->info("Body: {$body}");
        
        return 0;
    }
}
