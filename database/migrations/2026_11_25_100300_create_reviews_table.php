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
        Schema::create('reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // A review needs a booking, and a booking gets one review.
            $table->foreignUuid('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignUuid('reviewer_id')->index()->constrained('users')->cascadeOnDelete();
            $table->uuidMorphs('reviewable');
            $table->smallInteger('rating');
            $table->text('comment')->nullable();
            // The reviewed side answers once.
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->foreignUuid('hidden_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });

        DB::statement('alter table reviews add constraint reviews_rating_check check (rating between 1 and 5)');
        DB::statement("alter table reviews add constraint reviews_reviewable_type_check check (reviewable_type in ('provider', 'property'))");
        DB::statement('alter table reviews add constraint reviews_reply_check check ((reply is null) = (replied_at is null))');
        DB::statement('alter table reviews add constraint reviews_hidden_check check (is_hidden = (hidden_at is not null))');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
