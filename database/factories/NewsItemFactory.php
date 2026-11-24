<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Geography\Models\City;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsItem>
 */
final class NewsItemFactory extends Factory
{
    #[\Override]
    protected $model = NewsItem::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'title' => 'افتتاح سوق الجمعة الجديد في المنطقة السكنية الأولى',
            'body' => 'يفتح السوق أبوابه كل يوم جمعة من الساعة ٨ الصبح.',
            'is_published' => true,
            'published_at' => CarbonImmutable::now()->subDay(),
            'created_by' => null,
        ];
    }
}
