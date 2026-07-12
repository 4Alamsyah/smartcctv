<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * A handful of representative DKI Jakarta busway/parking chokepoints for
 * local development and demoing the dashboard without live RTSP feeds.
 */
class CctvCameraSeeder extends Seeder
{
    public function run(): void
    {
        $cameras = [
            ['code' => 'CCTV-JKT-SDN-014', 'name' => 'Sudirman - Simpang Blok M', 'zone_type' => 'busway_lane', 'lng' => 106.8022, 'lat' => -6.2245],
            ['code' => 'CCTV-JKT-THM-002', 'name' => 'Thamrin - Bundaran HI', 'zone_type' => 'busway_lane', 'lng' => 106.8230, 'lat' => -6.1950],
            ['code' => 'CCTV-JKT-GTM-007', 'name' => 'Gatot Subroto - Kuningan', 'zone_type' => 'general', 'lng' => 106.8306, 'lat' => -6.2251],
            ['code' => 'CCTV-JKT-KMY-011', 'name' => 'Kemayoran - Landmark', 'zone_type' => 'general', 'lng' => 106.8446, 'lat' => -6.1633],
        ];

        foreach ($cameras as $camera) {
            DB::statement(
                <<<'SQL'
                    INSERT INTO cctv_cameras (code, name, rtsp_url, zone_type, location, stationary_threshold_seconds, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), 180, true, now(), now())
                    ON CONFLICT (code) DO NOTHING
                    SQL,
                [
                    $camera['code'],
                    $camera['name'],
                    Crypt::encryptString("rtsp://edge-relay.internal/{$camera['code']}"),
                    $camera['zone_type'],
                    $camera['lng'],
                    $camera['lat'],
                ]
            );
        }
    }
}
