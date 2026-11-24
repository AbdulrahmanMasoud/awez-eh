<?php

declare(strict_types=1);

use App\Support\Database\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every chat opened and every viewing booked on a brokered listing (invariant 2).
        Schema::create('leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->restrictOnDelete();
            // Restrict: step 1.4 blanks a deleted resident's personal data instead (DECISIONS 41).
            $table->foreignUuid('resident_id')->index()->constrained('users')->restrictOnDelete();
            $table->enum('channel', ['chat', 'viewing']);
            // The conversation or the booking that created it.
            $table->string('source_type');
            $table->uuid('source_id');
            // A correction is a new row that voids an earlier one.
            $table->uuid('voids_lead_id')->nullable()->unique();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'created_at']);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreign('voids_lead_id')->references('id')->on('leads')->restrictOnDelete();
        });

        // One lead per conversation or booking; a correction carries the source of the lead it voids.
        DB::statement('create unique index leads_one_per_source_index on leads (source_type, source_id) where voids_lead_id is null');
        DB::statement("alter table leads add constraint leads_source_check check ((channel = 'chat' and source_type = 'conversation') or (channel = 'viewing' and source_type = 'booking'))");
        DB::statement('alter table leads add constraint leads_void_check check ((voids_lead_id is null) = (void_reason is null) and voids_lead_id <> id)');

        AppendOnly::enable('leads');

        // A property's commission history; its state is the sum of its entries (CommissionBalance).
        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('property_agreement_id')->index()->constrained()->restrictOnDelete();
            $table->foreignUuid('lead_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->enum('kind', ['expected', 'invoiced', 'collected', 'cancelled']);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            // The deal the commission is on, in the same currency.
            $table->bigInteger('deal_price')->nullable();
            $table->date('occurred_on');
            $table->foreignUuid('recorded_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'occurred_on']);
        });

        DB::statement('alter table commission_entries add constraint commission_entries_amount_check check (amount > 0 and (deal_price is null or deal_price > 0))');

        AppendOnly::enable('commission_entries');
    }

    public function down(): void
    {
        AppendOnly::disable('commission_entries');
        Schema::dropIfExists('commission_entries');
        AppendOnly::disable('leads');
        Schema::dropIfExists('leads');
    }
};
