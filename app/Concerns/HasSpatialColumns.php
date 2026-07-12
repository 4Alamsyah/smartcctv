<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * PDO's pgsql driver returns `geometry`/`geography` columns as raw WKB hex,
 * which is not useful to consumers directly. Rather than a magic Eloquent cast,
 * models expose coordinates explicitly via ST_X/ST_Y/ST_AsGeoJSON select expressions,
 * kept in one place since every spatial table (cameras, violations, CRM reports,
 * patrol schedules) needs the same three query shapes.
 */
trait HasSpatialColumns
{
    /**
     * Append longitude/latitude/GeoJSON projections for a geometry column.
     * Resulting attributes: "{column}_lng", "{column}_lat", "{column}_geojson".
     */
    public function scopeSelectWithCoordinates(Builder $query, string $column = 'location'): Builder
    {
        return $query->addSelect([
            "{$this->getTable()}.*",
        ])->selectRaw(
            "ST_X({$column}::geometry) as {$column}_lng, ".
            "ST_Y({$column}::geometry) as {$column}_lat, ".
            "ST_AsGeoJSON({$column}) as {$column}_geojson"
        );
    }

    /**
     * Append a GeoJSON projection for a geometry column, without ST_X/ST_Y
     * (those require a Point; this works for any geometry type, e.g. the
     * `lane_geofence` Polygon column). Resulting attribute: "{column}_geojson".
     */
    public function scopeSelectGeoJson(Builder $query, string $column): Builder
    {
        return $query->addSelect([
            "{$this->getTable()}.*",
        ])->selectRaw("ST_AsGeoJSON({$column}) as {$column}_geojson");
    }

    /**
     * Filter rows within $radiusMeters of a lng/lat point using the geography
     * cast so the distance is measured in meters over the WGS84 spheroid,
     * not raw degrees.
     */
    public function scopeNear(Builder $query, float $lng, float $lat, float $radiusMeters, string $column = 'location'): Builder
    {
        return $query->whereRaw(
            "ST_DWithin({$column}::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
            [$lng, $lat, $radiusMeters]
        );
    }

    /**
     * Order rows by distance from a point, nearest first. Useful for
     * "closest camera to this CRM report" style lookups.
     */
    public function scopeOrderByDistance(Builder $query, float $lng, float $lat, string $column = 'location'): Builder
    {
        return $query
            ->selectRaw(
                "ST_Distance({$column}::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_meters",
                [$lng, $lat]
            )
            ->orderBy('distance_meters');
    }

    protected static function makePointExpression(float $lng, float $lat): string
    {
        return sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat);
    }

    /**
     * Build a single-ring POLYGON expression from an ordered list of
     * [lng, lat] vertices. The ring is closed automatically (first vertex
     * repeated as the last) since callers only ever hand over the distinct
     * points a user drew on a map.
     */
    protected static function makePolygonExpression(array $points): string
    {
        $ring = $points;
        $ring[] = $points[0];

        $wkt = implode(', ', array_map(
            fn (array $p) => sprintf('%F %F', $p[0], $p[1]),
            $ring
        ));

        return sprintf("ST_SetSRID(ST_GeomFromText('POLYGON((%s))'), 4326)", $wkt);
    }
}
