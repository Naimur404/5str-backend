<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Geo-search helpers shared by every model with `latitude` / `longitude`
 * decimal columns.
 *
 * The implementation deliberately uses an in-SQL Haversine expression
 * (great-circle distance on a 6371 km sphere) rather than
 * `ST_Distance_Sphere` against the indexed `location` POINT column.
 * Reasoning:
 *
 *   - MariaDB's `ST_Distance_Sphere` rejects POINTs with the default
 *     SRID 0 that the migration's trigger produces (it expects a
 *     geographic SRID on its second argument), which surfaced as
 *     `/api/v1/categories/{id}/businesses` and other "near me" paths
 *     returning 500 in production.
 *   - MySQL 8 accepts both forms; Haversine is a few ms slower in
 *     theory but indistinguishable in practice once the category /
 *     active filter has narrowed the candidate set, which it always
 *     does for these endpoints.
 *   - Same code path now runs locally on SQLite, in CI, on MySQL 8,
 *     and on MariaDB. No more "works on my machine" surprises.
 *
 * The spatial column and SPATIAL INDEX from the migration still exist
 * and can be lit up later for unfiltered "nearest in the world" type
 * queries via a separate helper if and when we need it.
 *
 * Public API (called from model scopes):
 *
 *     applyNearbyFilter($q, $col, $lat, $lng, $radiusKm)
 *     applyDistanceSelect($q, $table, $col, $lat, $lng, $alias='distance')
 *     applyDistanceOrder($q, $col, $lat, $lng, 'asc'|'desc')
 *
 * `$col` is kept in the signature for backwards compatibility with
 * existing callers (and future spatial use); it's currently ignored.
 */
trait HasGeoLocation
{
    public static function applyNearbyFilter(
        Builder $query,
        string $pointColumn,
        float $latitude,
        float $longitude,
        float $radiusKm,
        ?string $latColumn = null,
        ?string $lngColumn = null,
    ): Builder {
        unset($pointColumn);

        return $query->whereRaw(
            self::haversineKmExpression($latColumn ?? 'latitude', $lngColumn ?? 'longitude').' <= ?',
            [$latitude, $longitude, $latitude, $radiusKm],
        );
    }

    public static function applyDistanceSelect(
        Builder $query,
        string $table,
        string $pointColumn,
        float $latitude,
        float $longitude,
        string $alias = 'distance',
        ?string $latColumn = null,
        ?string $lngColumn = null,
    ): Builder {
        unset($pointColumn);

        return $query->selectRaw(
            "{$table}.*, ".self::haversineKmExpression($latColumn ?? 'latitude', $lngColumn ?? 'longitude')." AS {$alias}",
            [$latitude, $longitude, $latitude],
        );
    }

    public static function applyDistanceOrder(
        Builder $query,
        string $pointColumn,
        float $latitude,
        float $longitude,
        string $direction = 'asc',
        ?string $latColumn = null,
        ?string $lngColumn = null,
    ): Builder {
        unset($pointColumn);

        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw(
            self::haversineKmExpression($latColumn ?? 'latitude', $lngColumn ?? 'longitude')." {$direction}",
            [$latitude, $longitude, $latitude],
        );
    }

    /**
     * 6371 km Haversine. Bindings expected: [lat, lng, lat].
     */
    private static function haversineKmExpression(string $latColumn, string $lngColumn): string
    {
        return "(6371 * acos(cos(radians(?)) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians(?)) + sin(radians(?)) * sin(radians({$latColumn}))))";
    }
}
