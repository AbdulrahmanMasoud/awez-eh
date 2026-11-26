<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public list filters and sorts published properties only, so its indexes are partial:
     * small, and each one answers a sort or a filter of GET /api/v1/properties (DECISIONS 107).
     *
     * @var array<string, string>
     */
    private const array PUBLIC_PROPERTY_INDEXES = [
        'properties_published_newest_index' => 'id',
        'properties_published_price_index' => 'price, id',
        'properties_published_area_index' => 'area_m2',
        'properties_published_bedrooms_index' => 'bedrooms',
        'properties_published_finishing_index' => 'finishing',
        'properties_published_furnished_index' => 'furnished',
        'properties_published_brokered_index' => 'brokered',
    ];

    public function up(): void
    {
        // Edited by its owner since its last review: resuming a paused listing then goes through
        // review, and a rejected one may be submitted again (ContentStatusMachine). One block per
        // table, so static analysis reads each table's columns.
        Schema::table('properties', function (Blueprint $table): void {
            $table->boolean('changed_since_review')->default(false);
        });
        Schema::table('providers', function (Blueprint $table): void {
            $table->boolean('changed_since_review')->default(false);
        });
        Schema::table('places', function (Blueprint $table): void {
            $table->boolean('changed_since_review')->default(false);
        });
        Schema::table('used_items', function (Blueprint $table): void {
            $table->boolean('changed_since_review')->default(false);
        });

        foreach (self::PUBLIC_PROPERTY_INDEXES as $name => $columns) {
            DB::statement("create index {$name} on properties ({$columns}) where status = 'published'");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::PUBLIC_PROPERTY_INDEXES) as $name) {
            DB::statement("drop index {$name}");
        }

        foreach (['used_items', 'places', 'providers', 'properties'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('changed_since_review');
            });
        }
    }
};
