<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the right recommendation strategy for a user based on
 * how much signal we actually have for them.
 *
 * The ladder, in order:
 *
 *   1. PERSONALIZED — user has ≥ N events in the last K days. Use
 *      the regular `RecommendationService` pipeline. (No-op here;
 *      the controller stays on the existing path.)
 *
 *   2. AREA_TRENDING — too little signal. Show what is trending in
 *      the user's area right now, filtered to "open within next 30
 *      minutes" when we have business hours, otherwise just by
 *      view count in the last 24h. Frontend renders a "popular
 *      near you" header instead of "for you".
 *
 *   3. NATIONAL_TRENDING — no area context either. Top-trending
 *      businesses across the country in the last 24h.
 *
 *   4. FEATURED — last resort. Hand-curated featured businesses,
 *      ordered by `is_featured DESC, overall_rating DESC`. We
 *      always have at least these to show.
 *
 * The controller surfaces the chosen rung as `algorithm` in the
 * response so the mobile client can adjust its copy. That makes
 * cold-start a feature, not a hidden fallback.
 */
class ColdStartLadder
{
    public const RUNG_PERSONALIZED = 'personalized';
    public const RUNG_AREA_TRENDING = 'area_trending';
    public const RUNG_NATIONAL_TRENDING = 'national_trending';
    public const RUNG_FEATURED = 'featured';

    public function pickRung(?string $anonId, ?int $userId, ?string $area): string
    {
        $minEvents = (int) config('recommendations.cold_start.min_signal_events', 3);
        $lookbackDays = (int) config('recommendations.cold_start.lookback_days', 14);

        $signalCount = $this->countRecentEvents($anonId, $userId, $lookbackDays);

        if ($signalCount >= $minEvents) {
            return self::RUNG_PERSONALIZED;
        }

        if ($area) {
            return self::RUNG_AREA_TRENDING;
        }

        return self::RUNG_NATIONAL_TRENDING;
    }

    /**
     * Build the candidate pool for a non-personalized rung. Returns
     * the same shape (`Business` with the relations the rest of the
     * pipeline expects) so the post-processor (freshness + MMR)
     * can run on top of it without special-casing.
     *
     * @return Collection<int, Business>
     */
    public function fallbackCandidates(string $rung, ?string $area, int $count): Collection
    {
        $base = Business::query()
            ->with([
                'category:id,name,icon_image',
                'subcategory:id,name',
                'logoImage:id,business_id,image_url,image_type',
                'coverImage:id,business_id,image_url,image_type',
                'galleryImages:id,business_id,image_url,image_type',
                'activeOffers:id,business_id,title,description,discount_percentage,valid_to',
                'reviews' => fn ($q) => $q->latest()->limit(3)->select(
                    'id',
                    'reviewable_id',
                    'user_id',
                    'overall_rating',
                    'title',
                    'created_at',
                ),
            ])
            ->where('is_active', true);

        $candidates = match ($rung) {
            self::RUNG_AREA_TRENDING => $this->areaTrending($base->clone(), $area, $count),
            self::RUNG_NATIONAL_TRENDING => $this->nationalTrending($base->clone(), $count),
            default => $this->featured($base->clone(), $count),
        };

        if ($candidates->isEmpty()) {
            // Final safety net so a user never gets an empty
            // recommendations response.
            $candidates = $this->featured($base->clone(), $count);
        }

        return $candidates->map(function (Business $b) {
            // Seed `final_score` so the post-processor has something
            // to start from. `discovery_score` is the closest
            // existing aggregate proxy for relevance in this rung.
            $b->final_score = (float) ($b->discovery_score ?? 0) / 100;
            $b->personalization_applied = false;
            $b->contributing_algorithms = ['cold_start'];
            return $b;
        })->values();
    }

    private function countRecentEvents(?string $anonId, ?int $userId, int $lookbackDays): int
    {
        if (! $anonId && ! $userId) {
            return 0;
        }

        $since = Carbon::now()->subDays(max(1, $lookbackDays));

        $q = UserEvent::query()->where('created_at', '>=', $since);

        // Match either identifier — covers the case where the user
        // has both an anon id (logged-out events earlier) and a
        // user_id (after login) by union-counting both.
        $q->where(function ($inner) use ($anonId, $userId) {
            if ($anonId) {
                $inner->orWhere('anon_id', $anonId);
            }
            if ($userId) {
                $inner->orWhere('user_id', $userId);
            }
        });

        // Only "intent-bearing" events count. A `home_open` or
        // `recommendation_impression` is too low-information to be
        // a personalization signal.
        $q->whereIn('event', [
            'business_view',
            'business_click',
            'attraction_view',
            'attraction_click',
            'offering_view',
            'search',
            'search_click',
            'favorite_added',
            'review_submitted',
            'recommendation_click',
        ]);

        return $q->count();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return Collection<int, Business>
     */
    private function areaTrending($query, ?string $area, int $count): Collection
    {
        if (! $area) {
            return collect();
        }

        // Trending = views in last 24h within this area, weighted
        // by total reviews so a single bot-like spike doesn't take
        // over the list.
        $since = Carbon::now()->subDay();

        $candidateIds = DB::table('views')
            ->select('viewable_id', DB::raw('COUNT(*) as view_count'))
            ->where('viewable_type', Business::class)
            ->where('created_at', '>=', $since)
            ->where('user_area', $area)
            ->groupBy('viewable_id')
            ->orderByDesc('view_count')
            ->limit($count * 3)
            ->pluck('viewable_id')
            ->all();

        if (empty($candidateIds)) {
            // Falls through to featured so we never return zero.
            return collect();
        }

        return $query
            ->whereIn('id', $candidateIds)
            ->orderByDesc('overall_rating')
            ->orderByDesc('discovery_score')
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, Business>
     */
    private function nationalTrending($query, int $count): Collection
    {
        return $query
            ->orderByDesc('discovery_score')
            ->orderByDesc('overall_rating')
            ->orderByDesc('total_reviews')
            ->limit($count)
            ->get();
    }

    /**
     * @return Collection<int, Business>
     */
    private function featured($query, int $count): Collection
    {
        return $query
            ->where('is_featured', true)
            ->orderByDesc('overall_rating')
            ->orderByDesc('total_reviews')
            ->limit($count)
            ->get();
    }
}
