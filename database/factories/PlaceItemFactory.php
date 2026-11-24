<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceItem;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaceItem>
 */
final class PlaceItemFactory extends Factory
{
    #[\Override]
    protected $model = PlaceItem::class;

    public function definition(): array
    {
        return [
            'place_id' => Place::factory(),
            'name' => fake()->randomElement(['طبق كشري كبير', 'ساندوتش فول', 'رغيف عيش فينو', 'زجاجة مياه', 'كيلو طماطم']),
            'description' => null,
            'price' => Money::of(fake()->numberBetween(5, 120), 'EGP'),
            'is_available' => true,
            'sort_order' => fake()->numberBetween(1, 30),
        ];
    }
}
