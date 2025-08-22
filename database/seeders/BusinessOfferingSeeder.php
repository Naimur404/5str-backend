<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BusinessOffering;
use App\Models\Business;

class BusinessOfferingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();

        $offeringTemplates = [
            'restaurant' => [
                [
                    'name' => 'Chicken Pizza (Large)',
                    'description' => 'Delicious large chicken pizza with fresh toppings',
                    'price' => 850.00,
                    'category' => 'Main Course'
                ],
                [
                    'name' => 'Beef Burger Combo',
                    'description' => 'Beef burger with fries and soft drink',
                    'price' => 420.00,
                    'category' => 'Combo Meals'
                ],
                [
                    'name' => 'Chicken Biryani',
                    'description' => 'Traditional Bengali style chicken biryani',
                    'price' => 280.00,
                    'category' => 'Rice Items'
                ],
                [
                    'name' => 'Fresh Juice',
                    'description' => 'Freshly squeezed seasonal fruit juice',
                    'price' => 120.00,
                    'category' => 'Beverages'
                ]
            ],
            'shopping' => [
                [
                    'name' => 'Cotton Saree',
                    'description' => 'Traditional handwoven cotton saree',
                    'price' => 3500.00,
                    'category' => 'Women\'s Clothing'
                ],
                [
                    'name' => 'Men\'s Panjabi',
                    'description' => 'Elegant cotton panjabi for special occasions',
                    'price' => 2200.00,
                    'category' => 'Men\'s Clothing'
                ],
                [
                    'name' => 'Handicraft Bag',
                    'description' => 'Handmade jute bag with traditional design',
                    'price' => 650.00,
                    'category' => 'Accessories'
                ],
                [
                    'name' => 'Clay Pottery Set',
                    'description' => 'Traditional clay pottery set for home decor',
                    'price' => 1200.00,
                    'category' => 'Home Decor'
                ]
            ],
            'service' => [
                [
                    'name' => 'Hair Cut & Style',
                    'description' => 'Professional haircut and styling service',
                    'price' => 500.00,
                    'category' => 'Hair Services'
                ],
                [
                    'name' => 'Facial Treatment',
                    'description' => 'Deep cleansing facial treatment',
                    'price' => 1200.00,
                    'category' => 'Skin Care'
                ],
                [
                    'name' => 'Bridal Makeup',
                    'description' => 'Complete bridal makeup package',
                    'price' => 8000.00,
                    'category' => 'Bridal Services'
                ],
                [
                    'name' => 'Manicure & Pedicure',
                    'description' => 'Professional nail care service',
                    'price' => 800.00,
                    'category' => 'Nail Services'
                ]
            ],
            'entertainment' => [
                [
                    'name' => 'Movie Ticket (Regular)',
                    'description' => 'Regular movie ticket for latest releases',
                    'price' => 350.00,
                    'category' => 'Tickets'
                ],
                [
                    'name' => 'Movie Ticket (Premium)',
                    'description' => 'Premium seat movie ticket with extra comfort',
                    'price' => 500.00,
                    'category' => 'Tickets'
                ],
                [
                    'name' => 'Combo Deal',
                    'description' => 'Movie ticket with popcorn and soft drink',
                    'price' => 650.00,
                    'category' => 'Combo'
                ],
                [
                    'name' => 'Group Booking',
                    'description' => 'Special group booking for 10+ people',
                    'price' => 3000.00,
                    'category' => 'Group Packages'
                ]
            ]
        ];

        foreach ($businesses as $business) {
            $categorySlug = $business->category->slug ?? 'restaurant';
            
            // Map business category to offering template
            $templateType = 'restaurant';
            if (str_contains($categorySlug, 'shopping') || str_contains($categorySlug, 'retail')) {
                $templateType = 'shopping';
            } elseif (str_contains($categorySlug, 'service')) {
                $templateType = 'service';
            } elseif (str_contains($categorySlug, 'entertainment') || str_contains($categorySlug, 'movie')) {
                $templateType = 'entertainment';
            }

            $offerings = $offeringTemplates[$templateType] ?? $offeringTemplates['restaurant'];
            
            // Add 2-3 random offerings per business
            $selectedOfferings = array_slice($offerings, 0, rand(2, 3));
            
            foreach ($selectedOfferings as $index => $offeringData) {
                BusinessOffering::create([
                    'business_id' => $business->id,
                    'name' => $offeringData['name'],
                    'description' => $offeringData['description'],
                    'offering_type' => 'product', // Default to product
                    'price' => $offeringData['price'],
                    'is_available' => rand(0, 10) > 1, // 90% availability
                    'sort_order' => $index + 1
                ]);
            }
        }
    }
}
