<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BusinessImage;
use App\Models\Business;

class BusinessImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();

        $sampleImages = [
            'restaurant' => [
                'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1514933651103-005eec06c04b?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1559339352-11d035aa65de?w=400&h=300&fit=crop'
            ],
            'shopping' => [
                'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1445205170230-053b83016050?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=400&h=300&fit=crop'
            ],
            'service' => [
                'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400&h=300&fit=crop'
            ],
            'entertainment' => [
                'https://images.unsplash.com/photo-1489599512460-0c1c62d0124a?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1489599512460-0c1c62d0124a?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=400&h=300&fit=crop'
            ]
        ];

        foreach ($businesses as $business) {
            $categorySlug = $business->category->slug ?? 'restaurant';
            
            // Map category types to image arrays
            $imageType = 'restaurant';
            if (str_contains($categorySlug, 'shopping') || str_contains($categorySlug, 'retail')) {
                $imageType = 'shopping';
            } elseif (str_contains($categorySlug, 'service')) {
                $imageType = 'service';
            } elseif (str_contains($categorySlug, 'entertainment') || str_contains($categorySlug, 'movie')) {
                $imageType = 'entertainment';
            }

            $images = $sampleImages[$imageType] ?? $sampleImages['restaurant'];
            
            // Add 2-4 images per business
            $imageCount = rand(2, 4);
            $selectedImages = array_slice($images, 0, $imageCount);
            
            foreach ($selectedImages as $index => $imageUrl) {
                BusinessImage::create([
                    'business_id' => $business->id,
                    'image_url' => $imageUrl,
                    'image_type' => $index === 0 ? 'logo' : 'gallery',
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1
                ]);
            }
        }
    }
}
