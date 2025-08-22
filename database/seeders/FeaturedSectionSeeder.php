<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FeaturedSection;

class FeaturedSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $featuredSections = [
            [
                'section_name' => 'top_services',
                'title' => 'Top Services',
                'category_filter' => null,
                'display_limit' => 8,
                'sort_criteria' => 'popularity',
                'sort_order' => 1
            ],
            [
                'section_name' => 'popular_nearby',
                'title' => 'Popular Services Nearby',
                'category_filter' => null,
                'display_limit' => 10,
                'sort_criteria' => 'distance',
                'sort_order' => 2
            ],
            [
                'section_name' => 'top_restaurants',
                'title' => 'Top Restaurants Nearby',
                'category_filter' => ['restaurants'],
                'display_limit' => 8,
                'sort_criteria' => 'rating',
                'sort_order' => 3
            ],
            [
                'section_name' => 'top_pizza',
                'title' => 'Top Pizza Nearby',
                'category_filter' => ['pizza'],
                'display_limit' => 6,
                'sort_criteria' => 'rating',
                'sort_order' => 4
            ],
            [
                'section_name' => 'special_offers',
                'title' => 'Special Offers',
                'category_filter' => null,
                'display_limit' => 12,
                'sort_criteria' => 'popularity',
                'sort_order' => 5
            ],
            [
                'section_name' => 'featured_businesses',
                'title' => 'Featured Businesses',
                'category_filter' => null,
                'display_limit' => 6,
                'sort_criteria' => 'rating',
                'sort_order' => 6
            ]
        ];

        foreach ($featuredSections as $section) {
            FeaturedSection::create($section);
        }
    }
}
