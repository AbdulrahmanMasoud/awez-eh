<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('favoritable');
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id']);
        });

        DB::statement("alter table favorites add constraint favorites_favoritable_type_check check (favoritable_type in ('property', 'provider', 'place', 'used_item'))");

        Schema::create('search_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            // The values of requests.area.
            $table->enum('area', ['real_estate', 'services', 'places', 'marketplace', 'other']);
            $table->string('query')->nullable();
            $table->text('search_text')->nullable();
            // Shaped by a Data class in step 3.4.
            $table->jsonb('filters')->default('{}');
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->integer('radius_m')->nullable();
            $table->enum('frequency', ['instant', 'daily']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            // The jobs that send alerts read the active ones by frequency.
            $table->index(['is_active', 'frequency']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'search_alerts_search_text_trgm_index', 'gin');
        });

        // "Near" needs both a point and a radius.
        DB::statement('alter table search_alerts add constraint search_alerts_radius_check check ((location is null) = (radius_m is null) and (radius_m is null or radius_m > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('search_alerts');
        Schema::dropIfExists('favorites');
    }
};
