<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceOffer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaceOffer>
 */
final class PlaceOfferFactory extends Factory
{
    #[\Override]
    protected $model = PlaceOffer::class;

    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->subDays(fake()->numberBetween(0, 5))->startOfHour();

        return [
            'place_id' => Place::factory(),
            'title' => fake()->randomElement(['خصم ١٠٪ على كل الطلبات', 'التوصيل مجانًا', 'اشتري ٢ والتالت هدية']),
            'description' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addDays(fake()->numberBetween(3, 20)),
        ];
    }
}
