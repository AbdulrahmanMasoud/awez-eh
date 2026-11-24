<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->geography('center', subtype: 'point', srid: 4326);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->spatialIndex('center');
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug');
            $table->geography('center', subtype: 'point', srid: 4326)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
            $table->index(['city_id', 'sort_order']);
            $table->spatialIndex('center');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
    }
};
