<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * `withoutOverlapping()` uses a file-based mutex by default which is
     * single-host. On Redis we wrap the heaviest jobs in `Cache::lock()`
     * inside the command itself for cluster safety; the schedule entry
     * still has `withoutOverlapping()` as a belt-and-braces guard.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Trending data — daily snapshot is recomputed every minute so the
        // public /trending endpoint stays fresh.
        $schedule->command('analytics:calculate-trending daily')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        $schedule->command('analytics:calculate-trending weekly')
            ->weeklyOn(1, '02:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('analytics:calculate-trending monthly')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Recommendation similarity matrix.
        $schedule->command('business:calculate-similarities')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // Category business-count sync (backup to observer).
        $schedule->command('categories:sync-business-counts')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Prune cross-category false-positive similarities.
        $schedule->command('business:clean-wrong-similarities')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Trim failed queue jobs older than 7 days.
        $schedule->command('queue:prune-failed --hours=168')
            ->dailyAt('05:00')
            ->runInBackground();

        // Sanctum: drop tokens that expired more than 24 hours ago so the
        // personal_access_tokens table doesn't grow unbounded.
        $schedule->command('sanctum:prune-expired --hours=24')
            ->dailyAt('05:15')
            ->runInBackground();

        // Activity log retention. Default keeps 365 days; tune via
        // config/activitylog.php.
        $schedule->command('activitylog:clean')
            ->dailyAt('05:30')
            ->runInBackground();

        // ── Horizon ────────────────────────────────────────────────────
        // 5-minute snapshots feed the throughput graph; daily trim keeps
        // Redis bounded.
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('horizon:trim')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
