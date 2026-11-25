<?php

declare(strict_types=1);

use App\Support\Database\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array STATUSES = [
        'requested', 'confirmed', 'declined', 'on_the_way', 'in_progress',
        'completed', 'cancelled_by_resident', 'cancelled_by_counterpart', 'no_show',
    ];

    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('kind', ['property_viewing', 'service_visit']);
            $table->uuidMorphs('bookable');
            // Restrict: a booking is history, and its events can never go.
            $table->foreignUuid('resident_id')->index()->constrained('users')->restrictOnDelete();
            // Null for a brokered viewing, which the platform runs.
            $table->foreignUuid('counterpart_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('address_note')->nullable();
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->text('note')->nullable();
            $table->bigInteger('estimated_price')->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->enum('status', self::STATUSES)->default('requested');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->spatialIndex('location');
        });

        // A viewing is of a property and a visit is by a craftsman; only a visit is on the way or in progress.
        DB::statement("alter table bookings add constraint bookings_bookable_check check ((kind = 'property_viewing' and bookable_type = 'property') or (kind = 'service_visit' and bookable_type = 'provider'))");
        DB::statement("alter table bookings add constraint bookings_visit_status_check check (kind = 'service_visit' or status not in ('on_the_way', 'in_progress'))");
        DB::statement('alter table bookings add constraint bookings_currency_check check (estimated_price is null or currency is not null)');

        Schema::create('booking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            $table->enum('from_status', self::STATUSES)->nullable();
            $table->enum('to_status', self::STATUSES);
            // Null: the system. Restrict, because a deleted actor could not be set to null.
            $table->foreignUuid('actor_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The booking's timeline.
            $table->index(['booking_id', 'created_at']);
        });

        AppendOnly::enable('booking_events');
    }

    public function down(): void
    {
        AppendOnly::disable('booking_events');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('bookings');
    }
};
