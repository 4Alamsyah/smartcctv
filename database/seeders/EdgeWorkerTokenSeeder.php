<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the headless service account the Python edge worker authenticates
 * as, and issues it a Sanctum token scoped to a single ability so a leaked
 * edge-device token can never do anything but POST violations.
 */
class EdgeWorkerTokenSeeder extends Seeder
{
    public function run(): void
    {
        $serviceAccount = User::firstOrCreate(
            ['email' => 'edge-worker@dishub.jakarta.go.id'],
            ['name' => 'Edge ML Worker (service account)', 'password' => bcrypt(str()->random(40))]
        );

        $serviceAccount->tokens()->where('name', 'python-edge-worker')->delete();
        $token = $serviceAccount->createToken('python-edge-worker', ['edge:ingest']);

        $this->command?->warn('EDGE WORKER TOKEN (save now, shown once, copy into the Python worker .env as LARAVEL_API_TOKEN): '.$token->plainTextToken);
    }
}
