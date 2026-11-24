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
        // "What do you want": public as soon as it is written; reports handle abuse.
        Schema::create('requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->enum('area', ['real_estate', 'services', 'places', 'marketplace', 'other']);
            $table->foreignUuid('trade_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('place_category_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->text('body');
            $table->text('search_text');
            $table->bigInteger('budget')->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignUuid('city_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->enum('status', ['open', 'closed', 'expired'])->default('open');
            $table->timestamp('expires_at');
            $table->integer('replies_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->index('area');
            $table->index(['status', 'expires_at']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'requests_search_text_trgm_index', 'gin');
        });

        DB::statement('alter table requests add constraint requests_currency_check check (budget is null or currency is not null)');
        // A trade belongs to a request for a craftsman, a place category to one for a place.
        DB::statement("alter table requests add constraint requests_trade_area_check check (trade_id is null or area = 'services')");
        DB::statement("alter table requests add constraint requests_place_category_area_check check (place_category_id is null or area = 'places')");

        Schema::create('request_replies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->bigInteger('offered_price')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamps();
        });

        DB::statement('alter table request_replies add constraint request_replies_currency_check check (offered_price is null or currency is not null)');
    }

    public function down(): void
    {
        Schema::dropIfExists('request_replies');
        Schema::dropIfExists('requests');
    }
};
