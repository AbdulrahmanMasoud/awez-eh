<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CityGuide\Enums\GuideCategory;
use App\Domains\CityGuide\Models\GuideEntry;
use App\Domains\Geography\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideEntry>
 */
final class GuideEntryFactory extends Factory
{
    #[\Override]
    protected $model = GuideEntry::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'category' => GuideCategory::Emergency,
            'title' => 'الإسعاف',
            'body' => null,
            'phone_display' => '123',
            'location' => null,
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
