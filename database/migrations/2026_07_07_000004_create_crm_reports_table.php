<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();

            $table->text('raw_text')->comment('Unstructured complaint text as submitted by the citizen');

            // Structured fields extracted by Gemini AI (see GeminiExtractionService).
            $table->enum('category', [
                'illegal_parking', 'busway_lane_intrusion', 'traffic_congestion',
                'signage_damage', 'road_hazard', 'other',
            ])->nullable()->index();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->nullable()->index();
            $table->text('ai_summary')->nullable()->comment('Gemini-generated one-line summary for dispatcher UI');
            $table->decimal('ai_confidence', 4, 3)->nullable()->comment('Gemini structured-extraction confidence 0-1');
            $table->jsonb('ai_raw_response')->nullable()->comment('Full Gemini JSON payload, kept for audit/replay');

            $table->geometry('location', subtype: 'point', srid: 4326)->nullable()
                ->comment('Nullable until Gemini/geocoding resolves coordinates from free-text address');
            $table->string('address_text')->nullable();

            $table->enum('status', ['received', 'processed', 'triaged', 'resolved', 'rejected'])->default('received')->index();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX crm_reports_location_gist ON crm_reports USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_reports');
    }
};
