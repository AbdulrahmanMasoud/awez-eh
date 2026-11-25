<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Bookings\Models\BookingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The first event of a booking unless told otherwise: the resident asked.
 *
 * @extends Factory<BookingEvent>
 */
final class BookingEventFactory extends Factory
{
    #[\Override]
    protected $model = BookingEvent::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'from_status' => null,
            'to_status' => BookingStatus::Requested,
            'actor_id' => fn (array $attributes): mixed => Booking::query()->whereKey($attributes['booking_id'])->value('resident_id'),
            'note' => null,
        ];
    }
}
