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
        Schema::create('place_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->string('icon');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        // Separately: a key can reference only a primary key that already exists, and Laravel adds
        // the primary key after the table's other commands.
        Schema::table('place_categories', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('place_categories')->restrictOnDelete();
        });

        Schema::create('places', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // A place an admin adds has no owner until someone claims it.
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignUuid('place_category_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('search_text');
            // The place's public numbers, not a person's.
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->foreignUuid('city_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->index()->constrained()->restrictOnDelete();
            $table->string('address_note')->nullable();
            $table->geography('location', subtype: 'point', srid: 4326);
            $table->boolean('delivers')->default(false);
            // Moderation (invariant 9): new content waits for an admin.
            $table->enum('status', ['draft', 'pending_review', 'published', 'paused', 'sold', 'rented', 'rejected', 'archived'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('delivers');
            $table->index(['status', 'published_at']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'places_search_text_trgm_index', 'gin');
        });

        Schema::create('place_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('price')->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('is_available')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['place_id', 'sort_order']);
        });

        DB::statement('alter table place_items add constraint place_items_currency_check check (price is null or currency is not null)');

        Schema::create('place_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['place_id', 'ends_at']);
        });

        DB::statement('alter table place_offers add constraint place_offers_ends_after_start_check check (ends_at > starts_at)');

        // Cairo wall-clock times, the one exception to "time is UTC" (DECISIONS.md).
        Schema::create('opening_hours', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->index(['place_id', 'weekday']);
        });

        // 0 is Sunday, as Carbon counts.
        DB::statement('alter table opening_hours add constraint opening_hours_weekday_check check (weekday between 0 and 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('place_offers');
        Schema::dropIfExists('place_items');
        Schema::dropIfExists('places');
        Schema::dropIfExists('place_categories');
    }
};
