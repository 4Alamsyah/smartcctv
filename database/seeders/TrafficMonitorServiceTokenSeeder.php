<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the headless service account the Flask traffic-monitor-service
 * authenticates as, and issues it a Sanctum token scoped to `edge:ingest`
 * (the same ability the Python edge worker uses) so it can call
 * GET /api/v1/cctv-cameras/{code} to resolve a camera's decrypted rtsp_url.
 * A separate service account/token from the edge worker's, on purpose: a
 * leaked credential from one service can't be mistaken for the other's, and
 * each can be revoked independently.
 */
class TrafficMonitorServiceTokenSeeder extends Seeder
{
    public function run(): void
    {
        $serviceAccount = User::firstOrCreate(
            ['email' => 'traffic-monitor-service@dishub.jakarta.go.id'],
            ['name' => 'Traffic Monitor Service (service account)', 'password' => bcrypt(str()->random(40))]
        );

        $serviceAccount->tokens()->where('name', 'traffic-monitor-service')->delete();
        $token = $serviceAccount->createToken('traffic-monitor-service', ['edge:ingest']);

        $this->command?->warn('TRAFFIC MONITOR SERVICE TOKEN (save now, shown once, copy into traffic-monitor-service/.env as LARAVEL_API_TOKEN): '.$token->plainTextToken);
    }
}
