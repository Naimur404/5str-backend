<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SPATIAL indexes to every geo table so "near me" queries become
 * O(log n) instead of full scans. Compatible with both MySQL 8+ and
 * MariaDB (10.5+).
 *
 * Strategy:
 *   - Keep the existing decimal `latitude` / `longitude` columns intact;
 *     application code still reads/writes them as before.
 *   - Add a generated POINT column populated from those decimals (using
 *     plain POINT(lng, lat) with default SRID — the two-argument
 *     ST_SRID() form is MySQL 8 only and MariaDB rejects it).
 *   - Index it with a SPATIAL INDEX so ST_Distance_Sphere queries
 *     execute against the index.
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

            // Generated POINT column. We deliberately do NOT wrap with
            // ST_SRID(..., 4326) because that two-argument form is MySQL
            // 8 only — MariaDB rejects it with "Incorrect parameter count
            // in the call to native function 'ST_SRID'". Plain POINT()
            // (default SRID 0) works on both engines and is fine for
            // ST_Distance_Sphere queries which expect (lng, lat) order.
            DB::statement(sprintf(
                "ALTER TABLE `%s`
                  ADD COLUMN `%s` POINT
                    GENERATED ALWAYS AS (
                      POINT(IFNULL(`%s`, 0), IFNULL(`%s`, 0))
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
