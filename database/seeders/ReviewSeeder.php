<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\Business;
use App\Models\BusinessOffering;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();
        $users = User::all();

        $reviewTemplates = [
            5 => [
                'Excellent service! Highly recommended.',
                'Amazing experience, will definitely come back.',
                'Perfect in every way. 5 stars!',
                'Outstanding quality and service.',
                'Absolutely loved it! Best in the area.',
                'Exceptional experience from start to finish.',
                'Top-notch service and quality.',
                'Couldn\'t be happier with the service.'
            ],
            4 => [
                'Very good experience overall.',
                'Great service with minor room for improvement.',
                'Really good, would recommend to others.',
                'Satisfying experience, good value for money.',
                'Good quality service, quite happy.',
                'Nice place with friendly staff.',
                'Above average service, well maintained.',
                'Good experience, will visit again.'
            ],
            3 => [
                'Average experience, nothing special.',
                'Okay service, could be better.',
                'Decent but not exceptional.',
                'Fair service, reasonable prices.',
                'Standard service, meets expectations.',
                'Acceptable quality for the price.',
                'Not bad, but room for improvement.',
                'Average quality, decent location.'
            ],
            2 => [
                'Below expectations, needs improvement.',
                'Not very satisfied with the service.',
                'Poor quality for the price paid.',
                'Disappointing experience overall.',
                'Service was lacking in many areas.',
                'Not worth the money spent.',
                'Expected better quality and service.',
                'Unsatisfactory experience, won\'t return.'
            ],
            1 => [
                'Terrible experience, very disappointed.',
                'Worst service ever, avoid this place.',
                'Completely unsatisfied with everything.',
                'Poor service and rude staff.',
                'Waste of time and money.',
                'Extremely disappointing, never again.',
                'Awful experience from start to finish.',
                'The worst, would not recommend to anyone.'
            ]
        ];

        foreach ($businesses as $business) {
            // Generate 5-15 reviews per business
            $reviewCount = rand(5, 15);
            
            for ($i = 0; $i < $reviewCount; $i++) {
                // Weight ratings towards higher scores (more realistic)
                $ratingDistribution = [1, 1, 2, 2, 3, 3, 3, 4, 4, 4, 4, 4, 5, 5, 5, 5, 5];
                $rating = $ratingDistribution[array_rand($ratingDistribution)];
                
                $reviewTexts = $reviewTemplates[$rating];
                $reviewText = $reviewTexts[array_rand($reviewTexts)];
                
                Review::create([
                    'user_id' => $users->random()->id,
                    'reviewable_type' => Business::class,
                    'reviewable_id' => $business->id,
                    'overall_rating' => $rating,
                    'review_text' => $reviewText,
                    'is_verified_visit' => rand(0, 10) > 2, // 80% verified
                    'helpful_count' => rand(0, 25),
                    'status' => 'approved', // All sample reviews are approved
                    'created_at' => now()->subDays(rand(1, 180)),
                    'updated_at' => now()->subDays(rand(1, 180))
                ]);
            }
            
            // Update business rating and review count
            $businessReviews = Review::where('reviewable_type', Business::class)
                ->where('reviewable_id', $business->id);
            
            $averageRating = $businessReviews->avg('overall_rating');
            $totalReviews = $businessReviews->count();
            
            $business->update([
                'overall_rating' => round($averageRating, 1),
                'total_reviews' => $totalReviews
            ]);
        }

        // Generate reviews for business offerings (products/services/menu items)
        $this->seedOfferingReviews($users, $reviewTemplates);
    }

    /**
     * Generate reviews for business offerings
     */
    private function seedOfferingReviews($users, $reviewTemplates)
    {
        $offerings = BusinessOffering::all();

        $offeringSpecificTemplates = [
            5 => [
                'Love this product! Excellent quality.',
                'Perfect menu item, tastes amazing!',
                'This service exceeded my expectations.',
                'Fantastic product, worth every penny.',
                'Best item on their menu!',
                'Outstanding quality, highly recommend this.',
                'Amazing taste and presentation.',
                'Perfect service, professional staff.'
            ],
            4 => [
                'Really good product, satisfied with quality.',
                'Tasty item, good portion size.',
                'Nice service, well executed.',
                'Good value for the price paid.',
                'Quality product, would order again.',
                'Fresh and well-prepared.',
                'Good service, friendly staff.',
                'Above average quality, recommended.'
            ],
            3 => [
                'Average product, nothing special.',
                'Okay taste, standard quality.',
                'Decent service, meets expectations.',
                'Fair quality for the price.',
                'Standard item, acceptable.',
                'Normal quality, average taste.',
                'Basic service, gets the job done.',
                'Reasonable but not exceptional.'
            ],
            2 => [
                'Below expectations, poor quality.',
                'Not fresh, disappointing taste.',
                'Service was slow and unprofessional.',
                'Overpriced for the quality offered.',
                'Not satisfied with this item.',
                'Poor presentation, bland taste.',
                'Service needs major improvement.',
                'Not worth the money spent.'
            ],
            1 => [
                'Terrible quality, completely disappointed.',
                'Awful taste, very poor quality.',
                'Worst service ever experienced.',
                'Complete waste of money.',
                'Poor quality, never ordering this again.',
                'Disgusting taste, avoid this item.',
                'Unprofessional service, very disappointed.',
                'Extremely poor quality and service.'
            ]
        ];

        foreach ($offerings as $offering) {
            // Generate 2-8 reviews per offering (fewer than business reviews)
            $reviewCount = rand(2, 8);
            
            for ($i = 0; $i < $reviewCount; $i++) {
                // Weight ratings towards higher scores
                $ratingDistribution = [2, 2, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5];
                $rating = $ratingDistribution[array_rand($ratingDistribution)];
                
                // Use offering-specific templates if available, fallback to general templates
                $reviewTexts = $offeringSpecificTemplates[$rating] ?? $reviewTemplates[$rating];
                $reviewText = $reviewTexts[array_rand($reviewTexts)];
                
                Review::create([
                    'user_id' => $users->random()->id,
                    'reviewable_type' => BusinessOffering::class,
                    'reviewable_id' => $offering->id,
                    'overall_rating' => $rating,
                    'review_text' => $reviewText,
                    'is_verified_visit' => rand(0, 10) > 3, // 70% verified for offerings
                    'helpful_count' => rand(0, 15),
                    'status' => 'approved',
                    'created_at' => now()->subDays(rand(1, 120)),
                    'updated_at' => now()->subDays(rand(1, 120))
                ]);
            }
            
            // Update offering rating and review count
            $offeringReviews = Review::where('reviewable_type', BusinessOffering::class)
                ->where('reviewable_id', $offering->id);
            
            $averageRating = $offeringReviews->avg('overall_rating');
            $totalReviews = $offeringReviews->count();
            
            $offering->update([
                'average_rating' => round($averageRating, 1),
                'total_reviews' => $totalReviews
            ]);
        }
    }
}
