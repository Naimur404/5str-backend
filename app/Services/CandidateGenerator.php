<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recall-first stage of the two-stage recommender.
 *
 * Standard architecture:
 *
 *     candidate generation (this class) → top ~500 cheap candidates
 *     ranking (RecommendationService::calculateBusinessScore)
 *                                       → top 50
 *     post-processing (RecommendationPostProcessor)
 *                                       → top K (the response)
 *
 * Why split it out:
 *
 *   - Today's `getFastRecommendations` scans every active
 *     business and scores all of them. That's fine at 1k rows but
 *     becomes a bottleneck at 100k. Candidate generation caps the
 *     ranking input regardless of catalog size.
 *
 *   - It also forces the retrieval contract to be explicit, which
 *     makes A/B testing the retrieval strategy possible (try
 *     different candidate sets without touching the ranker).
 *
 * Strategies blended (each contributes up to N candidates):
 *
 *   1. Geo proximity (when lat/lng given): closest businesses
 *   2. Category boost: businesses in user's top categories
 *      (from UserSignals.top_categories)
 *   3. Popular: highest discovery_score globally
 *   4. Trending in area: hot last 24h in the user's area
 *
 * Duplicates are merged by id; the highest-scoring source wins
 * placement in the final candidate list.
 */
class CandidateGenerator
{
    /**
     * @param  array<int, float>  $userTopCategories  category_id => weight
     * @return Collection<int, Business>
     */
    public function generate(
        ?float $latitude,
        ?float $longitude,
        ?string $area,
        array $userTopCategories,
        ?array $categoryFilter = null,
        int $count = 200,
    ): Collection {
        $perStrategy = max(20, (int) ceil($count / 3));

        $bucket = collect();

        // 1. Geo proximity
        $bucket = $bucket->merge($this->nearby($latitude, $longitude, $categoryFilter, $perStrategy));

        // 2. Category preferences
        if (! empty($userTopCategories) || ! empty($categoryFilter)) {
            $cats = ! empty($categoryFilter)
                ? $categoryFilter
                : array_keys($userTopCategories);
            $bucket = $bucket->merge($this->byCategory($cats, $perStrategy));
        }

        // 3. Popular fallback
        $bucket = $bucket->merge($this->popular($categoryFilter, $perStrategy));

        // 4. Area-trending if we have one (skipped silently when not).
        if ($area) {
            $bucket = $bucket->merge($this->areaTrending($area, $categoryFilter, $perStrategy));
        }

        // De-dup by id, keep first occurrence (highest-priority
        // strategy that surfaced this business). Cap at $count so
        // the ranker stays bounded regardless of catalog size.
        return $bucket
            ->unique('id')
            ->take($count)
            ->values();
    }

    private function baseQuery(?array $categoryFilter): Builder
    {
        $q = Business::query()
            ->where('is_active', true)
            ->with([
                'category:id,name,icon_image',
                'subcategory:id,name',
                'logoImage:id,business_id,image_url,image_type',
                'coverImage:id,business_id,image_url,image_type',
                'galleryImages:id,business_id,image_url,image_type',
                'activeOffers:id,business_id,title,description,discount_percentage,valid_to',
                'reviews' => fn ($r) => $r->latest()->limit(3)
                    ->select('id', 'reviewable_id', 'user_id', 'overall_rating', 'title', 'created_at'),
            ]);

        if (! empty($categoryFilter)) {
            $q->whereIn('category_id', $categoryFilter);
        }

        return $q;
    }

    /**
     * @return Collection<int, Business>
     */
    private function nearby(?float $latitude, ?float $longitude, ?array $categoryFilter, int $count): Collection
    {
        if ($latitude === null || $longitude === null) {
            return collect();
        }

        return $this->baseQuery($categoryFilter)
            ->selectRaw(
                'businesses.*, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance',
                [$longitude, $latitude],
            )
            ->whereRaw(
                'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?',
                [$longitude, $latitude, 50000], // 50km cap, same as RecommendationService
            )
            ->orderBy('distance')
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, Business>
     */
    private function byCategory(array $categoryIds, int $count): Collection
    {
        if (empty($categoryIds)) return collect();

        return $this->baseQuery(null)
            ->whereIn('category_id', $categoryIds)
            ->orderByDesc('overall_rating')
            ->orderByDesc('discovery_score')
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, Business>
     */
    private function popular(?array $categoryFilter, int $count): Collection
    {
        return $this->baseQuery($categoryFilter)
            ->orderByDesc('discovery_score')
            ->orderByDesc('overall_rating')
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, Business>
     */
    private function areaTrending(string $area, ?array $categoryFilter, int $count): Collection
    {
        // Trending = views in last 24h within this area.
        $since = now()->subDay();

        $candidateIds = DB::table('views')
            ->select('viewable_id', DB::raw('COUNT(*) as view_count'))
            ->where('viewable_type', Business::class)
            ->where('created_at', '>=', $since)
            ->where('user_area', $area)
            ->groupBy('viewable_id')
            ->orderByDesc('view_count')
            ->limit($count)
            ->pluck('viewable_id')
            ->all();

        if (empty($candidateIds)) {
            return collect();
        }

        return $this->baseQuery($categoryFilter)
            ->whereIn('id', $candidateIds)
            ->limit($count)
            ->get();
    }
}
