<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Filament's notification bell reads `data` as JSON (`data->>'format'`), and Laravel's
 * notifications table stores it as text. Every row Laravel writes there is JSON, so the cast is
 * safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table notifications alter column data type jsonb using data::jsonb');
    }

    public function down(): void
    {
        DB::statement('alter table notifications alter column data type text using data::text');
    }
};
