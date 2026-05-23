<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Applies the "perceived quality" layer on top of whatever the
 * recommender returned:
 *
 *   1. Freshness adjustment — boost businesses with recent reviews
 *      or active offers, demote stale ones. Tuned to be small enough
 *      not to override strong relevance signals but large enough to
 *      break ties.
 *
 *   2. Maximal Marginal Relevance (MMR) diversification — once we
 *      have N candidates, greedily build the final top-K so that
 *      consecutive slots don't all share the same brand or category.
 *      This is the single biggest UX win the user notices: no more
 *      "five KFC branches in a row" lists.
 *
 * Both passes preserve the original scores on `business->base_score`
 * and `business->fresh_score` so A/B comparisons can still measure
 * what each pass contributed. The final ranked list is returned in
 * place; the caller's response shape never changes.
 *
 * Knobs live in `config/recommendations.php` so product can tune
 * them without redeploying logic.
 */
class RecommendationPostProcessor
{
    /**
     * Apply freshness scoring to every candidate. Returns the same
     * collection re-sorted by the new score.
     *
     * @param  Collection<int, Business>  $candidates
     * @return Collection<int, Business>
     */
    public function applyFreshness(Collection $candidates): Collection
    {
        $cfg = (array) config('recommendations.freshness', []);
        $boostRecentReview = (float) ($cfg['recent_review_boost'] ?? 0.05);
        $boostActiveOffer = (float) ($cfg['active_offer_boost'] ?? 0.10);
        $penaltyStale = (float) ($cfg['stale_penalty'] ?? 0.15);
        $recentWindowDays = (int) ($cfg['recent_window_days'] ?? 30);
        $staleThresholdDays = (int) ($cfg['stale_threshold_days'] ?? 90);

        $now = Carbon::now();
        $recentCutoff = $now->copy()->subDays($recentWindowDays);
        $staleCutoff = $now->copy()->subDays($staleThresholdDays);

        return $candidates
            ->map(function (Business $b) use ($boostRecentReview, $boostActiveOffer, $penaltyStale, $recentCutoff, $staleCutoff) {
                // Preserve the original score so the metrics rollup
                // can attribute lift correctly.
                $baseScore = isset($b->final_score) ? (float) $b->final_score : 0.0;
                $b->base_score = $baseScore;

                $delta = 0.0;

                // Recent review activity → mild boost.
                $hasRecentReview = $b->reviews->isNotEmpty()
                    && Carbon::parse($b->reviews->max('created_at'))->gte($recentCutoff);
                if ($hasRecentReview) {
                    $delta += $boostRecentReview;
                }

                // Active offer right now → larger boost.
                if ($b->activeOffers->isNotEmpty()) {
                    $delta += $boostActiveOffer;
                }

                // No reviews in 90+ days → stale penalty. Newly-onboarded
                // businesses with zero reviews skip this — we don't
                // punish empty lists, only ones that used to be active.
                if ($b->total_reviews > 0) {
                    $latestReview = $b->reviews->max('created_at');
                    $isStale = $latestReview === null
                        || Carbon::parse($latestReview)->lt($staleCutoff);
                    if ($isStale) {
                        $delta -= $penaltyStale;
                    }
                }

                $b->fresh_score = round($baseScore + $delta, 4);
                $b->final_score = $b->fresh_score;
                return $b;
            })
            ->sortByDesc('final_score')
            ->values();
    }

    /**
     * Build a diversified top-K from the (already-scored) candidate
     * pool using MMR.
     *
     *   score(picked, c) = λ · relevance(c)
     *                    − (1 − λ) · max similarity(c, p) for p in picked
     *
     * Similarity here is a cheap symbolic match: same parent_brand,
     * same category, same area. Exact embedding similarity is a
     * future upgrade.
     *
     * @param  Collection<int, Business>  $candidates
     * @return Collection<int, Business>
     */
    public function diversify(Collection $candidates, int $topK): Collection
    {
        $cfg = (array) config('recommendations.diversity', []);
        $lambda = max(0.0, min(1.0, (float) ($cfg['lambda'] ?? 0.7)));
        $brandDup = (float) ($cfg['brand_duplicate_penalty'] ?? 1.0);
        $categoryDup = (float) ($cfg['category_duplicate_penalty'] ?? 0.5);
        $areaDup = (float) ($cfg['area_duplicate_penalty'] ?? 0.25);

        if ($candidates->count() <= 1 || $topK <= 1) {
            return $candidates->take($topK)->values();
        }

        // Normalise score to [0, 1] across the candidate pool so the
        // λ blending is meaningful even when raw scores live in
        // wildly different ranges (the trad scorer tops out around
        // 1.5; the AI engine returns values up to 100).
        $scores = $candidates->map(fn (Business $b) => (float) ($b->final_score ?? 0.0));
        $min = $scores->min();
        $max = $scores->max();
        $range = max(0.0001, $max - $min);
        $candidates = $candidates->map(function (Business $b) use ($min, $range) {
            $b->mmr_relevance = (((float) ($b->final_score ?? 0.0)) - $min) / $range;
            return $b;
        })->values();

        $picked = collect();
        $remaining = $candidates;

        while ($picked->count() < $topK && $remaining->isNotEmpty()) {
            $bestIdx = null;
            $bestMmr = -INF;
            foreach ($remaining as $idx => $cand) {
                $maxSim = $picked->isEmpty() ? 0.0 : $picked->map(
                    fn (Business $p) => $this->similarity($p, $cand, $brandDup, $categoryDup, $areaDup),
                )->max();
                $mmr = $lambda * $cand->mmr_relevance - (1.0 - $lambda) * $maxSim;
                if ($mmr > $bestMmr) {
                    $bestMmr = $mmr;
                    $bestIdx = $idx;
                }
            }
            if ($bestIdx === null) {
                break;
            }
            $winner = $remaining[$bestIdx];
            $winner->mmr_score = round($bestMmr, 4);
            $picked->push($winner);
            $remaining = $remaining->forget($bestIdx)->values();
        }

        return $picked->values();
    }

    /**
     * Similarity heuristic — 1.0 means "should never be next to each
     * other in the list" (same brand). Lower values stack penalties
     * for shared category / area only.
     */
    private function similarity(Business $a, Business $b, float $brandDup, float $catDup, float $areaDup): float
    {
        // Same parent brand → max similarity.
        if ($a->parent_id !== null && $a->parent_id === $b->parent_id) {
            return $brandDup;
        }
        if ($a->id === $b->parent_id || $b->id === $a->parent_id) {
            return $brandDup;
        }

        $sim = 0.0;
        if ($a->category_id !== null && $a->category_id === $b->category_id) {
            $sim += $catDup;
        }
        if ($a->area && $b->area && strcasecmp((string) $a->area, (string) $b->area) === 0) {
            $sim += $areaDup;
        }

        return min(1.0, $sim);
    }
}
