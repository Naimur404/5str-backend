<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Schedule
|--------------------------------------------------------------------------
|
| Closure-based console commands and the application schedule both live
| here in the slim Laravel 11+ layout. Anything CPU- or DB-heavy uses
| `withoutOverlapping()` plus a Cache::lock inside the command for
| multi-host safety.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Trending data — daily snapshot recomputed every minute ───────────
Schedule::command('analytics:calculate-trending daily')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('analytics:calculate-trending weekly')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('analytics:calculate-trending monthly')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Recommendation similarity matrix.
Schedule::command('business:calculate-similarities')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();

// Roll fresh user_events into per-identity affinity signals so the
// recommender always reads from a compact, indexed table instead of
// scanning weeks of raw events on every request.
Schedule::command('signals:rebuild --since="1 hour"')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Category business-count sync (backup to observer).
Schedule::command('categories:sync-business-counts')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// Prune cross-category false-positive similarities.
Schedule::command('business:clean-wrong-similarities')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground();

// ── Maintenance ──────────────────────────────────────────────────────
Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('05:00')
    ->runInBackground();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('05:15')
    ->runInBackground();

Schedule::command('activitylog:clean')
    ->dailyAt('05:30')
    ->runInBackground();

// ── Horizon ──────────────────────────────────────────────────────────
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('horizon:trim')->daily();
