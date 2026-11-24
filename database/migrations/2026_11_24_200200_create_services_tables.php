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
        Schema::create('trades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->string('icon');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // One craftsman's profile per account.
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->text('search_text');
            $table->foreignUuid('city_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->index()->constrained()->restrictOnDelete();
            $table->geography('location', subtype: 'point', srid: 4326);
            $table->integer('service_radius_m');
            $table->smallInteger('years_experience')->nullable();
            $table->enum('verification', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_available')->default(false);
            // Counters kept by reviews and bookings; the average rating is computed from them.
            $table->integer('rating_sum')->default(0);
            $table->integer('rating_count')->default(0);
            $table->integer('jobs_done')->default(0);
            // Moderation (invariant 9): new content waits for an admin.
            $table->enum('status', ['draft', 'pending_review', 'published', 'paused', 'sold', 'rented', 'rejected', 'archived'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('verification');
            $table->index('is_available');
            $table->index(['status', 'published_at']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'providers_search_text_trgm_index', 'gin');
        });

        Schema::create('provider_trades', function (Blueprint $table): void {
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('trade_id')->index()->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);

            $table->primary(['provider_id', 'trade_id']);
        });

        DB::statement('create unique index provider_trades_one_primary_index on provider_trades (provider_id) where is_primary');

        Schema::create('provider_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->bigInteger('price_from')->nullable();
            $table->bigInteger('price_to')->nullable();
            $table->char('currency', 3)->nullable();
            $table->enum('unit', ['visit', 'hour', 'job', 'square_meter']);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['provider_id', 'sort_order']);
        });

        DB::statement('alter table provider_prices add constraint provider_prices_currency_check check ((price_from is null and price_to is null) or currency is not null)');
        DB::statement('alter table provider_prices add constraint provider_prices_range_check check (price_from is null or price_to is null or price_to >= price_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_prices');
        Schema::dropIfExists('provider_trades');
        Schema::dropIfExists('providers');
        Schema::dropIfExists('trades');
    }
};
