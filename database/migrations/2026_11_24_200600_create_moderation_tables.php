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
        Schema::create('reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // The resident who reported.
            $table->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            // Short names from the enforced morph map (property, provider, place, ...).
            $table->uuidMorphs('reportable');
            $table->enum('reason', ['fake', 'scam', 'spam', 'wrong_price', 'offensive', 'other']);
            $table->text('note')->nullable();
            $table->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $table->foreignUuid('resolved_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // A resident reports an item once while the report is open.
        DB::statement("create unique index reports_one_open_per_reporter_index on reports (user_id, reportable_type, reportable_id) where status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
