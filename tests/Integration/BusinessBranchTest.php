<?php

namespace Tests\Integration;

use App\Models\Business;
use App\Models\Category;
use App\Models\BusinessOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('integration')]
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

    public function test_general_listing_still_hides_brand_rows_that_have_branches()
    {
        // A standalone national business (no branches) stays listed.
        $standalone = Business::create([
            'business_name' => 'Aarong',
            'slug' => 'aarong',
            'description' => 'Standalone national business',
            'category_id' => $this->category->id,
            'full_address' => 'Tejgaon, Dhaka',
            'latitude' => 23.7806,
            'longitude' => 90.4070,
            'city' => 'Dhaka',
            'area' => 'Tejgaon',
            'is_active' => true,
            'is_national' => true,
        ]);

        // Without an explicit top-level filter, reachable() hides the brand
        // row that has branches.
        $response = $this->getJson('/api/v1/businesses');
        $response->assertStatus(200);
        $businessIds = collect($response->json('data.businesses'))->pluck('id');
        $this->assertFalse($businessIds->contains($this->parentBusiness->id));
        $this->assertTrue($businessIds->contains($standalone->id));

        // parent_id=null is an explicit top-level filter, like only_parents=true.
        $response = $this->getJson('/api/v1/businesses?parent_id=null');
        $response->assertStatus(200);
        $businessIds = collect($response->json('data.businesses'))->pluck('id');
        $this->assertTrue($businessIds->contains($this->parentBusiness->id));
        $this->assertTrue($businessIds->contains($standalone->id));
        $this->assertFalse($businessIds->contains($this->branchBusiness->id));
    }

    public function test_parent_business_api_returns_branches_sorted_by_distance_with_closest_branch()
    {
        // 1. Create a second branch in Gulshan (closer to Gulshan coordinates)
        $gulshanBranch = Business::create([
            'business_name' => 'Takeout Gulshan',
            'slug' => 'takeout-gulshan',
            'description' => 'Gulshan Branch of Takeout',
            'category_id' => $this->category->id,
            'parent_id' => $this->parentBusiness->id,
            'full_address' => 'Gulshan Avenue, Dhaka',
            'latitude' => 23.8120,
            'longitude' => 90.4130,
            'city' => 'Dhaka',
            'area' => 'Gulshan',
            'is_active' => true,
            'is_national' => false,
        ]);

        // 2. Query parent business details from a location very close to Gulshan branch
        $response = $this->getJson("/api/v1/businesses/{$this->parentBusiness->id}?latitude=23.8115&longitude=90.4120");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'closest_branch' => [
                        'id',
                        'business_name',
                        'distance',
                    ],
                    'branches'
                ]
            ]);

        // Gulshan branch is very close (~0.12 km), Dhanmondi is far (~8 km)
        // Assert Gulshan branch is identified as the closest branch
        $this->assertEquals($gulshanBranch->id, $response->json('data.closest_branch.id'));

        // Assert branches list is sorted: Gulshan first, then Dhanmondi
        $branches = $response->json('data.branches');
        $this->assertCount(2, $branches);
        $this->assertEquals($gulshanBranch->id, $branches[0]['id']);
        $this->assertEquals($this->branchBusiness->id, $branches[1]['id']);

        // 3. Query parent business details from a location very close to Dhanmondi branch
        $response = $this->getJson("/api/v1/businesses/{$this->parentBusiness->id}?latitude=23.7460&longitude=90.3750");

        $response->assertStatus(200);
        $this->assertEquals($this->branchBusiness->id, $response->json('data.closest_branch.id'));

        // Assert branches list is sorted: Dhanmondi first, then Gulshan
        $branches = $response->json('data.branches');
        $this->assertEquals($this->branchBusiness->id, $branches[0]['id']);
        $this->assertEquals($gulshanBranch->id, $branches[1]['id']);
    }

    public function test_branch_business_api_returns_other_branches_sorted_by_distance()
    {
        // 1. Create a second branch in Gulshan
        $gulshanBranch = Business::create([
            'business_name' => 'Takeout Gulshan',
            'slug' => 'takeout-gulshan',
            'description' => 'Gulshan Branch of Takeout',
            'category_id' => $this->category->id,
            'parent_id' => $this->parentBusiness->id,
            'full_address' => 'Gulshan Avenue, Dhaka',
            'latitude' => 23.8120,
            'longitude' => 90.4130,
            'city' => 'Dhaka',
            'area' => 'Gulshan',
            'is_active' => true,
            'is_national' => false,
        ]);

        // 2. Query Dhanmondi branch, passing coordinates close to Gulshan branch.
        // The endpoint should calculate and include 'other_branches' (which contains Gulshan branch)
        $response = $this->getJson("/api/v1/businesses/{$this->branchBusiness->id}?latitude=23.8115&longitude=90.4120");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'other_branches' => [
                        '*' => [
                            'id',
                            'business_name',
                            'distance',
                        ]
                    ]
                ]
            ]);

        $otherBranches = $response->json('data.other_branches');
        $this->assertCount(1, $otherBranches);
        $this->assertEquals($gulshanBranch->id, $otherBranches[0]['id']);
        // Verify it includes correct distance units/details
        $this->assertNotNull($otherBranches[0]['distance']);
    }

    public function test_offerings_api_supports_inheritance_fallback_with_meta()
    {
        // 1. Query offerings of the branch. Initially should be empty, but inherited from parent (which is also empty).
        $response = $this->getJson("/api/v1/businesses/{$this->branchBusiness->id}/offerings");

        $response->assertStatus(200)
            ->assertJsonPath('data.inherited_from_parent', true)
            ->assertJsonCount(0, 'data.offerings');


        // 2. Create offerings on the parent business
        $parentOffering = BusinessOffering::create([
            'business_id' => $this->parentBusiness->id,
            'name' => 'Parent Burger',
            'description' => 'Beef Burger from parent',
            'price' => 350.00,
            'offering_type' => 'product',
            'is_available' => true,
        ]);

        // 3. Query branch offerings. Since branch has no offerings, it should inherit parent's,
        // and setting inherited_from_parent => true
        $response = $this->getJson("/api/v1/businesses/{$this->branchBusiness->id}/offerings");

        $response->assertStatus(200)
            ->assertJsonPath('data.inherited_from_parent', true)
            ->assertJsonCount(1, 'data.offerings')
            ->assertJsonPath('data.offerings.0.id', $parentOffering->id);

        // 4. Create local offering for branch
        $branchOffering = BusinessOffering::create([
            'business_id' => $this->branchBusiness->id,
            'name' => 'Branch Special Burger',
            'description' => 'Exclusive to local branch',
            'price' => 400.00,
            'offering_type' => 'product',
            'is_available' => true,
        ]);

        // 5. Query branch offerings again. It should return ONLY branch local offerings,
        // and setting inherited_from_parent => false
        $response = $this->getJson("/api/v1/businesses/{$this->branchBusiness->id}/offerings");

        $response->assertStatus(200)
            ->assertJsonPath('data.inherited_from_parent', false)
            ->assertJsonCount(1, 'data.offerings')
            ->assertJsonPath('data.offerings.0.id', $branchOffering->id);
    }
}

