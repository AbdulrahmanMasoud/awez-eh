<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
final class DistrictFactory extends Factory
{
    #[\Override]
    protected $model = District::class;

    public function definition(): array
    {
        $number = fake()->numberBetween(1, 30);

        return [
            'city_id' => City::factory(),
            'name_ar' => "المجاورة {$number}",
            'name_en' => "Neighbourhood {$number}",
            'slug' => fake()->unique()->slug(2),
            'center' => null,
            'sort_order' => $number,
        ];
    }
}
