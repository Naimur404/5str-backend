<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;

class NationalBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create categories for national businesses
        $foodCategory = Category::firstOrCreate([
            'name' => 'Food & Beverages'
        ], [
            'slug' => 'food-beverages',
            'description' => 'Food and beverage businesses',
            'icon_image' => null,
            'color_code' => '#FF5722',
            'is_active' => true,
            'sort_order' => 1
        ]);

        $retailCategory = Category::firstOrCreate([
            'name' => 'Retail & Shopping'
        ], [
            'slug' => 'retail-shopping',
            'description' => 'Retail and shopping businesses',
            'icon_image' => null,
            'color_code' => '#2196F3',
            'is_active' => true,
            'sort_order' => 2
        ]);

        $serviceCategory = Category::firstOrCreate([
            'name' => 'Services'
        ], [
            'slug' => 'services',
            'description' => 'General services',
            'icon_image' => null,
            'color_code' => '#4CAF50',
            'is_active' => true,
            'sort_order' => 3
        ]);

        // Create sample national businesses (brands available across Bangladesh)
        $nationalBusinesses = [
            // Food & Beverage Brands
            [
                'business_name' => 'Pran Foods Limited',
                'slug' => 'pran-foods',
                'description' => 'Leading food and beverage manufacturer in Bangladesh. Produces snacks, beverages, dairy products, and confectionery items.',
                'category_id' => $foodCategory?->id,
                'business_email' => 'info@prangroup.com',
                'business_phone' => '+88-02-8331891',
                'website_url' => 'https://www.pranfoods.net',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'manufacturing',
                'is_verified' => true,
                'is_featured' => true,
                'overall_rating' => 4.5,
                'total_reviews' => 289,
                'is_active' => true,
            ],
            [
                'business_name' => 'Polar Ice Cream',
                'slug' => 'polar-ice-cream',
                'description' => 'Premium ice cream brand available nationwide with various flavors and products.',
                'category_id' => $foodCategory?->id,
                'business_email' => 'info@polar.com.bd',
                'business_phone' => '+88-02-9356789',
                'website_url' => 'https://www.polar.com.bd',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'brand',
                'service_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barisal'],
                'is_verified' => true,
                'is_featured' => true,
                'overall_rating' => 4.2,
                'total_reviews' => 156,
                'is_active' => true,
            ],
            [
                'business_name' => 'Olympic Biscuit',
                'slug' => 'olympic-biscuit',
                'description' => 'Popular biscuit brand in Bangladesh offering various types of biscuits and cookies.',
                'category_id' => $foodCategory?->id,
                'business_email' => 'contact@olympic.com.bd',
                'business_phone' => '+88-02-8851234',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'brand',
                'is_verified' => true,
                'overall_rating' => 4.0,
                'total_reviews' => 95,
                'is_active' => true,
            ],
            [
                'business_name' => 'Tiger Biscuits',
                'slug' => 'tiger-biscuits',
                'description' => 'Well-known biscuit manufacturer producing quality biscuits across Bangladesh.',
                'category_id' => $foodCategory?->id,
                'business_email' => 'info@tigerbiscuits.com',
                'business_phone' => '+88-02-7751234',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'manufacturing',
                'is_verified' => true,
                'overall_rating' => 3.8,
                'total_reviews' => 72,
                'is_active' => true,
            ],
            // Online Services
            [
                'business_name' => 'Foodpanda Bangladesh',
                'slug' => 'foodpanda-bangladesh',
                'description' => 'Leading food delivery platform serving major cities across Bangladesh.',
                'category_id' => $serviceCategory?->id,
                'business_email' => 'support@foodpanda.com.bd',
                'business_phone' => '+88-09678-002002',
                'website_url' => 'https://www.foodpanda.com.bd',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'online_service',
                'service_areas' => ['Dhaka', 'Chittagong', 'Sylhet', 'Cumilla'],
                'is_verified' => true,
                'is_featured' => true,
                'overall_rating' => 3.9,
                'total_reviews' => 445,
                'is_active' => true,
            ],
            [
                'business_name' => 'Daraz Bangladesh',
                'slug' => 'daraz-bangladesh',
                'description' => 'Leading e-commerce platform in Bangladesh for online shopping.',
                'category_id' => $retailCategory?->id,
                'business_email' => 'help@daraz.com.bd',
                'business_phone' => '+88-09678-991991',
                'website_url' => 'https://www.daraz.com.bd',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'online_service',
                'is_verified' => true,
                'is_featured' => true,
                'overall_rating' => 4.1,
                'total_reviews' => 1250,
                'is_active' => true,
            ],
            // Manufacturing Companies
            [
                'business_name' => 'Akij Food & Beverage',
                'slug' => 'akij-food-beverage',
                'description' => 'Major food and beverage manufacturer in Bangladesh producing various consumer goods.',
                'category_id' => $foodCategory?->id,
                'business_email' => 'info@akijgroup.com',
                'business_phone' => '+88-02-8315085',
                'website_url' => 'https://www.akijgroup.com',
                'is_national' => true,
                'service_coverage' => 'national',
                'business_model' => 'manufacturing',
                'is_verified' => true,
                'overall_rating' => 4.3,
                'total_reviews' => 178,
                'is_active' => true,
            ],
        ];

        foreach ($nationalBusinesses as $businessData) {
            Business::create($businessData);
        }

        $this->command->info('National businesses seeded successfully!');
    }
}