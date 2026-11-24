<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Models;

use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Identity\Models\User;
use App\Support\Database\IsAppendOnly;
use Database\Factories\BookingEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a booking's timeline. Append-only: never updated or deleted.
 */
#[Fillable(['from_status', 'to_status', 'note'])]
#[UseFactory(BookingEventFactory::class)]
final class BookingEvent extends Model
{
    /** @use HasFactory<BookingEventFactory> */
    use HasFactory;

    use HasUuids;
    use IsAppendOnly;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Who made the change; none when the system did.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => BookingStatus::class,
            'to_status' => BookingStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
