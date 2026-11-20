<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds SPATIAL indexes to every geo table so "near me" queries become
 * O(log n) instead of full scans. Works on both MySQL 8+ and
 * MariaDB 10.5+ — historically this migration has been bitten by
 * cross-vendor differences:
 *
 *   - MariaDB rejects the two-arg ST_SRID(POINT(...), 4326) helper.
 *   - MariaDB rejects a single-statement
 *     "ADD COLUMN ... GENERATED ALWAYS AS (...) STORED NOT NULL,
 *      ADD SPATIAL INDEX ..." mixed ALTER.
 *   - MariaDB does not allow a literal DEFAULT on POINT columns.
 *
 * Strategy that works on both engines:
 *   1. Add a plain nullable POINT column.
 *   2. Backfill it from the existing lat/lng decimals.
 *   3. Tighten to NOT NULL (a SPATIAL INDEX requires NOT NULL).
 *   4. Add the SPATIAL INDEX in its own ALTER.
 *   5. Install BEFORE INSERT / BEFORE UPDATE triggers so future writes
 *      keep the POINT in sync — same role generated columns would have
 *      played, just portable.
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
        ['table' => 'businesses',            'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',         'index' => 'businesses_location_spatial_idx'],
        ['table' => 'attractions',           'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',         'index' => 'attractions_location_spatial_idx'],
        ['table' => 'business_submissions',  'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',         'index' => 'business_submissions_location_spatial_idx'],
        ['table' => 'attraction_submissions','lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',         'index' => 'attraction_submissions_location_spatial_idx'],
        ['table' => 'users',                 'lat' => 'current_latitude', 'lng' => 'current_longitude', 'point' => 'current_location', 'index' => 'users_current_location_spatial_idx'],
        ['table' => 'search_logs',           'lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location',    'index' => 'search_logs_user_location_spatial_idx'],
        ['table' => 'views',                 'lat' => 'user_latitude',    'lng' => 'user_longitude',    'point' => 'user_location',    'index' => 'views_user_location_spatial_idx'],
        ['table' => 'endpoint_analytics',    'lat' => 'latitude',         'lng' => 'longitude',         'point' => 'location',         'index' => 'endpoint_analytics_location_spatial_idx'],
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

            $this->installSpatialColumn($t);
            $this->installSpatialTriggers($t);
        }
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        foreach (array_reverse($this->targets) as $t) {
            if (! Schema::hasTable($t['table'])) {
                continue;
            }

            $this->dropSpatialTriggers($t);

            if (! Schema::hasColumn($t['table'], $t['point'])) {
                continue;
            }

            try {
                DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $t['table'], $t['index']));
            } catch (\Throwable) {
                // Index may not exist if a prior down() was partial — keep going.
            }

            DB::statement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $t['table'], $t['point']));
        }
    }

    /**
     * Add the POINT column, backfill it, lock it down, and index it.
     *
     * Each step is its own ALTER because MariaDB chokes on the
     * combined "add generated stored NOT NULL column + add spatial
     * index" form.
     */
    private function installSpatialColumn(array $t): void
    {
        $table = $t['table'];
        $point = $t['point'];
        $lat   = $t['lat'];
        $lng   = $t['lng'];
        $index = $t['index'];

        if (! Schema::hasColumn($table, $point)) {
            // Step 1: nullable POINT column. POINT cannot have a DEFAULT
            // on MariaDB, so we add it nullable then backfill.
            DB::statement(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` POINT NULL', $table, $point));

            // Step 2: backfill from existing rows. Order is (lng, lat)
            // so ST_Distance_Sphere stays consistent with the rest of
            // the codebase.
            DB::statement(sprintf(
                'UPDATE `%s` SET `%s` = POINT(IFNULL(`%s`, 0), IFNULL(`%s`, 0))',
                $table,
                $point,
                $lng,
                $lat,
            ));

            // Step 3: a SPATIAL INDEX requires the column to be NOT NULL.
            DB::statement(sprintf('ALTER TABLE `%s` MODIFY COLUMN `%s` POINT NOT NULL', $table, $point));
        }

        // Step 4: spatial index, idempotent.
        if (! $this->indexExists($table, $index)) {
            DB::statement(sprintf('ALTER TABLE `%s` ADD SPATIAL INDEX `%s` (`%s`)', $table, $index, $point));
        }
    }

    /**
     * Replace any existing keep-in-sync triggers with fresh ones.
     */
    private function installSpatialTriggers(array $t): void
    {
        $table = $t['table'];
        $point = $t['point'];
        $lat   = $t['lat'];
        $lng   = $t['lng'];
        $bi    = sprintf('%s_%s_bi', $table, $point);
        $bu    = sprintf('%s_%s_bu', $table, $point);

        DB::statement(sprintf('DROP TRIGGER IF EXISTS `%s`', $bi));
        DB::statement(sprintf(
            'CREATE TRIGGER `%s` BEFORE INSERT ON `%s` FOR EACH ROW SET NEW.`%s` = POINT(IFNULL(NEW.`%s`, 0), IFNULL(NEW.`%s`, 0))',
            $bi,
            $table,
            $point,
            $lng,
            $lat,
        ));

        DB::statement(sprintf('DROP TRIGGER IF EXISTS `%s`', $bu));
        DB::statement(sprintf(
            'CREATE TRIGGER `%s` BEFORE UPDATE ON `%s` FOR EACH ROW SET NEW.`%s` = POINT(IFNULL(NEW.`%s`, 0), IFNULL(NEW.`%s`, 0))',
            $bu,
            $table,
            $point,
            $lng,
            $lat,
        ));
    }

    private function dropSpatialTriggers(array $t): void
    {
        $bi = sprintf('%s_%s_bi', $t['table'], $t['point']);
        $bu = sprintf('%s_%s_bu', $t['table'], $t['point']);

        DB::statement(sprintf('DROP TRIGGER IF EXISTS `%s`', $bi));
        DB::statement(sprintf('DROP TRIGGER IF EXISTS `%s`', $bu));
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index],
        );

        return (int) ($row->c ?? 0) > 0;
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
