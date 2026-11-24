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
        Schema::create('properties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->enum('purpose', ['sale', 'rent']);
            $table->enum('type', ['apartment', 'villa', 'duplex', 'house', 'land', 'shop', 'office', 'warehouse', 'factory']);
            $table->string('title');
            $table->text('description');
            $table->text('search_text');
            $table->bigInteger('price');
            $table->char('currency', 3);
            $table->enum('rent_period', ['monthly', 'yearly'])->nullable();
            $table->integer('area_m2');
            $table->smallInteger('bedrooms')->nullable();
            $table->smallInteger('bathrooms')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->boolean('furnished')->default(false);
            $table->enum('finishing', ['unfinished', 'semi_finished', 'finished', 'luxury'])->nullable();
            $table->foreignUuid('city_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('district_id')->index()->constrained()->restrictOnDelete();
            $table->string('address_note')->nullable();
            $table->geography('location', subtype: 'point', srid: 4326);
            // The platform has a written agreement for it: the owner's number is never shown (invariant 2).
            $table->boolean('brokered')->default(false);
            // Moderation (invariant 9): new content waits for an admin.
            $table->enum('status', ['draft', 'pending_review', 'published', 'paused', 'sold', 'rented', 'rejected', 'archived'])->default('pending_review');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->index(['purpose', 'type']);
            $table->index('price');
            $table->index(['status', 'published_at']);
            $table->spatialIndex('location');
            $table->index([new Expression('search_text gin_trgm_ops')], 'properties_search_text_trgm_index', 'gin');
        });

        // A rent has a period and a sale has none.
        DB::statement("alter table properties add constraint properties_rent_period_required_check check ((purpose = 'rent') = (rent_period is not null))");

        Schema::create('property_agreements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->index()->constrained()->cascadeOnDelete();
            $table->string('owner_name');
            $table->string('owner_phone');
            $table->integer('commission_rate_basis_points')->nullable();
            $table->bigInteger('commission_fixed')->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('signed_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The commission is a rate or a fixed amount, never both and never neither.
        DB::statement('alter table property_agreements add constraint property_agreements_one_commission_check check (num_nonnulls(commission_rate_basis_points, commission_fixed) = 1)');
        DB::statement('alter table property_agreements add constraint property_agreements_currency_check check (commission_fixed is null or currency is not null)');
        DB::statement('alter table property_agreements add constraint property_agreements_ends_after_signing_check check (ends_on is null or ends_on >= signed_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_agreements');
        Schema::dropIfExists('properties');
    }
};
