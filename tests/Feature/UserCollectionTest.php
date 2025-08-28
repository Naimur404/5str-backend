<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Business;
use App\Models\UserCollection;

class UserCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
        
        // Create a test business
        $this->business = Business::factory()->create([
            'name' => 'Test Restaurant',
            'phone' => '+8801234567890',
            'address' => '123 Test Street, Dhaka',
            'is_active' => true
        ]);
    }

    public function test_user_can_create_collection()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/collections', [
                'name' => 'My Favorite Places',
                'description' => 'Places I love to visit',
                'is_public' => true
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'collection' => [
                        'id',
                        'name',
                        'description',
                        'is_public',
                        'slug',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('user_collections', [
            'user_id' => $this->user->id,
            'name' => 'My Favorite Places',
            'is_public' => true
        ]);
    }

    public function test_user_can_add_business_to_collection()
    {
        $collection = UserCollection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Collection'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/collections/{$collection->id}/businesses", [
                'business_id' => $this->business->id,
                'notes' => 'Great food!',
                'sort_order' => 1
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'collection_item' => [
                        'id',
                        'collection_id',
                        'business_id',
                        'notes',
                        'sort_order',
                        'business' => [
                            'id',
                            'name',
                            'phone',
                            'address'
                        ]
                    ]
                ]
            ]);

        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $collection->id,
            'business_id' => $this->business->id,
            'notes' => 'Great food!'
        ]);
    }

    public function test_user_can_get_their_collections()
    {
        $collection = UserCollection::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Collection'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/collections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'collections' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'is_public',
                            'businesses_count',
                            'followers_count'
                        ]
                    ]
                ]
            ]);
    }

    public function test_user_can_view_public_collection()
    {
        $otherUser = User::factory()->create();
        $collection = UserCollection::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Public Collection',
            'is_public' => true
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/collections/{$collection->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_private_collection_of_others()
    {
        $otherUser = User::factory()->create();
        $collection = UserCollection::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Private Collection',
            'is_public' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/collections/{$collection->id}");

        $response->assertStatus(404);
    }

    public function test_guest_can_view_popular_collections()
    {
        $collection = UserCollection::factory()->create([
            'name' => 'Popular Collection',
            'is_public' => true
        ]);

        $response = $this->getJson('/api/v1/discover/collections/popular');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'collections' => []
                ]
            ]);
    }

    public function test_guest_can_search_collections()
    {
        $collection = UserCollection::factory()->create([
            'name' => 'Amazing Restaurants',
            'is_public' => true
        ]);

        $response = $this->getJson('/api/v1/discover/collections/search?query=amazing');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'collections' => [],
                    'query'
                ]
            ]);
    }
}
