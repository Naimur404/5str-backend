<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Models\BusinessOffering;
use App\Models\Attraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a category with dynamic rating criteria
        $this->category = Category::create([
            'name' => 'Restaurant',
            'slug' => 'restaurant',
            'level' => 1,
            'rating_criteria' => ['Food', 'Service', 'Atmosphere', 'Value'],
            'is_active' => true,
        ]);

        // Create a business
        $this->business = Business::create([
            'business_name' => 'Pizza Palace',
            'slug' => 'pizza-palace',
            'description' => 'Best pizza in town',
            'category_id' => $this->category->id,
            'full_address' => '123 Pizza Street, Dhaka',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'city' => 'Dhaka',
            'area' => 'Gulshan',
            'is_active' => true,
        ]);
    }

    public function test_user_can_submit_review_with_dynamic_ratings()
    {
        $ratings = [
            'Food' => 5,
            'Service' => 4,
            'Atmosphere' => 4,
            'Value' => 5
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'business',
                'reviewable_id' => $this->business->id,
                'overall_rating' => 5,
                'title' => 'This place is absolutely wonderful! Loved the pizza.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'review' => [
                        'id',
                        'overall_rating',
                        'ratings',
                        'title',
                        'status',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $this->business->id,
            'reviewable_type' => 'App\\Models\\Business',
            'overall_rating' => 5,
        ]);

        // Verify the business aggregated rating, distribution, and averages
        $this->business->refresh();
        $this->assertEquals(5.00, $this->business->overall_rating);
        $this->assertEquals(1, $this->business->total_reviews);
        
        $this->assertEquals([
            'Food' => 5.0,
            'Service' => 4.0,
            'Atmosphere' => 4.0,
            'Value' => 5.0
        ], $this->business->rating_averages);

        $this->assertEquals([
            '5' => 1,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0
        ], $this->business->rating_distribution);
    }

    public function test_dynamic_ratings_validation_fails_when_missing_criteria()
    {
        // Missing 'Value' criterion
        $ratings = [
            'Food' => 5,
            'Service' => 4,
            'Atmosphere' => 4
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'business',
                'reviewable_id' => $this->business->id,
                'overall_rating' => 5,
                'title' => 'This place is absolutely wonderful! Loved the pizza.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ratings.Value']);
    }

    public function test_dynamic_ratings_validation_fails_when_invalid_value()
    {
        // Atmosphere rating is 6 (invalid, max is 5)
        $ratings = [
            'Food' => 5,
            'Service' => 4,
            'Atmosphere' => 6,
            'Value' => 5
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'business',
                'reviewable_id' => $this->business->id,
                'overall_rating' => 5,
                'title' => 'This place is absolutely wonderful! Loved the pizza.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ratings.Atmosphere']);
    }

    public function test_can_view_business_with_rating_criteria_and_distribution()
    {
        // Submit review first
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_type' => 'App\\Models\\Business',
            'reviewable_id' => $this->business->id,
            'overall_rating' => 4,
            'ratings' => [
                'Food' => 5,
                'Service' => 4,
                'Atmosphere' => 3,
                'Value' => 4
            ],
            'title' => 'Decent place, but could be better.',
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.rating_criteria', ['Food', 'Service', 'Atmosphere', 'Value'])
            ->assertJsonPath('data.rating_averages.Food', 5)
            ->assertJsonPath('data.rating_averages.Atmosphere', 3)
            ->assertJsonPath('data.rating_distribution.4', 1);
    }

    public function test_can_get_reviews_list_with_dynamic_ratings()
    {
        // Submit review
        $review = Review::create([
            'user_id' => $this->user->id,
            'reviewable_type' => 'App\\Models\\Business',
            'reviewable_id' => $this->business->id,
            'overall_rating' => 4,
            'ratings' => [
                'Food' => 5,
                'Service' => 4,
                'Atmosphere' => 3,
                'Value' => 4
            ],
            'title' => 'Decent place, but could be better.',
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reviews' => [
                        '*' => [
                            'id',
                            'overall_rating',
                            'ratings',
                            'title',
                        ]
                    ]
                ]
            ])
            ->assertJsonPath('data.reviews.0.ratings.Food', 5);
    }

    public function test_user_can_submit_brand_review()
    {
        // Create a brand
        $brand = Business::create([
            'business_name' => 'Coca Cola Brand',
            'slug' => 'coca-cola-brand',
            'description' => 'Global Brand',
            'category_id' => $this->category->id,
            'full_address' => 'National',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'city' => 'Dhaka',
            'area' => 'Gulshan',
            'business_model' => 'brand',
            'is_active' => true,
        ]);

        $ratings = [
            'Quality' => 5,
            'Price' => 4,
            'Availability' => 5,
            'Customer Service' => 3
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'brand',
                'reviewable_id' => $brand->id,
                'overall_rating' => 4,
                'title' => 'Good brand quality and availability.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $brand->id,
            'reviewable_type' => 'App\\Models\\Business',
            'overall_rating' => 4,
        ]);

        $brand->refresh();
        $this->assertEquals(4.00, $brand->overall_rating);
        $this->assertEquals([
            'Quality' => 5,
            'Price' => 4,
            'Availability' => 5,
            'Customer Service' => 3
        ], $brand->rating_averages);
    }

    public function test_user_can_submit_offering_review()
    {
        // Create an offering
        $offering = BusinessOffering::create([
            'business_id' => $this->business->id,
            'name' => 'Margerita Pizza',
            'description' => 'Classic pizza',
            'price' => 500,
            'offering_type' => 'product',
            'is_available' => true,
        ]);

        $ratings = [
            'Taste' => 5,
            'Portion' => 4,
            'Value' => 4,
            'Presentation' => 5
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'offering',
                'reviewable_id' => $offering->id,
                'overall_rating' => 5,
                'title' => 'Loved the taste and portion size.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $offering->id,
            'reviewable_type' => 'App\\Models\\BusinessOffering',
            'overall_rating' => 5,
        ]);

        $offering->refresh();
        $this->assertEquals(5.00, $offering->average_rating);
    }

    public function test_user_can_submit_attraction_review()
    {
        // Create an attraction
        $attraction = Attraction::create([
            'name' => 'Fantasy Kingdom',
            'slug' => 'fantasy-kingdom',
            'description' => 'Amusement park',
            'latitude' => 23.9,
            'longitude' => 90.3,
            'address' => 'Ashulia, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Ashulia',
            'is_active' => true,
        ]);

        $ratings = [
            'Cleanliness' => 4,
            'Safety' => 5,
            'Crowd Management' => 4,
            'Value' => 4
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reviews', [
                'reviewable_type' => 'attraction',
                'reviewable_id' => $attraction->id,
                'overall_rating' => 4,
                'title' => 'Very safe park for children, cleanliness is decent.',
                'ratings' => $ratings,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'reviewable_id' => $attraction->id,
            'reviewable_type' => 'App\\Models\\Attraction',
            'overall_rating' => 4,
        ]);

        $attraction->refresh();
        $this->assertEquals(4.00, $attraction->overall_rating);
    }
}
