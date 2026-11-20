<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates impression + click events into the
 * `recommendation_metrics` table so the admin dashboard can render
 * CTR by (model, bucket, slot) in O(rows-of-rollup) instead of
 * O(rows-of-user_events).
 *
 * Idempotent — running twice for the same day produces identical
 * results because we DELETE-then-INSERT each (day, model, bucket,
 * position) tuple.
 *
 * Default window is "yesterday" + "today" so we always have an
 * up-to-date partial for today and a final for yesterday. Override
 * with --since=YYYY-MM-DD --until=YYYY-MM-DD for backfills.
 */
class RollupRecommendationMetricsCommand extends Command
{
    protected $signature = 'recommendations:rollup-metrics
        {--since= : Start date (YYYY-MM-DD). Defaults to yesterday.}
        {--until= : End date inclusive (YYYY-MM-DD). Defaults to today.}';

    protected $description = 'Rollup recommendation impressions/clicks into daily CTR metrics.';

    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse((string) $this->option('since'))->startOfDay()
            : Carbon::yesterday();

        $until = $this->option('until')
            ? Carbon::parse((string) $this->option('until'))->endOfDay()
            : Carbon::today()->endOfDay();

        $this->info(sprintf('Rolling up recommendations from %s to %s', $since->toDateString(), $until->toDateString()));

        $cursor = $since->copy();
        $totalDays = 0;

        while ($cursor->lte($until)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $rolled = $this->rollupDay($dayStart, $dayEnd);
            $this->line(sprintf('  %s: %d rows', $cursor->toDateString(), $rolled));
            $totalDays++;
            $cursor->addDay();
        }

        $this->info("Done. {$totalDays} day(s) rolled up.");
        return self::SUCCESS;
    }

    private function rollupDay(Carbon $start, Carbon $end): int
    {
        // Pull (impressions, clicks) per (model, bucket, position)
        // from user_events. Both events share rec_position because
        // logClick copies it from the matching impression row.
        $rows = DB::table('user_events')
            ->selectRaw('
                rec_model AS model,
                rec_bucket AS bucket,
                rec_position AS position,
                SUM(CASE WHEN event = "recommendation_impression" THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN event = "recommendation_click" THEN 1 ELSE 0 END) AS clicks,
                AVG(CASE WHEN event = "recommendation_impression" THEN rec_score ELSE NULL END) AS avg_score
            ')
            ->whereIn('event', ['recommendation_impression', 'recommendation_click'])
            ->whereNotNull('rec_model')
            ->whereNotNull('rec_position')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('rec_model', 'rec_bucket', 'rec_position')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $day = $start->toDateString();

        // Replace this day's rollup atomically.
        DB::transaction(function () use ($rows, $day) {
            DB::table('recommendation_metrics')
                ->whereDate('day', $day)
                ->whereIn('model', $rows->pluck('model')->unique()->all())
                ->delete();

            $now = Carbon::now();
            $insertable = $rows->map(fn ($r) => [
                'day' => $day,
                'model' => $r->model,
                'bucket' => $r->bucket,
                'position' => (int) $r->position,
                'impressions' => (int) $r->impressions,
                'clicks' => (int) $r->clicks,
                'ctr' => (int) $r->impressions > 0
                    ? round($r->clicks / max(1, $r->impressions), 6)
                    : 0,
                'avg_score' => $r->avg_score !== null ? round((float) $r->avg_score, 4) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('recommendation_metrics')->insertOrIgnore($insertable);
        });

        return $rows->count();
    }
}
