<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attraction;
use App\Models\AttractionGallery;
use App\Models\AttractionReview;
use App\Models\UserAttractionInteraction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AttractionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample attraction data for Bangladesh
        $attractions = [
            [
                'name' => 'Cox\'s Bazar Beach',
                'description' => 'World\'s longest natural sandy sea beach with stunning sunset views and water activities. Perfect for relaxation and beach sports.',
                'type' => 'attraction',
                'category' => 'Beach',
                'subcategory' => 'Natural Beach',
                'latitude' => 21.4272,
                'longitude' => 92.0058,
                'address' => 'Cox\'s Bazar, Chittagong Division, Bangladesh',
                'city' => 'Cox\'s Bazar',
                'area' => 'Cox\'s Bazar Sadar',
                'district' => 'Cox\'s Bazar',
                'country' => 'Bangladesh',
                'is_free' => true,
                'entry_fee' => 0.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '06:00', 'close' => '20:00'],
                    'tuesday' => ['open' => '06:00', 'close' => '20:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '20:00'],
                    'thursday' => ['open' => '06:00', 'close' => '20:00'],
                    'friday' => ['open' => '06:00', 'close' => '20:00'],
                    'saturday' => ['open' => '06:00', 'close' => '20:00'],
                    'sunday' => ['open' => '06:00', 'close' => '20:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-341-62058',
                    'email' => 'info@coxsbazartourism.gov.bd',
                    'website' => 'https://coxsbazar.gov.bd'
                ]),
                'facilities' => json_encode(['parking', 'restrooms', 'restaurants', 'hotels', 'water_sports', 'lifeguard']),
                'best_time_to_visit' => json_encode(['months' => ['November', 'December', 'January', 'February', 'March']]),
                'estimated_duration_minutes' => 480, // 8 hours
                'difficulty_level' => 'easy',
                'accessibility_info' => json_encode(['wheelchair_accessible' => false, 'parking_available' => true]),
                'overall_rating' => 4.5,
                'total_reviews' => 1250,
                'total_views' => 15000,
                'total_likes' => 8900,
                'total_shares' => 450,
                'discovery_score' => 95.5,
                'is_verified' => true,
                'is_featured' => true,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['beach', 'sunset', 'swimming', 'surfing', 'photography', 'nature']]),
            ],
            [
                'name' => 'Sundarbans Mangrove Forest',
                'description' => 'World\'s largest mangrove forest and UNESCO World Heritage Site. Home to the famous Royal Bengal Tigers.',
                'type' => 'attraction',
                'category' => 'Wildlife',
                'subcategory' => 'National Park',
                'latitude' => 22.4981,
                'longitude' => 89.5403,
                'address' => 'Khulna Division, Bangladesh',
                'city' => 'Satkhira',
                'area' => 'Shyamnagar',
                'district' => 'Satkhira',
                'country' => 'Bangladesh',
                'is_free' => false,
                'entry_fee' => 500.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '08:00', 'close' => '17:00'],
                    'tuesday' => ['open' => '08:00', 'close' => '17:00'],
                    'wednesday' => ['open' => '08:00', 'close' => '17:00'],
                    'thursday' => ['open' => '08:00', 'close' => '17:00'],
                    'friday' => ['open' => '08:00', 'close' => '17:00'],
                    'saturday' => ['open' => '08:00', 'close' => '17:00'],
                    'sunday' => ['open' => '08:00', 'close' => '17:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-4792-56261',
                    'email' => 'info@sundarban.gov.bd',
                    'website' => 'https://forestdept.gov.bd/site/page/d6cc5773-59c8-4f96-a1d3-320a9dc74e6b'
                ]),
                'facilities' => json_encode(['guided_tours', 'boat_rental', 'accommodation', 'restaurants', 'visitor_center']),
                'best_time_to_visit' => json_encode(['months' => ['October', 'November', 'December', 'January', 'February']]),
                'estimated_duration_minutes' => 720, // 12 hours (full day)
                'difficulty_level' => 'moderate',
                'accessibility_info' => json_encode(['wheelchair_accessible' => false, 'boat_required' => true]),
                'overall_rating' => 4.7,
                'total_reviews' => 890,
                'total_views' => 5200,
                'total_likes' => 3400,
                'total_shares' => 280,
                'discovery_score' => 92.3,
                'is_verified' => true,
                'is_featured' => true,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['mangrove', 'tigers', 'wildlife', 'boat_tour', 'nature', 'unesco']]),
            ],
            [
                'name' => 'Ahsan Manzil (Pink Palace)',
                'description' => 'Historic palace museum showcasing the lifestyle of Nawab families of Dhaka. Beautiful Indo-Saracenic Revival architecture.',
                'type' => 'attraction',
                'category' => 'Historical',
                'subcategory' => 'Museum',
                'latitude' => 23.7104,
                'longitude' => 90.4074,
                'address' => 'Kumartoli, Old Dhaka, Dhaka-1100',
                'city' => 'Dhaka',
                'area' => 'Old Dhaka',
                'district' => 'Dhaka',
                'country' => 'Bangladesh',
                'is_free' => false,
                'entry_fee' => 20.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '10:00', 'close' => '17:00'],
                    'tuesday' => ['closed' => true],
                    'wednesday' => ['open' => '10:00', 'close' => '17:00'],
                    'thursday' => ['open' => '10:00', 'close' => '17:00'],
                    'friday' => ['open' => '15:00', 'close' => '17:00'],
                    'saturday' => ['open' => '10:00', 'close' => '17:00'],
                    'sunday' => ['open' => '10:00', 'close' => '17:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-2-7313122',
                    'email' => 'info@ahsanmanzil.org',
                    'website' => 'https://ahsanmanzil.org'
                ]),
                'facilities' => json_encode(['museum', 'guided_tours', 'parking', 'gift_shop', 'restrooms']),
                'best_time_to_visit' => json_encode(['months' => ['October', 'November', 'December', 'January', 'February', 'March']]),
                'estimated_duration_minutes' => 120, // 2 hours
                'difficulty_level' => 'easy',
                'accessibility_info' => json_encode(['wheelchair_accessible' => true, 'parking_available' => true]),
                'overall_rating' => 4.2,
                'total_reviews' => 567,
                'total_views' => 8900,
                'total_likes' => 2100,
                'total_shares' => 150,
                'discovery_score' => 85.7,
                'is_verified' => true,
                'is_featured' => false,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['palace', 'museum', 'history', 'architecture', 'nawab', 'heritage']]),
            ],
            [
                'name' => 'Ratargul Swamp Forest',
                'description' => 'Only swamp forest in Bangladesh, known as the Amazon of Bangladesh. Boat rides through freshwater forest.',
                'type' => 'attraction',
                'category' => 'Nature',
                'subcategory' => 'Forest',
                'latitude' => 25.0968,
                'longitude' => 91.8833,
                'address' => 'Goainghat, Sylhet, Bangladesh',
                'city' => 'Sylhet',
                'area' => 'Goainghat',
                'district' => 'Sylhet',
                'country' => 'Bangladesh',
                'is_free' => false,
                'entry_fee' => 100.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '09:00', 'close' => '17:00'],
                    'tuesday' => ['open' => '09:00', 'close' => '17:00'],
                    'wednesday' => ['open' => '09:00', 'close' => '17:00'],
                    'thursday' => ['open' => '09:00', 'close' => '17:00'],
                    'friday' => ['open' => '09:00', 'close' => '17:00'],
                    'saturday' => ['open' => '09:00', 'close' => '17:00'],
                    'sunday' => ['open' => '09:00', 'close' => '17:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-821-711150',
                    'email' => 'info@ratargul.com',
                    'website' => null
                ]),
                'facilities' => json_encode(['boat_rental', 'guides', 'life_jackets', 'parking', 'restrooms']),
                'best_time_to_visit' => json_encode(['months' => ['June', 'July', 'August', 'September', 'October']]),
                'estimated_duration_minutes' => 180, // 3 hours
                'difficulty_level' => 'easy',
                'accessibility_info' => json_encode(['wheelchair_accessible' => false, 'boat_required' => true]),
                'overall_rating' => 4.4,
                'total_reviews' => 423,
                'total_views' => 3200,
                'total_likes' => 1800,
                'total_shares' => 95,
                'discovery_score' => 87.2,
                'is_verified' => true,
                'is_featured' => true,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['swamp', 'forest', 'boat_ride', 'nature', 'photography', 'unique']]),
            ],
            [
                'name' => 'Paharpur Buddhist Monastery',
                'description' => 'Ancient Buddhist monastery ruins and UNESCO World Heritage Site. Important archaeological site with 8th-century architecture.',
                'type' => 'attraction',
                'category' => 'Archaeological',
                'subcategory' => 'Historic Site',
                'latitude' => 25.0317,
                'longitude' => 88.9764,
                'address' => 'Paharpur, Naogaon, Rajshahi Division',
                'city' => 'Naogaon',
                'area' => 'Badalgachhi',
                'district' => 'Naogaon',
                'country' => 'Bangladesh',
                'is_free' => false,
                'entry_fee' => 30.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '09:00', 'close' => '17:00'],
                    'tuesday' => ['open' => '09:00', 'close' => '17:00'],
                    'wednesday' => ['open' => '09:00', 'close' => '17:00'],
                    'thursday' => ['open' => '09:00', 'close' => '17:00'],
                    'friday' => ['open' => '09:00', 'close' => '17:00'],
                    'saturday' => ['open' => '09:00', 'close' => '17:00'],
                    'sunday' => ['open' => '09:00', 'close' => '17:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-741-61235',
                    'email' => 'info@paharpur.gov.bd',
                    'website' => 'https://archaeology.gov.bd/paharpur'
                ]),
                'facilities' => json_encode(['museum', 'guided_tours', 'parking', 'information_center']),
                'best_time_to_visit' => json_encode(['months' => ['November', 'December', 'January', 'February', 'March']]),
                'estimated_duration_minutes' => 90, // 1.5 hours
                'difficulty_level' => 'easy',
                'accessibility_info' => json_encode(['wheelchair_accessible' => true, 'parking_available' => true]),
                'overall_rating' => 4.1,
                'total_reviews' => 234,
                'total_views' => 1800,
                'total_likes' => 890,
                'total_shares' => 45,
                'discovery_score' => 78.9,
                'is_verified' => true,
                'is_featured' => false,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['buddhist', 'monastery', 'ruins', 'unesco', 'archaeology', 'ancient']]),
            ],
            [
                'name' => 'Kuakata Beach',
                'description' => 'Panoramic sea beach where you can see both sunrise and sunset. Known as the daughter of the sea.',
                'type' => 'attraction',
                'category' => 'Beach',
                'subcategory' => 'Natural Beach',
                'latitude' => 21.8174,
                'longitude' => 90.1198,
                'address' => 'Kuakata, Patuakhali, Barisal Division',
                'city' => 'Patuakhali',
                'area' => 'Kalapara',
                'district' => 'Patuakhali',
                'country' => 'Bangladesh',
                'is_free' => true,
                'entry_fee' => 0.00,
                'currency' => 'BDT',
                'opening_hours' => json_encode([
                    'monday' => ['open' => '06:00', 'close' => '20:00'],
                    'tuesday' => ['open' => '06:00', 'close' => '20:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '20:00'],
                    'thursday' => ['open' => '06:00', 'close' => '20:00'],
                    'friday' => ['open' => '06:00', 'close' => '20:00'],
                    'saturday' => ['open' => '06:00', 'close' => '20:00'],
                    'sunday' => ['open' => '06:00', 'close' => '20:00'],
                ]),
                'contact_info' => json_encode([
                    'phone' => '+880-4427-56108',
                    'email' => 'info@kuakata.gov.bd',
                    'website' => null
                ]),
                'facilities' => json_encode(['hotels', 'restaurants', 'parking', 'beach_activities']),
                'best_time_to_visit' => json_encode(['months' => ['November', 'December', 'January', 'February', 'March']]),
                'estimated_duration_minutes' => 480, // 8 hours
                'difficulty_level' => 'easy',
                'accessibility_info' => json_encode(['wheelchair_accessible' => false, 'parking_available' => true]),
                'overall_rating' => 4.3,
                'total_reviews' => 678,
                'total_views' => 4500,
                'total_likes' => 2800,
                'total_shares' => 190,
                'discovery_score' => 89.1,
                'is_verified' => true,
                'is_featured' => true,
                'is_active' => true,
                'status' => 'active',
                'meta_data' => json_encode(['tags' => ['beach', 'sunrise', 'sunset', 'panoramic', 'nature', 'photography']]),
            ]
        ];

        foreach ($attractions as $attractionData) {
            $attraction = Attraction::create($attractionData);

            // Create sample gallery images for each attraction
            $this->createSampleGallery($attraction);

            // Create sample reviews for each attraction
            $this->createSampleReviews($attraction);

            // Create sample user interactions
            $this->createSampleInteractions($attraction);
        }
    }

    /**
     * Create sample gallery images for an attraction
     */
    private function createSampleGallery(Attraction $attraction): void
    {
        $galleryImages = [
            [
                'attraction_id' => $attraction->id,
                'image_path' => 'attractions/' . strtolower(str_replace(' ', '_', $attraction->name)) . '_1.jpg',
                'image_url' => 'https://picsum.photos/800/600?random=' . $attraction->id . '1',
                'title' => 'Main View',
                'description' => 'Main view of ' . $attraction->name,
                'is_cover' => true,
                'sort_order' => 1,
            ],
            [
                'attraction_id' => $attraction->id,
                'image_path' => 'attractions/' . strtolower(str_replace(' ', '_', $attraction->name)) . '_2.jpg',
                'image_url' => 'https://picsum.photos/800/600?random=' . $attraction->id . '2',
                'title' => 'Scenic View',
                'description' => 'Beautiful scenic view',
                'is_cover' => false,
                'sort_order' => 2,
            ],
            [
                'attraction_id' => $attraction->id,
                'image_path' => 'attractions/' . strtolower(str_replace(' ', '_', $attraction->name)) . '_3.jpg',
                'image_url' => 'https://picsum.photos/800/600?random=' . $attraction->id . '3',
                'title' => 'Detail Shot',
                'description' => 'Detailed view of the attraction',
                'is_cover' => false,
                'sort_order' => 3,
            ]
        ];

        foreach ($galleryImages as $imageData) {
            AttractionGallery::create($imageData);
        }
    }

    /**
     * Create sample reviews for an attraction
     */
    private function createSampleReviews(Attraction $attraction): void
    {
        $users = User::inRandomOrder()->limit(rand(3, 8))->get();
        
        if ($users->isEmpty()) {
            return; // Skip if no users exist
        }

        $reviewTexts = [
            "Amazing place! Absolutely loved the experience and would definitely recommend to others.",
            "Great spot for family visits. The kids enjoyed it a lot and we had a wonderful time.",
            "Beautiful location with stunning views. Perfect for photography enthusiasts.",
            "Good place to visit but can get crowded during weekends. Plan accordingly.",
            "Excellent facilities and well-maintained. Staff was very helpful and friendly.",
            "Nice attraction with rich history. Educational and entertaining at the same time.",
            "Peaceful and serene environment. Great place to relax and unwind from city life.",
            "Worth the visit! The natural beauty here is breathtaking and truly impressive.",
        ];

        $experienceTags = ['family-friendly', 'romantic', 'adventure', 'educational', 'relaxing', 'photography'];

        foreach ($users as $user) {
            AttractionReview::create([
                'attraction_id' => $attraction->id,
                'user_id' => $user->id,
                'rating' => rand(35, 50) / 10, // 3.5 to 5.0
                'title' => 'Great experience!',
                'comment' => $reviewTexts[array_rand($reviewTexts)],
                'visit_info' => json_encode([
                    'visit_date' => now()->subDays(rand(1, 365))->format('Y-m-d'),
                    'duration_minutes' => rand(60, 480),
                    'companions' => rand(1, 6)
                ]),
                'experience_tags' => json_encode(array_slice($experienceTags, 0, rand(1, 3))),
                'is_verified' => rand(0, 100) > 70, // 30% verified
                'helpful_votes' => rand(0, 15),
                'total_votes' => rand(0, 20),
                'visit_date' => now()->subDays(rand(1, 365)),
                'status' => 'active',
            ]);
        }
    }

    /**
     * Create sample user interactions for an attraction
     */
    private function createSampleInteractions(Attraction $attraction): void
    {
        $users = User::inRandomOrder()->limit(rand(10, 25))->get();
        
        if ($users->isEmpty()) {
            return; // Skip if no users exist
        }

        $interactionTypes = ['like', 'bookmark', 'visit', 'share'];

        foreach ($users as $user) {
            // Each user might have multiple interaction types
            $userInteractionTypes = array_slice($interactionTypes, 0, rand(1, 3));
            
            foreach ($userInteractionTypes as $type) {
                UserAttractionInteraction::create([
                    'user_id' => $user->id,
                    'attraction_id' => $attraction->id,
                    'interaction_type' => $type,
                    'created_at' => now()->subDays(rand(1, 180)),
                ]);
            }
        }
    }
}
