<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds MySQL 8 SPATIAL indexes to every geo table so "near me" queries
 * become O(log n) instead of full scans.
 *
 * Strategy:
 *   - Keep the existing decimal `latitude` / `longitude` columns intact;
 *     application code still reads/writes them as before.
 *   - Add a generated `POINT` column populated from those decimals
 *     (NOT NULL — required for SPATIAL indexes pre-MySQL 8.0.13+ and
 *     safer regardless), with SRID 4326 (WGS-84).
 *   - Index it with a SPATIAL INDEX so `ST_Distance_Sphere` and
 *     `MBRContains` execute against the index.
 *
 * Skipped on SQLite (test runner) — SQLite has no spatial type.
 */
return new class extends Migration
{
    /**
     * Tables whose lat/lng columns get a spatial mirror.
     *
     * @var array<int, array{table: string, lat: string, lng: string, point: string, index: string}>
     */
    private array $targets = [
        ['table' => 'businesses',           'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',      'index' => 'businesses_location_spatial_idx'],
        ['table' => 'attractions',          'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',      'index' => 'attractions_location_spatial_idx'],
        ['table' => 'business_submissions', 'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',      'index' => 'business_submissions_location_spatial_idx'],
        ['table' => 'attraction_submissions','lat' => 'latitude',        'lng' => 'longitude',         'point' => 'location',      'index' => 'attraction_submissions_location_spatial_idx'],
        ['table' => 'users',                'lat' => 'current_latitude', 'lng' => 'current_longitude', 'point' => 'current_location','index' => 'users_current_location_spatial_idx'],
        ['table' => 'search_logs',          'lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location', 'index' => 'search_logs_user_location_spatial_idx'],
        ['table' => 'views',                'lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location', 'index' => 'views_user_location_spatial_idx'],
        ['table' => 'endpoint_analytics',   'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',      'index' => 'endpoint_analytics_location_spatial_idx'],
    ];

    public function up(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        foreach ($this->targets as $t) {
            if (! Schema::hasTable($t['table'])) {
                continue;
            }
            if (! Schema::hasColumn($t['table'], $t['lat']) || ! Schema::hasColumn($t['table'], $t['lng'])) {
                continue;
            }
            if (Schema::hasColumn($t['table'], $t['point'])) {
                continue;
            }

            // Generated POINT column. Nullable lat/lng tables get a stored
            // POINT(0,0) fallback so the index can be built; queries should
            // filter `IS NOT NULL` on the source columns anyway.
            DB::statement(sprintf(
                "ALTER TABLE `%s`
                  ADD COLUMN `%s` POINT
                    GENERATED ALWAYS AS (
                      ST_SRID(POINT(IFNULL(`%s`, 0), IFNULL(`%s`, 0)), 4326)
                    ) STORED NOT NULL,
                  ADD SPATIAL INDEX `%s` (`%s`)",
                $t['table'],
                $t['point'],
                $t['lng'],
                $t['lat'],
                $t['index'],
                $t['point'],
            ));
        }
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        foreach (array_reverse($this->targets) as $t) {
            if (! Schema::hasTable($t['table']) || ! Schema::hasColumn($t['table'], $t['point'])) {
                continue;
            }

            // Drop index first so the column drop succeeds.
            try {
                DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $t['table'], $t['index']));
            } catch (\Throwable $e) {
                // Index may not exist if a prior down() was partial — keep going.
            }

            DB::statement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $t['table'], $t['point']));
        }
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
