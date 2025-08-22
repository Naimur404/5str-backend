<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::where('level', '<=', 2)->get();
        $users = User::take(5)->get();

        $businesses = [
            // Restaurants
            [
                'business_name' => 'Pizza Hut Dhanmondi',
                'slug' => 'pizza-hut-dhanmondi',
                'description' => 'Delicious pizza with authentic Italian taste. Best pizza place in Dhanmondi area.',
                'category_id' => Category::where('slug', 'restaurants')->first()->id,
                'subcategory_id' => Category::where('slug', 'pizza')->first()->id,
                'business_email' => 'info@pizzahut-dhanmondi.com',
                'business_phone' => '+880171234567',
                'website_url' => 'https://pizzahut.com.bd',
                'full_address' => 'House 12, Road 7, Dhanmondi, Dhaka 1205',
                'latitude' => 23.7465,
                'longitude' => 90.3754,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Near Dhanmondi 27',
                'opening_hours' => [
                    'monday' => '11:00 AM - 11:00 PM',
                    'tuesday' => '11:00 AM - 11:00 PM',
                    'wednesday' => '11:00 AM - 11:00 PM',
                    'thursday' => '11:00 AM - 11:00 PM',
                    'friday' => '11:00 AM - 11:30 PM',
                    'saturday' => '11:00 AM - 11:30 PM',
                    'sunday' => '11:00 AM - 11:00 PM'
                ],
                'price_range' => 3,
                'has_delivery' => true,
                'has_pickup' => true,
                'has_parking' => true,
                'is_verified' => true,
                'is_featured' => true,
                'approval_status' => 'approved',
                'approved_by' => 1, // Admin user ID
                'approved_at' => now(),
                'overall_rating' => 4.3,
                'total_reviews' => 127,
                'discovery_score' => 85.5
            ],
            [
                'business_name' => 'Star Kabab & Restaurant',
                'slug' => 'star-kabab-restaurant',
                'description' => 'Traditional Bengali and Chinese cuisine. Famous for biryani and kabab.',
                'category_id' => Category::where('slug', 'restaurants')->first()->id,
                'subcategory_id' => Category::where('slug', 'bengali')->first()->id,
                'business_email' => 'contact@starkabab.com',
                'business_phone' => '+880181234567',
                'full_address' => 'Shop 15, Mirpur Road, Dhanmondi, Dhaka',
                'latitude' => 23.7514,
                'longitude' => 90.3790,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Opposite City Hospital',
                'opening_hours' => [
                    'monday' => '12:00 PM - 10:00 PM',
                    'tuesday' => '12:00 PM - 10:00 PM',
                    'wednesday' => '12:00 PM - 10:00 PM',
                    'thursday' => '12:00 PM - 10:00 PM',
                    'friday' => '12:00 PM - 10:30 PM',
                    'saturday' => '12:00 PM - 10:30 PM',
                    'sunday' => '12:00 PM - 10:00 PM'
                ],
                'price_range' => 2,
                'has_delivery' => true,
                'has_pickup' => true,
                'has_parking' => false,
                'is_verified' => true,
                'is_featured' => false,
                'approval_status' => 'approved',
                'approved_by' => 1, // Admin user ID
                'approved_at' => now(),
                'overall_rating' => 4.1,
                'total_reviews' => 89,
                'discovery_score' => 78.2
            ],
            // Shopping
            [
                'business_name' => 'Aarong Dhanmondi',
                'slug' => 'aarong-dhanmondi',
                'description' => 'Premium clothing and handicrafts. Best collection of traditional and modern wear.',
                'category_id' => Category::where('slug', 'shopping')->first()->id,
                'subcategory_id' => Category::where('slug', 'clothing')->first()->id,
                'business_email' => 'dhanmondi@aarong.com',
                'business_phone' => '+880191234567',
                'website_url' => 'https://aarong.com',
                'full_address' => 'House 34, Road 11, Dhanmondi, Dhaka',
                'latitude' => 23.7489,
                'longitude' => 90.3801,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Near Abahani Field',
                'opening_hours' => [
                    'monday' => '10:00 AM - 9:00 PM',
                    'tuesday' => '10:00 AM - 9:00 PM',
                    'wednesday' => '10:00 AM - 9:00 PM',
                    'thursday' => '10:00 AM - 9:00 PM',
                    'friday' => '10:00 AM - 9:30 PM',
                    'saturday' => '10:00 AM - 9:30 PM',
                    'sunday' => '10:00 AM - 9:00 PM'
                ],
                'price_range' => 4,
                'has_delivery' => false,
                'has_pickup' => true,
                'has_parking' => true,
                'is_verified' => true,
                'is_featured' => true,
                'approval_status' => 'approved',
                'approved_by' => 1, // Admin user ID
                'approved_at' => now(),
                'overall_rating' => 4.5,
                'total_reviews' => 203,
                'discovery_score' => 92.1
            ],
            // Services
            [
                'business_name' => 'Persona Beauty Salon',
                'slug' => 'persona-beauty-salon',
                'description' => 'Professional beauty salon for women. Hair care, skin care, and bridal makeup.',
                'category_id' => Category::where('slug', 'services')->first()->id,
                'subcategory_id' => Category::where('slug', 'beauty-salon')->first()->id,
                'business_email' => 'info@personabeauty.com',
                'business_phone' => '+880201234567',
                'full_address' => 'Level 3, House 67, Road 15A, Dhanmondi, Dhaka',
                'latitude' => 23.7456,
                'longitude' => 90.3723,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Above Standard Bank',
                'opening_hours' => [
                    'monday' => '9:00 AM - 8:00 PM',
                    'tuesday' => '9:00 AM - 8:00 PM',
                    'wednesday' => '9:00 AM - 8:00 PM',
                    'thursday' => '9:00 AM - 8:00 PM',
                    'friday' => '9:00 AM - 8:00 PM',
                    'saturday' => '9:00 AM - 8:00 PM',
                    'sunday' => '10:00 AM - 6:00 PM'
                ],
                'price_range' => 3,
                'has_delivery' => false,
                'has_pickup' => false,
                'has_parking' => false,
                'is_verified' => true,
                'is_featured' => false,
                'approval_status' => 'approved',
                'approved_by' => 1, // Admin user ID
                'approved_at' => now(),
                'overall_rating' => 4.2,
                'total_reviews' => 156,
                'discovery_score' => 81.7
            ],
            // Entertainment
            [
                'business_name' => 'Star Cineplex Dhanmondi',
                'slug' => 'star-cineplex-dhanmondi',
                'description' => 'Premium movie theater with latest blockbusters. IMAX and 3D available.',
                'category_id' => Category::where('slug', 'entertainment')->first()->id,
                'subcategory_id' => Category::where('slug', 'movie-theater')->first()->id,
                'business_email' => 'dhanmondi@starcineplex.com',
                'business_phone' => '+880211234567',
                'website_url' => 'https://starcineplex.com',
                'full_address' => 'Shimanto Shambhar, 1/B Dhanmondi, Dhaka',
                'latitude' => 23.7501,
                'longitude' => 90.3812,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Dhanmondi Shoping Complex',
                'opening_hours' => [
                    'monday' => '11:00 AM - 12:00 AM',
                    'tuesday' => '11:00 AM - 12:00 AM',
                    'wednesday' => '11:00 AM - 12:00 AM',
                    'thursday' => '11:00 AM - 12:00 AM',
                    'friday' => '11:00 AM - 12:00 AM',
                    'saturday' => '11:00 AM - 12:00 AM',
                    'sunday' => '11:00 AM - 12:00 AM'
                ],
                'price_range' => 3,
                'has_delivery' => false,
                'has_pickup' => false,
                'has_parking' => true,
                'is_verified' => true,
                'is_featured' => true,
                'approval_status' => 'approved',
                'approved_by' => 1, // Admin user ID
                'approved_at' => now(),
                'overall_rating' => 4.4,
                'total_reviews' => 341,
                'discovery_score' => 88.9
            ],
            // Pending approval businesses
            [
                'business_name' => 'New Cafe Corner',
                'slug' => 'new-cafe-corner',
                'description' => 'Cozy cafe with fresh coffee and pastries. Perfect for breakfast and meetings.',
                'category_id' => Category::where('slug', 'restaurants')->first()->id,
                'subcategory_id' => Category::where('slug', 'cafe')->first()->id,
                'business_email' => 'info@newcafecorner.com',
                'business_phone' => '+880221234567',
                'full_address' => 'House 45, Road 8, Dhanmondi, Dhaka',
                'latitude' => 23.7478,
                'longitude' => 90.3756,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Near Dhanmondi Lake',
                'opening_hours' => [
                    'monday' => '7:00 AM - 10:00 PM',
                    'tuesday' => '7:00 AM - 10:00 PM',
                    'wednesday' => '7:00 AM - 10:00 PM',
                    'thursday' => '7:00 AM - 10:00 PM',
                    'friday' => '7:00 AM - 11:00 PM',
                    'saturday' => '7:00 AM - 11:00 PM',
                    'sunday' => '8:00 AM - 10:00 PM'
                ],
                'price_range' => 2,
                'has_delivery' => true,
                'has_pickup' => true,
                'has_parking' => false,
                'is_verified' => false,
                'is_featured' => false,
                'approval_status' => 'pending',
                'overall_rating' => 0,
                'total_reviews' => 0,
                'discovery_score' => 0
            ],
            [
                'business_name' => 'Tech Repair Hub',
                'slug' => 'tech-repair-hub',
                'description' => 'Professional mobile and laptop repair service. Quick and reliable solutions.',
                'category_id' => Category::where('slug', 'services')->first()->id,
                'subcategory_id' => Category::where('slug', 'home-repair')->first()->id,
                'business_email' => 'support@techrepairhub.com',
                'business_phone' => '+880231234567',
                'full_address' => 'Shop 8, Level 2, Dhanmondi Plaza, Dhaka',
                'latitude' => 23.7492,
                'longitude' => 90.3778,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'landmark' => 'Dhanmondi Plaza',
                'opening_hours' => [
                    'monday' => '10:00 AM - 8:00 PM',
                    'tuesday' => '10:00 AM - 8:00 PM',
                    'wednesday' => '10:00 AM - 8:00 PM',
                    'thursday' => '10:00 AM - 8:00 PM',
                    'friday' => '10:00 AM - 8:00 PM',
                    'saturday' => '10:00 AM - 6:00 PM',
                    'sunday' => 'Closed'
                ],
                'price_range' => 2,
                'has_delivery' => false,
                'has_pickup' => true,
                'has_parking' => false,
                'is_verified' => false,
                'is_featured' => false,
                'approval_status' => 'pending',
                'overall_rating' => 0,
                'total_reviews' => 0,
                'discovery_score' => 0
            ]
        ];

        foreach ($businesses as $businessData) {
            if (!isset($businessData['owner_user_id']) && $users->isNotEmpty()) {
                $businessData['owner_user_id'] = $users->random()->id;
            }
            
            Business::create($businessData);
        }

        // Update category business counts
        foreach ($categories as $category) {
            $count = Business::where('category_id', $category->id)
                ->orWhere('subcategory_id', $category->id)
                ->count();
            $category->update(['total_businesses' => $count]);
        }
    }
}
