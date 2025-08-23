<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['admin', 'super-admin', 'moderator', 'business-owner']);
        })->get();
        
        $businesses = Business::take(10)->get();

        $notificationTemplates = [
            [
                'type' => 'App\Notifications\NewReviewNotification',
                'title' => 'New Review on Your Business',
                'message' => 'Someone left a review on your business listing.',
                'priority' => 'medium'
            ],
            [
                'type' => 'App\Notifications\FavoriteBusinessUpdatedNotification',
                'title' => 'Your Favorite Business Updated',
                'message' => 'One of your favorite businesses has new offers.',
                'priority' => 'low'
            ],
            [
                'type' => 'App\Notifications\NewOfferNotification',
                'title' => 'New Offer Available',
                'message' => 'Check out the latest offer from businesses near you.',
                'priority' => 'high'
            ],
            [
                'type' => 'App\Notifications\BusinessVerificationNotification',
                'title' => 'Business Verified',
                'message' => 'Your business has been successfully verified.',
                'priority' => 'high'
            ],
            [
                'type' => 'App\Notifications\WeeklyDigestNotification',
                'title' => 'Weekly Business Digest',
                'message' => 'Here are the top businesses and offers this week.',
                'priority' => 'low'
            ],
            [
                'type' => 'App\Notifications\PromotionNotification',
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
                $relatedBusiness = $businesses->isNotEmpty() ? $businesses->random() : null;
                
                $isRead = rand(1, 10) <= 6; // 60% read notifications
                
                DB::table('notifications')->insert([
                    'id' => Str::uuid(),
                    'type' => $template['type'],
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => $template['title'],
                        'message' => $template['message'],
                        'priority' => $template['priority'],
                        'business_id' => $relatedBusiness?->id,
                        'business_name' => $relatedBusiness?->business_name,
                        'action_url' => $relatedBusiness ? '/businesses/' . $relatedBusiness->slug : null
                    ]),
                    'read_at' => $isRead ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now()->subDays(rand(1, 60))
                ]);
            }
        }
    }
}
