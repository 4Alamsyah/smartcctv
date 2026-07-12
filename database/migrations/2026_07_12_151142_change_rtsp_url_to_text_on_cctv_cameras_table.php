<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * rtsp_url is encrypted at rest (App\Models\CctvCamera's 'encrypted' cast).
     * Ciphertext (base64 JSON of iv/value/mac) comfortably exceeds varchar(255)
     * even for a short plaintext URL, so the column needs unbounded text.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cctv_cameras ALTER COLUMN rtsp_url TYPE TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cctv_cameras ALTER COLUMN rtsp_url TYPE VARCHAR(255)');
    }
};
