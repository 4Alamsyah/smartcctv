<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostGIS must be enabled before any migration defines geometry/geography columns.
 * Requires the postgis package to be installed on the PostgreSQL server
 * (e.g. `postgresql-16-postgis-3` or the official `postgis/postgis` Docker image).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology');
    }

    public function down(): void
    {
        // Never drop postgis on rollback: other tables/geometry columns depend on
        // the extension and dropping it mid-lifecycle is destructive and rarely intended.
    }
};
