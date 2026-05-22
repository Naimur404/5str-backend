<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Force-recompute the generated `location` POINT columns added by the
 * `add_spatial_indexes` migration.
 *
 * Generated STORED columns are populated automatically on INSERT/UPDATE,
 * so freshly migrated rows are correct out of the box. This command is
 * the safety net for cases where the migration was run while rows
 * already existed and the platform's MySQL did not auto-populate them
 * (rare, but seen on some managed services).
 *
 *     php artisan spatial:backfill              # all geo tables
 *     php artisan spatial:backfill businesses   # one table
 *     php artisan spatial:backfill --chunk=5000 # bigger batches
 */
class BackfillSpatialColumnsCommand extends Command
{
    protected $signature = 'spatial:backfill
                            {table? : Limit to a single table}
                            {--chunk=1000 : Rows updated per batch}';

    protected $description = 'Force-recompute generated POINT columns for existing rows.';

    /**
     * @var array<string, array{lat: string, lng: string, point: string}>
     */
    private const TABLES = [
        'businesses'             => ['lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location'],
        'attractions'            => ['lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location'],
        'business_submissions'   => ['lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location'],
        'attraction_submissions' => ['lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location'],
        'users'                  => ['lat' => 'current_latitude', 'lng' => 'current_longitude', 'point' => 'current_location'],
        'search_logs'            => ['lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location'],
        'views'                  => ['lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location'],
        'endpoint_analytics'     => ['lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location'],
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->warn('Spatial columns only exist on MySQL — nothing to backfill.');

            return self::SUCCESS;
        }

        $only = $this->argument('table');
        $chunk = max(100, (int) $this->option('chunk'));

        $targets = $only ? array_intersect_key(self::TABLES, [$only => true]) : self::TABLES;
        if ($only && empty($targets)) {
            $this->error("Unknown table: {$only}");

            return self::FAILURE;
        }

        foreach ($targets as $table => $cols) {
            $this->backfill($table, $cols, $chunk);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{lat: string, lng: string, point: string}  $cols
     */
    private function backfill(string $table, array $cols, int $chunk): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $cols['point'])) {
            $this->warn("Skip {$table}: spatial column missing (run `php artisan migrate` first).");

            return;
        }

        // Touch every row so the generated column is recomputed. The actual
        // SET is a no-op (id = id) but MySQL still re-runs the stored
        // expression. Bounded by chunk + primary key for safety.
        $total = DB::table($table)->count();
        if ($total === 0) {
            $this->info("✓ {$table}: empty");

            return;
        }

        $this->line("Backfilling {$table} ({$total} rows, chunk {$chunk})…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $minId = (int) DB::table($table)->min('id');
        $maxId = (int) DB::table($table)->max('id');

        for ($from = $minId; $from <= $maxId; $from += $chunk) {
            $to = $from + $chunk - 1;
            $touched = DB::table($table)
                ->whereBetween('id', [$from, $to])
                ->update(['id' => DB::raw('id')]);
            $bar->advance($touched);
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ {$table}: done");
    }
}
