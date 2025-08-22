<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Banner;
use App\Models\Business;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::where('is_featured', true)->get();

        $bannerData = [
            [
                'title' => 'Best Restaurants in Dhaka',
                'subtitle' => 'Discover amazing dining experiences near you',
                'image_url' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=800&h=400&fit=crop',
                'link_type' => 'category',
                'link_url' => '/categories/restaurants',
                'position' => 'hero',
                'sort_order' => 1
            ],
            [
                'title' => 'Shop Local, Shop Fresh',
                'subtitle' => 'Explore local markets and authentic products',
                'image_url' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=800&h=400&fit=crop',
                'link_type' => 'category',
                'link_url' => '/categories/shopping',
                'position' => 'hero',
                'sort_order' => 2
            ],
            [
                'title' => 'Premium Beauty Services',
                'subtitle' => 'Pamper yourself with professional beauty treatments',
                'image_url' => 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800&h=400&fit=crop',
                'link_type' => 'category',
                'link_url' => '/categories/services',
                'position' => 'top',
                'sort_order' => 3
            ],
            [
                'title' => 'Entertainment Hub',
                'subtitle' => 'Movies, games, and fun activities for everyone',
                'image_url' => 'https://images.unsplash.com/photo-1489599512460-0c1c62d0124a?w=800&h=400&fit=crop',
                'link_type' => 'category',
                'link_url' => '/categories/entertainment',
                'position' => 'top',
                'sort_order' => 4
            ]
        ];

        // Create category banners
        foreach ($bannerData as $banner) {
            Banner::create([
                'title' => $banner['title'],
                'subtitle' => $banner['subtitle'],
                'image_url' => $banner['image_url'],
                'link_type' => $banner['link_type'],
                'link_url' => $banner['link_url'],
                'position' => $banner['position'],
                'is_active' => true,
                'sort_order' => $banner['sort_order'],
                'start_date' => now(),
                'end_date' => now()->addMonths(3)
            ]);
        }

        // Create business promotion banners for featured businesses
        foreach ($businesses->take(3) as $index => $business) {
            Banner::create([
                'title' => 'Featured: ' . $business->business_name,
                'subtitle' => $business->description,
                'image_url' => 'https://images.unsplash.com/photo-' . (1500000000 + $index) . '?w=800&h=400&fit=crop',
                'link_type' => 'business',
                'link_id' => $business->id,
                'link_url' => '/businesses/' . $business->slug,
                'position' => 'bottom',
                'is_active' => true,
                'sort_order' => 5 + $index,
                'start_date' => now(),
                'end_date' => now()->addMonths(1)
            ]);
        }

        // Create promotional banners
        $promotionalBanners = [
            [
                'title' => 'Grand Opening Sale',
                'subtitle' => 'Up to 50% off on all items! Limited time offer.',
                'image_url' => 'https://images.unsplash.com/photo-1607083206968-13611e3d76db?w=800&h=400&fit=crop',
                'link_type' => 'external',
                'link_url' => '/offers',
                'position' => 'top'
            ],
            [
                'title' => 'Weekend Special',
                'subtitle' => 'Exclusive weekend deals and offers for you!',
                'image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=800&h=400&fit=crop',
                'link_type' => 'external',
                'link_url' => '/weekend-offers',
                'position' => 'bottom'
            ]
        ];

        foreach ($promotionalBanners as $index => $banner) {
            Banner::create([
                'title' => $banner['title'],
                'subtitle' => $banner['subtitle'],
                'image_url' => $banner['image_url'],
                'link_type' => $banner['link_type'],
                'link_url' => $banner['link_url'],
                'position' => $banner['position'],
                'is_active' => true,
                'sort_order' => 10 + $index,
                'start_date' => now(),
                'end_date' => now()->addWeeks(2)
            ]);
        }
    }
}
