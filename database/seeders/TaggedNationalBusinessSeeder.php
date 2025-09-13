<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaggedNationalBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create categories
        $foodCategory = Category::firstOrCreate(['name' => 'Food & Beverages'], [
            'slug' => 'food-beverages',
            'description' => 'Food and beverage businesses',
            'icon_image' => 'food-beverages.png',
            'level' => 1,
            'is_active' => true
        ]);

        $dairyCategory = Category::firstOrCreate(['name' => 'Dairy & Ice Cream'], [
            'slug' => 'dairy-ice-cream',
            'description' => 'Dairy products and ice cream',
            'icon_image' => 'dairy-ice-cream.png',
            'level' => 1,
            'is_active' => true
        ]);

        $snacksCategory = Category::firstOrCreate(['name' => 'Snacks & Confectionery'], [
            'slug' => 'snacks-confectionery',
            'description' => 'Snacks, biscuits, and confectionery items',
            'icon_image' => 'snacks-confectionery.png',
            'level' => 1,
            'is_active' => true
        ]);

        $beverageCategory = Category::firstOrCreate(['name' => 'Beverages'], [
            'slug' => 'beverages',
            'description' => 'All types of beverages',
            'icon_image' => 'beverages.png',
            'level' => 1,
            'is_active' => true
        ]);

        // Get first user as owner
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now()
            ]);
        }

        // National businesses with proper tags
        $nationalBusinesses = [
            // Ice Cream Brands
            [
                'business_name' => 'Polar Ice Cream',
                'slug' => 'polar-ice-cream',
                'description' => 'Leading ice cream manufacturer in Bangladesh offering premium quality ice cream products.',
                'category_id' => $dairyCategory->id,
                'product_tags' => ['ice cream', 'dairy', 'frozen dessert', 'premium'],
                'business_tags' => ['family-owned', 'premium', 'traditional'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Igloo Ice Cream',
                'slug' => 'igloo-ice-cream',
                'description' => 'Popular ice cream brand known for innovative flavors and quality products.',
                'category_id' => $dairyCategory->id,
                'product_tags' => ['ice cream', 'dairy', 'frozen dessert', 'flavored'],
                'business_tags' => ['modern', 'premium', 'innovative'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Kwality Ice Cream',
                'slug' => 'kwality-ice-cream',
                'description' => 'Quality ice cream products for families across Bangladesh.',
                'category_id' => $dairyCategory->id,
                'product_tags' => ['ice cream', 'dairy', 'frozen dessert', 'family'],
                'business_tags' => ['family-friendly', 'budget-friendly', 'traditional'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],

            // Biscuit & Snack Brands
            [
                'business_name' => 'Olympic Biscuits',
                'slug' => 'olympic-biscuits',
                'description' => 'Leading biscuit manufacturer producing various types of biscuits and cookies.',
                'category_id' => $snacksCategory->id,
                'product_tags' => ['biscuit', 'cookie', 'snack', 'packaged food'],
                'business_tags' => ['traditional', 'family-owned', 'local'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Danish Biscuits',
                'slug' => 'danish-biscuits',
                'description' => 'Premium biscuit brand offering quality cookies and crackers.',
                'category_id' => $snacksCategory->id,
                'product_tags' => ['biscuit', 'cookie', 'crackers', 'premium'],
                'business_tags' => ['premium', 'imported', 'modern'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Fresh Snacks Ltd',
                'slug' => 'fresh-snacks-ltd',
                'description' => 'Manufacturer of various snack items including chips, crackers, and namkeen.',
                'category_id' => $snacksCategory->id,
                'product_tags' => ['snack', 'chips', 'crackers', 'namkeen'],
                'business_tags' => ['modern', 'chain', 'budget-friendly'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],

            // Beverage Brands
            [
                'business_name' => 'Pran Beverages',
                'slug' => 'pran-beverages',
                'description' => 'Leading beverage manufacturer producing soft drinks, juices, and energy drinks.',
                'category_id' => $beverageCategory->id,
                'product_tags' => ['beverage', 'soft drink', 'juice', 'energy drink'],
                'business_tags' => ['chain', 'modern', 'local', 'halal'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Shezan Juices',
                'slug' => 'shezan-juices',
                'description' => 'Premium fruit juice manufacturer known for natural and fresh juices.',
                'category_id' => $beverageCategory->id,
                'product_tags' => ['juice', 'beverage', 'fruit juice', 'natural'],
                'business_tags' => ['premium', 'organic', 'natural', 'healthy'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'Mojo Cola',
                'slug' => 'mojo-cola',
                'description' => 'Popular cola and carbonated drinks manufacturer in Bangladesh.',
                'category_id' => $beverageCategory->id,
                'product_tags' => ['soft drink', 'cola', 'beverage', 'carbonated'],
                'business_tags' => ['local', 'popular', 'budget-friendly'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],

            // Food Manufacturers (NOT ice cream brands)
            [
                'business_name' => 'Pran Foods Limited',
                'slug' => 'pran-foods-limited',
                'description' => 'Major food processing company manufacturing various food products, snacks, and beverages.',
                'category_id' => $foodCategory->id,
                'product_tags' => ['food processing', 'manufacturing', 'packaged food', 'ready meals', 'spices'],
                'business_tags' => ['chain', 'modern', 'local', 'diverse', 'halal'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
            [
                'business_name' => 'ACME Food Processing',
                'slug' => 'acme-food-processing',
                'description' => 'Food processing company specializing in packaged foods and ready-to-eat meals.',
                'category_id' => $foodCategory->id,
                'product_tags' => ['food processing', 'packaged food', 'ready meals', 'frozen food'],
                'business_tags' => ['modern', 'chain', 'convenient', 'halal'],
                'business_model' => 'manufacturing',
                'service_coverage' => 'national',
            ],
        ];

        foreach ($nationalBusinesses as $businessData) {
            $business = Business::create([
                'user_id' => $user->id,
                'category_id' => $businessData['category_id'],
                'business_name' => $businessData['business_name'],
                'slug' => $businessData['slug'],
                'description' => $businessData['description'],
                'full_address' => 'Dhaka, Bangladesh',
                'latitude' => 23.8103 + (rand(-100, 100) / 10000),
                'longitude' => 90.4125 + (rand(-100, 100) / 10000),
                'city' => 'Dhaka',
                'area' => 'Various Locations',
                'phone' => '+880-1' . rand(100000000, 999999999),
                'email' => strtolower(str_replace(' ', '', $businessData['business_name'])) . '@example.com',
                'website_url' => 'https://' . $businessData['slug'] . '.com',
                'opening_hours' => [
                    'monday' => '8:00 AM - 6:00 PM',
                    'tuesday' => '8:00 AM - 6:00 PM',
                    'wednesday' => '8:00 AM - 6:00 PM',
                    'thursday' => '8:00 AM - 6:00 PM',
                    'friday' => '8:00 AM - 6:00 PM',
                    'saturday' => '8:00 AM - 4:00 PM',
                    'sunday' => 'Closed',
                ],
                'price_range' => rand(1, 3),
                'has_delivery' => true,
                'has_pickup' => true,
                'has_parking' => true,
                'is_national' => true,
                'service_coverage' => $businessData['service_coverage'],
                'business_model' => $businessData['business_model'],
                'product_tags' => $businessData['product_tags'],
                'business_tags' => $businessData['business_tags'],
                'is_active' => true,
                'is_featured' => true,
                'approval_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'overall_rating' => rand(35, 50) / 10,
                'total_reviews' => rand(50, 500),
                'total_views' => rand(1000, 10000),
                'total_visits' => rand(100, 1000),
            ]);

            $this->command->info("Created national business: {$business->business_name}");
        }
    }
}
