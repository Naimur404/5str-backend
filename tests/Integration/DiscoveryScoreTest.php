<?php

namespace Tests\Integration;

use App\Models\Attraction;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * GET endpoints compute location-aware discovery scores in memory and
 * never write them; the stored score is location-independent and is
 * refreshed by discovery:refresh-scores.
 */
#[Group('integration')]
class DiscoveryScoreTest extends TestCase
{
    use RefreshDatabase;

    // Lalbagh Fort, Dhaka. Requests below are made from this point.
    private const LAT = 23.7189;

    private const LNG = 90.3882;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeAttraction(array $overrides = []): Attraction
    {
        return Attraction::create(array_merge([
            'name' => 'Lalbagh Fort',
            'description' => 'Mughal fort',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'address' => 'Lalbagh Road',
            'city' => 'Dhaka',
            'overall_rating' => 4.0,
            'is_verified' => true,
            'is_featured' => false,
            'is_free' => false,
            'discovery_score' => 10,
        ], $overrides));
    }

    public function test_nearby_returns_location_aware_score_without_writing(): void
    {
        $attraction = $this->makeAttraction();
        $before = $attraction->fresh();
        Carbon::setTestNow(now()->addHour()); // any write would move updated_at

        $response = $this->getJson('/api/v1/attractions/nearby?latitude='.self::LAT.'&longitude='.self::LNG.'&radius=5');

        // At its own location: distance 25 + rating 24 + verified 10.
        $response->assertOk()->assertJsonPath('data.0.id', $attraction->id);
        $this->assertEqualsWithDelta(59.0, (float) $response->json('data.0.discovery_score'), 0.01);

        $after = $attraction->fresh();
        $this->assertSame((float) $before->discovery_score, (float) $after->discovery_score);
        $this->assertTrue($before->updated_at->equalTo($after->updated_at));
    }

    public function test_index_with_location_ranks_by_location_aware_score_without_writing(): void
    {
        // Stored scores say "near" first; the caller's location-aware scores
        // say "far" first (near: 25 + 18 = 43, far: ~15 + 24 + 10 = ~49).
        $near = $this->makeAttraction(['name' => 'Near', 'overall_rating' => 3.0, 'is_verified' => false, 'discovery_score' => 90]);
        $far = $this->makeAttraction(['name' => 'Far', 'latitude' => 23.7909, 'discovery_score' => 1]); // ~8 km north

        $response = $this->getJson('/api/v1/attractions?latitude='.self::LAT.'&longitude='.self::LNG.'&radius=10');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $far->id)
            ->assertJsonPath('data.data.1.id', $near->id)
            ->assertJsonPath('meta.total_count', 2);

        $this->assertSame(90.0, (float) $near->fresh()->discovery_score);
        $this->assertSame(1.0, (float) $far->fresh()->discovery_score);
    }

    public function test_business_show_with_location_does_not_write(): void
    {
        $category = Category::create(['name' => 'Restaurant', 'slug' => 'restaurant', 'level' => 1, 'is_active' => true]);
        $business = Business::create([
            'business_name' => 'Star Kabab',
            'slug' => 'star-kabab',
            'description' => 'Kabab house',
            'category_id' => $category->id,
            'full_address' => 'Dhanmondi, Dhaka',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'is_active' => true,
            'discovery_score' => 12,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$business->id}?latitude=".self::LAT.'&longitude='.self::LNG);

        // At its own location: distance 30 + preference 5 (no rating, reviews or offers).
        $response->assertOk();
        $this->assertEqualsWithDelta(35.0, (float) $response->json('data.discovery_score'), 0.01);
        $this->assertSame(12.0, (float) $business->fresh()->discovery_score);
    }

    public function test_refresh_command_stores_location_independent_score_without_touching_updated_at(): void
    {
        $attraction = $this->makeAttraction(['discovery_score' => 77]); // stale value with an old distance bonus
        $updatedAt = $attraction->fresh()->updated_at;
        Carbon::setTestNow(now()->addHour());

        $this->artisan('discovery:refresh-scores', ['--only' => 'attractions', '--dry-run' => true])
            ->expectsOutputToContain('attractions: 1 scanned, 1 would change')
            ->assertSuccessful();
        $this->assertSame(77.0, (float) $attraction->fresh()->discovery_score);

        $this->artisan('discovery:refresh-scores', ['--only' => 'attractions'])
            ->expectsOutputToContain('attractions: 1 scanned, 1 updated')
            ->assertSuccessful();

        $fresh = $attraction->fresh();
        $this->assertEqualsWithDelta(34.0, (float) $fresh->discovery_score, 0.01); // rating 24 + verified 10
        $this->assertTrue($updatedAt->equalTo($fresh->updated_at));

        // A second run finds nothing to change.
        $this->artisan('discovery:refresh-scores', ['--only' => 'attractions'])
            ->expectsOutputToContain('attractions: 1 scanned, 0 updated')
            ->assertSuccessful();
    }
}
