<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-medialibrary's table, from its stub with a UUID key and UUID morphs (every model
 * is keyed by UUID), jsonb, and the image's own width and height, which the app needs to reserve
 * space before a photo loads. A collection is read in order: owner, collection, position.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->jsonb('manipulations');
            $table->jsonb('custom_properties');
            $table->jsonb('generated_conversions');
            $table->jsonb('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'collection_name', 'order_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
