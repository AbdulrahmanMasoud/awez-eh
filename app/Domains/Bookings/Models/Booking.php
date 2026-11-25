<?php

declare(strict_types=1);

namespace App\Domains\Bookings\Models;

use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Policies\BookingPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Reviews\Models\Review;
use App\Support\Casts\MoneyCast;
use App\Support\Casts\PointCast;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A viewing of a property or a craftsman's visit. Its status changes through BookingStateMachine,
 * and every change is a BookingEvent.
 */
#[Fillable(['scheduled_at', 'address_note', 'location', 'note', 'estimated_price', 'is_urgent'])]
#[UseFactory(BookingFactory::class)]
#[UsePolicy(BookingPolicy::class)]
final class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The property to view, or the craftsman who visits.
     *
     * @return MorphTo<Model, $this>
     */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    /**
     * The owner or the craftsman; none for a brokered viewing, which the platform runs.
     *
     * @return BelongsTo<User, $this>
     */
    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterpart_id');
    }

    /**
     * The booking's events, oldest first. Not `events`: Rector reads `->events` as Eloquent's old
     * $events property and renames it.
     *
     * @return HasMany<BookingEvent, $this>
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->oldest();
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'scheduled_at' => 'datetime',
            'location' => PointCast::class,
            'estimated_price' => MoneyCast::class,
            'is_urgent' => 'boolean',
            'status' => BookingStatus::class,
        ];
    }
}
