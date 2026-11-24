<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Places\Models\PlaceCategory;
use Database\Factories\Support\Random;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaceCategory>
 */
final class PlaceCategoryFactory extends Factory
{
    #[\Override]
    protected $model = PlaceCategory::class;

    public function definition(): array
    {
        $category = Random::element(fake(), [
            ['صيدليات', 'Pharmacies', 'pill'], ['مخابز', 'Bakeries', 'croissant'], ['سوبر ماركت', 'Supermarkets', 'shopping-cart'],
        ]);

        return [
            'parent_id' => null,
            'name_ar' => $category[0],
            'name_en' => $category[1],
            'slug' => fake()->unique()->slug(2),
            'icon' => $category[2],
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
