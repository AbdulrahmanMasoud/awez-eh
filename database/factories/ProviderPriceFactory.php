<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Services\Enums\PriceUnit;
use App\Domains\Services\Models\Provider;
use App\Domains\Services\Models\ProviderPrice;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderPrice>
 */
final class ProviderPriceFactory extends Factory
{
    #[\Override]
    protected $model = ProviderPrice::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'label' => fake()->randomElement(['المعاينة', 'ساعة العمل', 'الزيارة داخل المدينة']),
            'price_from' => Money::of(fake()->numberBetween(2, 10) * 50, 'EGP'),
            // A "from" price: a range is the exception.
            'price_to' => null,
            'unit' => PriceUnit::Visit,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
