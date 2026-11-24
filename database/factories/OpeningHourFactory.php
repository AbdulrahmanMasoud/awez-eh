<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Places\Models\OpeningHour;
use App\Domains\Places\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
final class OpeningHourFactory extends Factory
{
    #[\Override]
    protected $model = OpeningHour::class;

    public function definition(): array
    {
        return [
            'place_id' => Place::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            // Cairo wall-clock times.
            'opens_at' => '09:00',
            'closes_at' => '23:00',
        ];
    }
}
