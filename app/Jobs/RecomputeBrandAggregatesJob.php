<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Roll up branch aggregates onto the parent brand row.
 *
 * In our schema a "brand" is just a `businesses` row with `parent_id`
 * NULL whose own `branches()` are the actual physical locations users
 * can visit. The brand row exists so we can show one canonical brand
 * profile (KFC Bangladesh) and let the show endpoint inject the
 * closest branch, but its `overall_rating`, `total_reviews`, and
 * `discovery_score` would otherwise be permanently stale because
 * reviews and analytics attach to branches, not the parent.
 *
 * This job is dispatched (debounced via WithoutOverlapping) by:
 *   - ReviewObserver after a review affecting a child branch
 *   - BusinessObserver when a child branch's rating fields change
 *
 * It is queued on the `low` Horizon supervisor — these aggregates
 * never need to be sub-second fresh.
 *
 * Aggregation rules:
 *   - `overall_rating` weighted average across active branches that
 *     actually have at least one review (no zero-rating dilution).
 *   - `total_reviews` simple sum across active branches.
 *   - `discovery_score` MAX across active branches (one strong
 *     location should make the brand visible at the top of search).
 */
class RecomputeBrandAggregatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public readonly int $brandId)
    {
        $this->onQueue('low');
    }

    /**
     * Lock per-brand so concurrent review writes can't trigger a
     * thundering herd of recomputes. The other dispatches will be
     * dropped (releasedAfter) within 60s so the eventually-consistent
     * value matches the latest state.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->brandId))
                ->releaseAfter(60)
                ->dontRelease(),
        ];
    }

    public function handle(): void
    {
        /** @var Business|null $brand */
        $brand = Business::find($this->brandId);
        if (! $brand) {
            return;
        }

        // Brand rows always have parent_id = null. If somehow this
        // job was dispatched for a branch (defensive — shouldn't
        // happen) walk up to the real parent.
        if ($brand->parent_id !== null) {
            $brand = $brand->parentBusiness;
            if (! $brand) {
                return;
            }
        }

        $aggregate = DB::table('businesses')
            ->selectRaw('
                COALESCE(SUM(total_reviews), 0) AS total_reviews,
                COALESCE(MAX(discovery_score), 0) AS max_discovery,
                COALESCE(SUM(overall_rating * total_reviews), 0) AS rating_weighted_sum,
                COALESCE(SUM(CASE WHEN total_reviews > 0 THEN total_reviews ELSE 0 END), 0) AS rating_weight
            ')
            ->where('parent_id', $brand->id)
            ->where('is_active', true)
            ->first();

        if (! $aggregate) {
            return;
        }

        $totalReviews = (int) ($aggregate->total_reviews ?? 0);
        $maxDiscovery = (float) ($aggregate->max_discovery ?? 0);
        $ratingWeight = (float) ($aggregate->rating_weight ?? 0);
        $ratingSum = (float) ($aggregate->rating_weighted_sum ?? 0);

        $overallRating = $ratingWeight > 0
            ? round($ratingSum / $ratingWeight, 2)
            : 0.0;

        // Use saveQuietly + isolated update so we don't trigger
        // another BusinessObserver round-trip and another dispatch.
        Business::query()
            ->whereKey($brand->id)
            ->update([
                'overall_rating' => $overallRating,
                'total_reviews' => $totalReviews,
                'discovery_score' => $maxDiscovery,
                'updated_at' => now(),
            ]);
    }
}
