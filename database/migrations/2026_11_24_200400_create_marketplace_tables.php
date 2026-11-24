<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('used_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->enum('category', ['furniture', 'appliances', 'electronics', 'mobiles', 'vehicles', 'clothes', 'kids', 'books', 'other']);
            $table->string('title');
            $table->text('description');
            $table->text('search_text');
            $table->bigInteger('price');
            $table->char('currency', 3);
            $table->boolean('negotiable')->default(false);
            $table->enum('condition', ['new', 'like_new', 'good', 'fair']);
            $table->foreignUuid('city_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->index()->constrained()->restrictOnDelete();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            // Moderation (invariant 9): new content waits for an admin.
            $table->enum('status', ['draft', 'pending_review', 'published', 'paused', 'sold', 'rented', 'rejected', 'archived'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('condition');
            $table->index('price');
            $table->index(['status', 'published_at']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'used_items_search_text_trgm_index', 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_items');
    }
};
