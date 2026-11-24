<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Services\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * A craftsman's visit unless told otherwise. A brokered viewing has no counterpart.
 *
 * @extends Factory<Booking>
 */
final class BookingFactory extends Factory
{
    #[\Override]
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'kind' => BookingKind::ServiceVisit,
            'bookable_type' => new Provider()->getMorphClass(),
            'bookable_id' => Provider::factory()->published(),
            'resident_id' => User::factory(),
            'counterpart_id' => fn (array $attributes): mixed => Provider::query()->whereKey($attributes['bookable_id'])->value('user_id'),
            'scheduled_at' => CarbonImmutable::now()->addDays(fake()->numberBetween(1, 10))->setTime(fake()->numberBetween(9, 20), 0),
            'note' => null,
            'estimated_price' => null,
            'is_urgent' => false,
            'status' => BookingStatus::Requested,
        ];
    }

    /** A viewing of a property, run by its owner. */
    public function propertyViewing(): static
    {
        return $this->state([
            'kind' => BookingKind::PropertyViewing,
            'bookable_type' => new Property()->getMorphClass(),
            'bookable_id' => Property::factory()->published(),
            'counterpart_id' => fn (array $attributes): mixed => Property::query()->whereKey($attributes['bookable_id'])->value('user_id'),
        ]);
    }

    /** About a given property or provider, with its owner as counterpart (none if brokered). */
    public function of(Model $bookable): static
    {
        $viewing = $bookable instanceof Property;

        return $this->state([
            'kind' => $viewing ? BookingKind::PropertyViewing : BookingKind::ServiceVisit,
            'bookable_type' => $bookable->getMorphClass(),
            'bookable_id' => $bookable->getKey(),
            'counterpart_id' => $viewing && $bookable->brokered ? null : $bookable->getAttribute('user_id'),
        ]);
    }

    public function inStatus(BookingStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
