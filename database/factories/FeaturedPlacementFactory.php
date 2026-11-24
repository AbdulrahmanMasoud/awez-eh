<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Promotions\Models\FeaturedPackage;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\RealEstate\Models\Property;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A week of featuring a property, bought by its owner, active now.
 *
 * @extends Factory<FeaturedPlacement>
 */
final class FeaturedPlacementFactory extends Factory
{
    #[\Override]
    protected $model = FeaturedPlacement::class;

    public function definition(): array
    {
        return [
            'featurable_type' => new Property()->getMorphClass(),
            'featurable_id' => Property::factory()->published(),
            'featured_package_id' => FeaturedPackage::factory(),
            'user_id' => fn (array $attributes): mixed => Property::query()->whereKey($attributes['featurable_id'])->value('user_id'),
            'starts_at' => CarbonImmutable::now()->subDay(),
            'ends_at' => CarbonImmutable::now()->addDays(6),
            'price_paid' => Money::of(250, 'EGP'),
            'granted_by' => null,
            'cancelled_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => ['starts_at' => CarbonImmutable::now()->subDays(20), 'ends_at' => CarbonImmutable::now()->subDays(13)]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['cancelled_at' => CarbonImmutable::now()->subHours(2)]);
    }
}
