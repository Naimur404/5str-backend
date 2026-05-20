<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\BusinessOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBranchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;
    private Business $parentBusiness;
    private Business $branchBusiness;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a category
        $this->category = Category::create([
            'name' => 'Restaurant',
            'slug' => 'restaurant',
            'level' => 1,
            'is_active' => true,
        ]);

        // Create a parent business (Takeout Brand Headquarters)
        $this->parentBusiness = Business::create([
            'business_name' => 'Takeout Bangladesh',
            'slug' => 'takeout-bangladesh',
            'description' => 'Famous burger chain in Bangladesh',
            'category_id' => $this->category->id,
            'full_address' => 'Dhaka Headquarters',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'city' => 'Dhaka',
            'area' => 'Gulshan',
            'is_active' => true,
            'is_national' => true,
        ]);

        // Create a branch location (Takeout Dhanmondi Branch)
        $this->branchBusiness = Business::create([
            'business_name' => 'Takeout Dhanmondi',
            'slug' => 'takeout-dhanmondi',
            'description' => 'Dhanmondi Branch of Takeout',
            'category_id' => $this->category->id,
            'parent_id' => $this->parentBusiness->id,
            'full_address' => 'Satmasjid Road, Dhanmondi, Dhaka',
            'latitude' => 23.7465,
            'longitude' => 90.3754,
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'is_active' => true,
            'is_national' => false,
        ]);
    }

    public function test_business_relationship_and_helper_methods()
    {
        // Assert isParent and isBranch work
        $this->assertTrue($this->parentBusiness->isParent());
        $this->assertFalse($this->parentBusiness->isBranch());

        $this->assertTrue($this->branchBusiness->isBranch());
        $this->assertFalse($this->branchBusiness->isParent());

        // Assert relationships retrieve correctly
        $this->assertEquals($this->parentBusiness->id, $this->branchBusiness->parentBusiness->id);
        $this->assertCount(1, $this->parentBusiness->branches);
        $this->assertEquals($this->branchBusiness->id, $this->parentBusiness->branches->first()->id);
    }

    public function test_offering_inheritance_from_parent_to_branch()
    {
        // 1. Branch initially has no offerings, parent has no offerings.
        $this->assertCount(0, $this->branchBusiness->getInheritedOfferings());

        // 2. Create offerings on the parent business
        $offering = BusinessOffering::create([
            'business_id' => $this->parentBusiness->id,
            'name' => 'Cheese Burger',
            'description' => 'Juicy beef patty with double cheese',
            'price' => 350.00,
            'offering_type' => 'product',
            'is_available' => true,
        ]);

        // 3. Assert branch correctly inherits parent offerings since it doesn't define any locally
        $this->branchBusiness->refresh();
        $inheritedOfferings = $this->branchBusiness->getInheritedOfferings();
        $this->assertCount(1, $inheritedOfferings);
        $this->assertEquals($offering->id, $inheritedOfferings->first()->id);

        // 4. Create a local offering for the branch
        $localOffering = BusinessOffering::create([
            'business_id' => $this->branchBusiness->id,
            'name' => 'Dhanmondi Special Burger',
            'description' => 'Exclusive to Dhanmondi branch',
            'price' => 450.00,
            'offering_type' => 'product',
            'is_available' => true,
        ]);

        // 5. Assert branch now returns only its local offerings, bypassing inheritance
        $branchOfferings = $this->branchBusiness->getInheritedOfferings();
        $this->assertCount(1, $branchOfferings);
        $this->assertEquals($localOffering->id, $branchOfferings->first()->id);
    }

    public function test_parent_business_api_returns_branches_details()
    {
        $response = $this->getJson("/api/v1/businesses/{$this->parentBusiness->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_national', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'business_name',
                    'branches' => [
                        '*' => [
                            'id',
                            'business_name',
                            'slug',
                            'full_address',
                            'city',
                            'area',
                            'overall_rating',
                        ]
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data.branches'));
        $this->assertEquals($this->branchBusiness->id, $response->json('data.branches.0.id'));
    }

    public function test_branch_business_api_returns_parent_details()
    {
        $response = $this->getJson("/api/v1/businesses/{$this->branchBusiness->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'business_name',
                    'parent_brand' => [
                        'id',
                        'business_name',
                        'slug',
                    ]
                ]
            ]);

        $this->assertEquals($this->parentBusiness->id, $response->json('data.parent_brand.id'));
    }

    public function test_query_filtering_by_parent_id()
    {
        // 1. Querying parent_id returns only the Dhanmondi branch
        $response = $this->getJson("/api/v1/businesses?parent_id={$this->parentBusiness->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'businesses' => [
                        '*' => [
                            'id',
                            'business_name',
                        ]
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data.businesses'));
        $this->assertEquals($this->branchBusiness->id, $response->json('data.businesses.0.id'));

        // 2. Querying only_parents=true returns only parent brand and no branches
        $response = $this->getJson('/api/v1/businesses?only_parents=true');

        $response->assertStatus(200);
        $businessIds = collect($response->json('data.businesses'))->pluck('id');
        $this->assertTrue($businessIds->contains($this->parentBusiness->id));
        $this->assertFalse($businessIds->contains($this->branchBusiness->id));
    }
}
