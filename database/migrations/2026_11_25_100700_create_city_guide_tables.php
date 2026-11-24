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
        // Admin content, in Arabic.
        Schema::create('guide_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->enum('category', ['emergency', 'hospital', 'transport', 'government', 'utility', 'education', 'other']);
            $table->string('title');
            $table->text('body')->nullable();
            $table->text('search_text');
            // Text, not a PhoneNumber: emergency lines are three digits (122), and hotlines five.
            $table->string('phone_display')->nullable();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['city_id', 'category', 'sort_order']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'guide_entries_search_text_trgm_index', 'gin');
        });

        // The pharmacy on duty at night.
        Schema::create('duty_pharmacies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained()->cascadeOnDelete();
            // A Cairo calendar date, like opening hours (DECISIONS 40); starts_at and ends_at are UTC.
            $table->date('duty_date');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->unique(['place_id', 'duty_date']);
            $table->index('duty_date');
        });

        DB::statement('alter table duty_pharmacies add constraint duty_pharmacies_ends_after_start_check check (ends_at > starts_at)');

        Schema::create('news_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('body');
            $table->text('search_text');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['city_id', 'is_published', 'published_at']);
            $table->index([new Expression('search_text gin_trgm_ops')], 'news_items_search_text_trgm_index', 'gin');
        });

        DB::statement('alter table news_items add constraint news_items_published_check check (not is_published or published_at is not null)');
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
        Schema::dropIfExists('duty_pharmacies');
        Schema::dropIfExists('guide_entries');
    }
};
