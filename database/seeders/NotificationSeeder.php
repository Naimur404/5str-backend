<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;
use App\Models\Business;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['customer', 'business_owner']);
        })->get();
        $businesses = Business::all();

        $notificationTemplates = [
            [
                'type' => 'new_review',
                'title' => 'New Review on Your Business',
                'message' => 'Someone left a review on your business listing.',
                'priority' => 'medium'
            ],
            [
                'type' => 'favorite_business_updated',
                'title' => 'Your Favorite Business Updated',
                'message' => 'One of your favorite businesses has new offers.',
                'priority' => 'low'
            ],
            [
                'type' => 'new_offer',
                'title' => 'New Offer Available',
                'message' => 'Check out the latest offer from businesses near you.',
                'priority' => 'high'
            ],
            [
                'type' => 'business_verification',
                'title' => 'Business Verified',
                'message' => 'Your business has been successfully verified.',
                'priority' => 'high'
            ],
            [
                'type' => 'weekly_digest',
                'title' => 'Weekly Business Digest',
                'message' => 'Here are the top businesses and offers this week.',
                'priority' => 'low'
            ],
            [
                'type' => 'promotion',
                'title' => 'Special Promotion',
                'message' => 'Don\'t miss out on these exclusive deals!',
                'priority' => 'medium'
            ]
        ];

        foreach ($users as $user) {
            // Each user gets 3-8 notifications
            $notificationCount = rand(3, 8);
            
            for ($i = 0; $i < $notificationCount; $i++) {
                $template = $notificationTemplates[array_rand($notificationTemplates)];
                $relatedBusiness = $businesses->random();
                
                Notification::create([
                    'user_id' => $user->id,
                    'type' => $template['type'],
                    'title' => $template['title'],
                    'message' => $template['message'],
                    'data' => json_encode([
                        'business_id' => $relatedBusiness->id,
                        'business_name' => $relatedBusiness->business_name,
                        'action_url' => '/businesses/' . $relatedBusiness->slug
                    ]),
                    'priority' => $template['priority'],
                    'is_read' => rand(1, 10) <= 6, // 60% read notifications
                    'read_at' => rand(1, 10) <= 6 ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now()->subDays(rand(1, 60))
                ]);
            }
        }
    }
}
