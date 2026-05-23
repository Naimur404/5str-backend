<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\BusinessEmbeddingService;
use Illuminate\Console\Command;

/**
 * Generate or refresh semantic embeddings for businesses.
 *
 * Usage:
 *   php artisan businesses:embed                # missing or stale only
 *   php artisan businesses:embed --force        # re-embed everything
 *   php artisan businesses:embed --id=42        # single business
 *   php artisan businesses:embed --limit=500    # cap per run
 *
 * Cost rule of thumb at the time of writing:
 *   text-embedding-3-small  $0.02 / 1M tokens
 *   ~250 tokens per business average → ~$0.005 per 1000 businesses
 *
 * Wired into the schedule (daily, --limit=500) so a continuous
 * trickle covers freshly-edited businesses without ever spiking
 * the bill.
 */
class EmbedBusinessesCommand extends Command
{
    protected $signature = 'businesses:embed
        {--id= : Single business id (skips other filters)}
        {--limit=500 : Max businesses to embed in one run}
        {--force : Re-embed even if embedding_hash is unchanged}';

    protected $description = 'Generate / refresh OpenAI embeddings for businesses.';

    public function handle(BusinessEmbeddingService $service): int
    {
        $force = (bool) $this->option('force');
        $id = $this->option('id');
        $limit = max(1, (int) $this->option('limit'));

        $query = Business::query()->where('is_active', true);

        if ($id) {
            $query->whereKey((int) $id);
        } elseif (! $force) {
            // Only re-embed missing or stale rows when not forced.
            $query->where(function ($q) {
                $q->whereNull('embedding')
                    ->orWhereNull('embedding_hash');
            });
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('Nothing to embed.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Embedding %d business(es), force=%s, limit=%d', $count, $force ? 'yes' : 'no', $limit));

        $processed = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(50, function ($chunk) use ($service, $force, $limit, &$processed, &$failed) {
            foreach ($chunk as $business) {
                if ($processed >= $limit) {
                    return false; // exit chunk loop
                }
                try {
                    $ok = $service->embed($business, force: $force);
                    if ($ok) {
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn(sprintf('  #%d: %s', $business->id, $e->getMessage()));
                }
            }
            return null;
        });

        $this->info(sprintf('Done. embedded=%d failed=%d', $processed, $failed));
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
