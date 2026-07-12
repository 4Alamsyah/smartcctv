<?php

namespace App\Services\Spatial;

use Illuminate\Support\Facades\DB;

/**
 * Spatially joins `traffic_violations` and `crm_reports` into density-based
 * hotspot clusters (PostGIS ST_ClusterDBSCAN) so the daily report can rank
 * where enforcement pressure is actually needed, not just list raw rows.
 */
class HotspotClusterService
{
    /**
     * @return array<int, array{
     *     cluster_id: int, violation_count: int, crm_count: int,
     *     dominant_category: string|null, lng: float, lat: float, total: int
     * }>
     */
    public function clusterRecentActivity(int $lookbackHours = 24, float $epsMeters = 250.0, int $minPoints = 3): array
    {
        $since = now()->subHours($lookbackHours);

        $rows = DB::select(
            <<<'SQL'
                WITH points AS (
                    SELECT id, 'violation' AS source, violation_type AS category, location
                    FROM traffic_violations
                    WHERE detected_at >= ? AND status <> 'dismissed'

                    UNION ALL

                    SELECT id, 'crm' AS source, category, location
                    FROM crm_reports
                    WHERE created_at >= ? AND location IS NOT NULL AND status <> 'rejected'
                ),
                clustered AS (
                    SELECT
                        *,
                        ST_ClusterDBSCAN(ST_Transform(location, 3857), eps := ?, minpoints := ?) OVER () AS cluster_id
                    FROM points
                )
                SELECT
                    cluster_id,
                    COUNT(*) FILTER (WHERE source = 'violation')::int AS violation_count,
                    COUNT(*) FILTER (WHERE source = 'crm')::int AS crm_count,
                    MODE() WITHIN GROUP (ORDER BY category) AS dominant_category,
                    ST_X(ST_Centroid(ST_Collect(location))) AS lng,
                    ST_Y(ST_Centroid(ST_Collect(location))) AS lat
                FROM clustered
                WHERE cluster_id IS NOT NULL
                GROUP BY cluster_id
                ORDER BY COUNT(*) DESC
                SQL,
            [$since, $since, $epsMeters, $minPoints]
        );

        return array_map(fn ($row) => [
            'cluster_id' => (int) $row->cluster_id,
            'violation_count' => (int) $row->violation_count,
            'crm_count' => (int) $row->crm_count,
            'dominant_category' => $row->dominant_category,
            'lng' => (float) $row->lng,
            'lat' => (float) $row->lat,
            'total' => (int) $row->violation_count + (int) $row->crm_count,
        ], $rows);
    }
}
