<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Offer;
use App\Models\Business;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();

        $offerTemplates = [
            'restaurant' => [
                [
                    'title' => '20% Off on All Pizzas',
                    'description' => 'Get 20% discount on all pizza orders. Valid for dine-in and delivery.',
                    'discount_percentage' => 20,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Buy 1 Get 1 Free Burger',
                    'description' => 'Order any burger and get another burger absolutely free!',
                    'discount_percentage' => 50,
                    'offer_type' => 'bogo'
                ],
                [
                    'title' => 'Free Delivery Above ৳500',
                    'description' => 'No delivery charges for orders above ৳500.',
                    'discount_amount' => 60,
                    'offer_type' => 'fixed_amount'
                ],
                [
                    'title' => 'Family Combo Deal',
                    'description' => 'Special family combo: 2 Main dishes + 2 drinks + 1 dessert for only ৳1200',
                    'discount_percentage' => 25,
                    'offer_type' => 'combo'
                ]
            ],
            'shopping' => [
                [
                    'title' => '30% Off Summer Collection',
                    'description' => 'Huge discount on all summer clothing items.',
                    'discount_percentage' => 30,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Buy 2 Get 1 Free',
                    'description' => 'Purchase any 2 items and get 1 item free of equal or lesser value.',
                    'discount_percentage' => 33,
                    'offer_type' => 'bogo'
                ],
                [
                    'title' => 'Flash Sale - 50% Off',
                    'description' => 'Limited time flash sale with 50% discount on selected items.',
                    'discount_percentage' => 50,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Free Gift Wrapping',
                    'description' => 'Complimentary gift wrapping on all purchases above ৳2000.',
                    'discount_amount' => 200,
                    'offer_type' => 'fixed_amount'
                ]
            ],
            'service' => [
                [
                    'title' => '25% Off First Visit',
                    'description' => 'New customers get 25% discount on their first service.',
                    'discount_percentage' => 25,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Bridal Package Special',
                    'description' => 'Complete bridal package with makeup, hair, and mehendi for ৳15000',
                    'discount_percentage' => 20,
                    'offer_type' => 'combo'
                ],
                [
                    'title' => 'Free Consultation',
                    'description' => 'Get free beauty consultation with any facial treatment.',
                    'discount_amount' => 500,
                    'offer_type' => 'fixed_amount'
                ],
                [
                    'title' => 'Loyalty Discount',
                    'description' => '10% discount for customers with 5+ visits.',
                    'discount_percentage' => 10,
                    'offer_type' => 'percentage'
                ]
            ],
            'entertainment' => [
                [
                    'title' => 'Student Discount',
                    'description' => '20% off movie tickets for students with valid ID.',
                    'discount_percentage' => 20,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Matinee Show Special',
                    'description' => 'Special pricing for shows before 5 PM.',
                    'discount_percentage' => 15,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Group Booking Discount',
                    'description' => '15% discount for group bookings of 10 or more people.',
                    'discount_percentage' => 15,
                    'offer_type' => 'percentage'
                ],
                [
                    'title' => 'Weekend Combo Deal',
                    'description' => 'Movie ticket + popcorn + drink combo for ৳600',
                    'discount_percentage' => 12,
                    'offer_type' => 'combo'
                ]
            ]
        ];

        foreach ($businesses as $business) {
            // Some businesses might not have offers
            if (rand(1, 10) <= 7) { // 70% chance of having offers
                $categorySlug = $business->category->slug ?? 'restaurant';
                
                // Map business category to offer template
                $templateType = 'restaurant';
                if (str_contains($categorySlug, 'shopping') || str_contains($categorySlug, 'retail')) {
                    $templateType = 'shopping';
                } elseif (str_contains($categorySlug, 'service')) {
                    $templateType = 'service';
                } elseif (str_contains($categorySlug, 'entertainment') || str_contains($categorySlug, 'movie')) {
                    $templateType = 'entertainment';
                }

                $offers = $offerTemplates[$templateType] ?? $offerTemplates['restaurant'];
                
                // Add 1-2 offers per business
                $offerCount = rand(1, 2);
                $selectedOffers = array_slice($offers, 0, $offerCount);
                
                foreach ($selectedOffers as $offerData) {
                    $startDate = now()->subDays(rand(1, 30));
                    $endDate = now()->addDays(rand(7, 90));
                    
                    Offer::create([
                        'business_id' => $business->id,
                        'title' => $offerData['title'],
                        'description' => $offerData['description'],
                        'offer_type' => $offerData['offer_type'],
                        'discount_percentage' => $offerData['discount_percentage'] ?? null,
                        'discount_amount' => $offerData['discount_amount'] ?? null,
                        'minimum_spend' => $offerData['offer_type'] === 'fixed_amount' ? 500.00 : null,
                        'usage_limit' => rand(0, 10) <= 7 ? null : rand(50, 200), // 70% unlimited usage
                        'current_usage' => rand(0, 25),
                        'valid_from' => $startDate,
                        'valid_to' => $endDate,
                        'is_active' => true,
                        'is_featured' => rand(1, 10) <= 3, // 30% featured offers
                    ]);
                }
            }
        }
    }
}
