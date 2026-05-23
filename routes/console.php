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

// ── Analytics retention ──────────────────────────────────────────────
// Operational analytics tables are time-series data with no long-term
// value beyond the rolling 90-day window. We prune nightly so the hot
// indexes on `views`, `endpoint_analytics`, `search_logs`, and
// `user_events` stay cache-resident on MariaDB.
Schedule::command('analytics:prune --days=90')
    ->dailyAt('05:45')
    ->withoutOverlapping()
    ->runInBackground();

// ── Recommendation metrics rollup ────────────────────────────────────
// Pre-computes daily CTR by (model, bucket, slot) from user_events so
// the admin dashboard can render trend lines without scanning the
// (potentially millions of rows of) raw event store on every page
// load. Runs hourly so today's row stays fresh; the daily run at
// 06:00 finalises yesterday.
Schedule::command('recommendations:rollup-metrics')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// ── Business semantic embeddings ─────────────────────────────────────
// Continuous trickle through the catalog. Limit=200 per run so a
// large catalog is fully covered within ~24h while keeping the
// OpenAI bill predictable. Rows whose source text is unchanged
// (same embedding_hash) are skipped automatically.
Schedule::command('businesses:embed --limit=200')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ── Horizon ──────────────────────────────────────────────────────────
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('horizon:trim')->daily();
