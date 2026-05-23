<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UserSignalsService;
use Illuminate\Console\Command;

/**
 * Rebuild per-identity affinity signals from user_events.
 *
 * Usage:
 *   php artisan signals:rebuild               # last hour (scheduled)
 *   php artisan signals:rebuild --since="6 hours"
 *   php artisan signals:rebuild --since=all   # full catchup
 *
 * Scheduled hourly via routes/console.php.
 */
class RebuildUserSignalsCommand extends Command
{
    protected $signature = 'signals:rebuild {--since=1 hour}';
    protected $description = 'Roll user_events into compact per-user affinity signals.';

    public function handle(UserSignalsService $service): int
    {
        $since = (string) $this->option('since');
        $count = $service->refreshAll($since);

        $this->info(sprintf('Refreshed %d identity rollups (window: %s)', $count, $since));
        return self::SUCCESS;
    }
}
