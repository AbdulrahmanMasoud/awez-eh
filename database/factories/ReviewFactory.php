<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Reviews\Models\Review;
use Carbon\CarbonImmutable;
use Database\Factories\Support\Random;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A review of a completed visit, by the resident who booked it.
 *
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    #[\Override]
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->inStatus(BookingStatus::Completed),
            'reviewer_id' => fn (array $attributes): mixed => Booking::query()->whereKey($attributes['booking_id'])->value('resident_id'),
            'reviewable_type' => fn (array $attributes): mixed => Booking::query()->whereKey($attributes['booking_id'])->value('bookable_type'),
            'reviewable_id' => fn (array $attributes): mixed => Booking::query()->whereKey($attributes['booking_id'])->value('bookable_id'),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => Random::element(fake(), [
                'شغل نضيف وفي الميعاد.',
                'محترم جدًا وسعره معقول.',
                'كويس بس اتأخر شوية.',
                null,
            ]),
            'is_hidden' => false,
        ];
    }

    /** Hidden by an admin: only its reviewer still sees it. */
    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_hidden' => true, 'hidden_at' => CarbonImmutable::now()->subDay()]);
    }
}
