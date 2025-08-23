<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\DatabaseNotification;

class CreateSampleNotifications extends Command
{
    protected $signature = 'notifications:sample {user_id?}';
    protected $description = 'Create sample notifications for testing';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if (!$userId) {
            $firstUser = User::first();
            $userId = $firstUser ? $firstUser->id : null;
        }
        
        if (!$userId) {
            $this->error('No user found. Please specify a user ID.');
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error('User not found.');
            return;
        }

        // Sample notifications
        $notifications = [
            [
                'title' => 'Business Approved',
                'body' => 'Your business "Test Restaurant" has been approved and is now live!',
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success'
            ],
            [
                'title' => 'New Review Received',
                'body' => 'You received a 5-star review from a customer.',
                'icon' => 'heroicon-o-star',
                'color' => 'warning'
            ],
            [
                'title' => 'Offer Created Successfully',
                'body' => 'Your offer "30% Off Student Meals" is now active.',
                'icon' => 'heroicon-o-gift',
                'color' => 'info'
            ],
            [
                'title' => 'System Announcement',
                'body' => 'New features have been added to the admin panel.',
                'icon' => 'heroicon-o-megaphone',
                'color' => 'primary'
            ]
        ];

        foreach ($notifications as $notification) {
            $user->notify(new DatabaseNotification(
                $notification['title'],
                $notification['body'],
                $notification['icon'],
                $notification['color']
            ));
        }

        $this->info("Created " . count($notifications) . " sample notifications for user: {$user->name}");
    }
}
