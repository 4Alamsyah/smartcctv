<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cctv_cameras', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('DISHUB asset code, e.g. CCTV-JKT-SDN-014');
            $table->string('name');
            $table->string('rtsp_url')->comment('Encrypted at rest via App\\Casts\\Encrypted cast in the model');
            $table->enum('zone_type', ['general', 'busway_lane'])->default('general');

            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->geometry('lane_geofence', subtype: 'polygon', srid: 4326)->nullable()
                ->comment('Busway lane polygon in camera FOV, used by the edge worker to mask ROI');

            $table->unsignedSmallInteger('stationary_threshold_seconds')->default(180);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_heartbeat_at')->nullable()->comment('Updated by the edge worker on every processed frame batch');
            $table->timestamps();
        });

        DB::statement('CREATE INDEX cctv_cameras_location_gist ON cctv_cameras USING GIST (location)');
        DB::statement('CREATE INDEX cctv_cameras_lane_geofence_gist ON cctv_cameras USING GIST (lane_geofence)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_cameras');
    }
};
