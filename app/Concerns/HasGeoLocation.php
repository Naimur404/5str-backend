<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Geo-search helpers shared by every model with `latitude` / `longitude`
 * decimal columns. On MySQL the helpers target the spatial-indexed
 * generated `location` POINT column for O(log n) lookups; on SQLite (and
 * any other driver without spatial support) they transparently fall
 * back to in-SQL Haversine math so tests keep working.
 *
 * Public methods returning a `whereRaw` chain on the active query:
 *
 *     applyNearbyFilter($q, $col, $lat, $lng, $radiusKm)
 *     applyDistanceSelect($q, $table, $col, $lat, $lng, $alias='distance')
 *     applyDistanceOrder($q, $col, $lat, $lng, 'asc'|'desc')
 *
 * The host scope wires the appropriate column name (`location`,
 * `current_location`, `user_location`, …) and table name. The helpers
 * are static so non-Eloquent code (raw query builders) can call them too.
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
        if (self::supportsSpatial()) {
            return $query->whereRaw(
                "ST_Distance_Sphere(`{$pointColumn}`, POINT(?, ?)) <= ?",
                [$longitude, $latitude, $radiusKm * 1000],
            );
        }

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
        if (self::supportsSpatial()) {
            return $query->selectRaw(
                "{$table}.*, ST_Distance_Sphere(`{$pointColumn}`, POINT(?, ?)) / 1000 AS {$alias}",
                [$longitude, $latitude],
            );
        }

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
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if (self::supportsSpatial()) {
            return $query->orderByRaw(
                "ST_Distance_Sphere(`{$pointColumn}`, POINT(?, ?)) {$direction}",
                [$longitude, $latitude],
            );
        }

        return $query->orderByRaw(
            self::haversineKmExpression($latColumn ?? 'latitude', $lngColumn ?? 'longitude')." {$direction}",
            [$latitude, $longitude, $latitude],
        );
    }

    private static function supportsSpatial(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * 6371 km Haversine. Bindings expected: [lat, lng, lat].
     */
    private static function haversineKmExpression(string $latColumn, string $lngColumn): string
    {
        return "(6371 * acos(cos(radians(?)) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians(?)) + sin(radians(?)) * sin(radians({$latColumn}))))";
    }
}
