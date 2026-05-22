<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Geo-search trait for any Eloquent model that has decimal `latitude`
 * and `longitude` columns plus the matching generated `POINT` column
 * (added by 2026_05_22_140000_add_spatial_indexes).
 *
 * Provides:
 *   Model::query()->nearby($lat, $lng, $radiusKm)
 *       ->withDistance($lat, $lng)
 *       ->orderByNearest($lat, $lng)
 *
 * `nearby()` uses MySQL `ST_Distance_Sphere` against the spatial index,
 * making it ~O(log n) instead of the full-table scan you get with
 * Haversine math in PHP.
 *
 * The host class can override `geoColumns()` to point at non-default
 * column names (e.g. users uses `current_latitude`/`current_longitude`).
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasGeoLocation
{
    /**
     * @return array{lat: string, lng: string, point: string}
     */
    protected function geoColumns(): array
    {
        return [
            'lat' => 'latitude',
            'lng' => 'longitude',
            'point' => 'location',
        ];
    }

    public function scopeNearby(Builder $query, float $latitude, float $longitude, float $radiusKm = 5): Builder
    {
        $cols = $this->geoColumns();

        // ST_Distance_Sphere returns metres. Use the generated POINT col so
        // the spatial index can prune rows. SRID order in POINT is (lng, lat).
        return $query
            ->whereNotNull($cols['lat'])
            ->whereNotNull($cols['lng'])
            ->whereRaw(
                "ST_Distance_Sphere(`{$cols['point']}`, ST_SRID(POINT(?, ?), 4326)) <= ?",
                [$longitude, $latitude, $radiusKm * 1000],
            );
    }

    public function scopeWithDistance(Builder $query, float $latitude, float $longitude, string $alias = 'distance_meters'): Builder
    {
        $cols = $this->geoColumns();

        return $query->selectRaw(
            "{$query->getModel()->getTable()}.*, ST_Distance_Sphere(`{$cols['point']}`, ST_SRID(POINT(?, ?), 4326)) AS {$alias}",
            [$longitude, $latitude],
        );
    }

    public function scopeOrderByNearest(Builder $query, float $latitude, float $longitude): Builder
    {
        $cols = $this->geoColumns();

        return $query->orderByRaw(
            "ST_Distance_Sphere(`{$cols['point']}`, ST_SRID(POINT(?, ?), 4326)) ASC",
            [$longitude, $latitude],
        );
    }
}
