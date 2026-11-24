<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
final class CityFactory extends Factory
{
    #[\Override]
    protected $model = City::class;

    public function definition(): array
    {
        $name = Random::element(fake(), [
            ['مدينة السادات', 'Sadat City'], ['شبين الكوم', 'Shebin El Kom'], ['منوف', 'Menouf'],
            ['أشمون', 'Ashmoun'], ['قويسنا', 'Quesna'], ['الباجور', 'El Bagour'], ['تلا', 'Tala'],
            ['السنطة', 'El Santa'], ['بركة السبع', 'Berket El Sabaa'], ['الشهداء', 'El Shohada'],
        ]);

        return [
            'name_ar' => $name[0],
            'name_en' => $name[1],
            'slug' => fake()->unique()->slug(2),
            'center' => SadatCity::randomPoint($this->faker, 50_000),
            'is_active' => true,
        ];
    }
}
