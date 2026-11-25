<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prices are still open (OPEN_QUESTIONS 2): only the demo seeds packages.
        Schema::create('featured_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            $table->enum('applies_to', ['property', 'provider', 'place', 'used_item']);
            $table->integer('duration_days');
            $table->bigInteger('price');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['applies_to', 'is_active', 'sort_order']);
        });

        DB::statement('alter table featured_packages add constraint featured_packages_values_check check (duration_days > 0 and price >= 0)');

        Schema::create('featured_placements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Not uuidMorphs: the index below starts with these two columns and covers theirs.
            $table->string('featurable_type');
            $table->uuid('featurable_id');
            // Null: granted by an admin.
            $table->foreignUuid('featured_package_id')->nullable()->index()->constrained()->restrictOnDelete();
            // The advertiser who bought it.
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->bigInteger('price_paid')->nullable();
            $table->char('currency', 3)->nullable();
            $table->foreignUuid('granted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['featurable_type', 'featurable_id', 'ends_at']);
            // What is featured now, across all content.
            $table->index('ends_at');
        });

        DB::statement("alter table featured_placements add constraint featured_placements_featurable_type_check check (featurable_type in ('property', 'provider', 'place', 'used_item'))");
        DB::statement('alter table featured_placements add constraint featured_placements_ends_after_start_check check (ends_at > starts_at)');
        DB::statement('alter table featured_placements add constraint featured_placements_currency_check check (price_paid is null or currency is not null)');
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_placements');
        Schema::dropIfExists('featured_packages');
    }
};
