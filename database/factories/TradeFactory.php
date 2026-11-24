<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Services\Models\Trade;
use Database\Factories\Support\Random;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
final class TradeFactory extends Factory
{
    #[\Override]
    protected $model = Trade::class;

    public function definition(): array
    {
        $trade = Random::element(fake(), [
            ['سباك', 'Plumber', 'wrench'], ['كهربائي', 'Electrician', 'zap'], ['نجار', 'Carpenter', 'hammer'],
            ['نقاش', 'Painter', 'paint-roller'], ['حداد', 'Blacksmith', 'anvil'],
        ]);

        return [
            'name_ar' => $trade[0],
            'name_en' => $trade[1],
            'slug' => fake()->unique()->slug(2),
            'icon' => $trade[2],
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
