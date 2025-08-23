<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Review;
use Filament\Notifications\Notification;

class TestNotificationEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notifications {event} {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification events (offer-created, review-approved)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $event = $this->argument('event');
        $userId = $this->argument('user_id') ?? 1;
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        switch ($event) {
            case 'offer-created':
                $this->testOfferCreated($user);
                break;
            case 'review-approved':
                $this->testReviewApproved($user);
                break;
            case 'review-submitted':
                $this->testReviewSubmitted($user);
                break;
            default:
                $this->error('Invalid event. Use: offer-created, review-approved, review-submitted');
                return 1;
        }

        return 0;
    }

    private function testOfferCreated($user)
    {
        // Simulate admin notification for offer creation
        Notification::make()
            ->title('New Offer Added')
            ->body("A new offer 'Summer Sale - 20% Off' has been created for Test Business.")
            ->icon('heroicon-o-gift')
            ->color('info')
            ->sendToDatabase($user);

        $this->info("✅ Offer creation notification sent to: {$user->name}");
    }

    private function testReviewApproved($user)
    {
        // Simulate review approval notification
        Notification::make()
            ->title('Review Approved!')
            ->body("Your review has been approved and is now visible to other users. Thank you for your feedback!")
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->sendToDatabase($user);

        $this->info("✅ Review approval notification sent to: {$user->name}");
    }

    private function testReviewSubmitted($user)
    {
        // Simulate admin notification for review submission
        Notification::make()
            ->title('New Review Submitted')
            ->body("A new review has been submitted for Test Business and is pending approval.")
            ->icon('heroicon-o-star')
            ->color('info')
            ->sendToDatabase($user);

        $this->info("✅ Review submission notification sent to: {$user->name}");
    }
}
