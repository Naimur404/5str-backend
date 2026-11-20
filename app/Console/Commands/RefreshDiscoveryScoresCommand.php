<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Models\Business;
use App\Services\CacheInvalidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes the stored, location-independent discovery_score for
 * attractions and businesses.
 *
 * API requests used to rewrite this column on every read, with the
 * caller's distance baked in, so the stored value (used for sorting)
 * depended on whoever called last. Requests now only compute
 * location-aware scores in memory; this command keeps the stored value
 * fresh instead.
 *
 * Writes go through the query builder: only discovery_score changes,
 * updated_at is left alone (attractions "popular" filters on it) and no
 * per-row model events fire. Caches are flushed once per table.
 */
class RefreshDiscoveryScoresCommand extends Command
{
    protected $signature = 'discovery:refresh-scores
                            {--only= : Limit to "attractions" or "businesses"}
                            {--chunk=500 : Rows processed per batch}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Recompute the stored, location-independent discovery_score for attractions and businesses';

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only !== null && ! in_array($only, ['attractions', 'businesses'], true)) {
            $this->error('--only must be "attractions" or "businesses".');

            return self::INVALID;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        foreach (['attractions' => Attraction::class, 'businesses' => Business::class] as $label => $model) {
            if ($only !== null && $only !== $label) {
                continue;
            }

            [$scanned, $changed] = $this->refresh($model, $chunk, $dryRun);

            $this->info(sprintf('%s: %d scanned, %d %s', $label, $scanned, $changed, $dryRun ? 'would change' : 'updated'));

            if ($changed > 0 && ! $dryRun) {
                $label === 'attractions'
                    ? CacheInvalidationService::onAttractionChange()
                    : CacheInvalidationService::onBusinessChange();
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Attraction>|class-string<Business>  $model
     * @return array{0: int, 1: int} [scanned, changed]
     */
    private function refresh(string $model, int $chunk, bool $dryRun): array
    {
        $scanned = 0;
        $changed = 0;
        $columns = array_merge(['id', 'discovery_score'], $model::DISCOVERY_COLUMNS);

        $model::query()->select($columns)->chunkById($chunk, function ($rows) use ($model, $dryRun, &$scanned, &$changed) {
            $activity = $model::discoveryActivity($rows->pluck('id'));

            foreach ($rows as $row) {
                $scanned++;
                $score = round($row->calculateDiscoveryScore(null, null, $activity[$row->id] ?? null), 2);

                if (abs((float) $row->discovery_score - $score) < 0.005) {
                    continue;
                }

                $changed++;

                if (! $dryRun) {
                    DB::table($row->getTable())->where('id', $row->id)->update(['discovery_score' => $score]);
                }
            }
        });

        return [$scanned, $changed];
    }
}
