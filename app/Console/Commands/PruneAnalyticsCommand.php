<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop analytics rows older than the configured retention window.
 *
 * Targets every operational analytics store the platform writes:
 *
 *   - views               (per-business view log)
 *   - endpoint_analytics  (API request log written by middleware)
 *   - search_logs         (user search trail)
 *   - user_events         (consent-gated event ingestion)
 *
 * Default retention is 90 days, matching `config/activitylog.php` so a
 * single policy governs every operational data set. Override per-table
 * via `--days=<n>` or per-table env var; override per-table via
 * `--only=views,user_events`.
 *
 * Implementation notes:
 *   - We bulk-DELETE in chunks (default 5_000 rows per round) to avoid
 *     long lock-holds on hot MariaDB tables. The loop exits as soon as
 *     a round deletes fewer rows than the chunk size, which gives us
 *     ~O(rows/chunk) statements regardless of table size.
 *   - Tables that do not exist (e.g. the test runner against SQLite
 *     before user_events is migrated) are silently skipped — never
 *     fail the daily schedule because a deploy hasn't migrated yet.
 *   - The `created_at` column is required on every targeted table
 *     because we filter on it; if it's missing we skip with a warning
 *     rather than blow up the schedule.
 */
class PruneAnalyticsCommand extends Command
{
    protected $signature = 'analytics:prune
        {--days=90 : Retention window in days. Rows older than now()-days are deleted.}
        {--only= : Comma-separated list of tables to prune. Defaults to all known tables.}
        {--chunk=5000 : Rows deleted per round. Lower this on slow disks.}
        {--dry-run : Count what would be deleted without actually deleting.}';

    protected $description = 'Prune analytics rows older than the retention window (default 90 days).';

    /**
     * @var array<string, array{column: string, label: string}>
     */
    private array $targets = [
        'views' => ['column' => 'created_at', 'label' => 'views'],
        'endpoint_analytics' => ['column' => 'created_at', 'label' => 'endpoint analytics'],
        'search_logs' => ['column' => 'created_at', 'label' => 'search logs'],
        'user_events' => ['column' => 'created_at', 'label' => 'user events'],
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $only = $this->option('only');
        $tables = $only
            ? array_values(array_intersect(array_keys($this->targets), array_map('trim', explode(',', (string) $only))))
            : array_keys($this->targets);

        if (empty($tables)) {
            $this->warn('No matching tables to prune.');
            return self::SUCCESS;
        }

        $totalDeleted = 0;
        foreach ($tables as $table) {
            $totalDeleted += $this->pruneTable($table, $cutoff, $chunk, $dryRun);
        }

        $this->info(sprintf(
            '%s%d rows in total older than %s.',
            $dryRun ? '[dry-run] would delete ' : 'Deleted ',
            $totalDeleted,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }

    private function pruneTable(string $table, Carbon $cutoff, int $chunk, bool $dryRun): int
    {
        if (! Schema::hasTable($table)) {
            $this->line(sprintf('  - %s: table missing, skipped.', $table));
            return 0;
        }

        $column = $this->targets[$table]['column'] ?? 'created_at';
        if (! Schema::hasColumn($table, $column)) {
            $this->warn(sprintf('  - %s: missing %s column, skipped.', $table, $column));
            return 0;
        }

        if ($dryRun) {
            $count = (int) DB::table($table)->where($column, '<', $cutoff)->count();
            $this->line(sprintf('  - %s: would delete %d rows', $table, $count));
            return $count;
        }

        $totalDeleted = 0;
        do {
            $deleted = DB::table($table)
                ->where($column, '<', $cutoff)
                ->limit($chunk)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted >= $chunk);

        $this->line(sprintf('  - %s: deleted %d rows', $this->targets[$table]['label'], $totalDeleted));

        return $totalDeleted;
    }
}
