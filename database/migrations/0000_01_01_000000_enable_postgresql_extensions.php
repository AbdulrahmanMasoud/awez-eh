<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Runs before every other migration: geography columns need PostGIS (invariant 5) and
 * searched text needs pg_trgm's trigram indexes (invariant 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
