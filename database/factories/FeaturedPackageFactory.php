<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Promotions\Enums\FeaturableType;
use App\Domains\Promotions\Models\FeaturedPackage;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Demo prices only: the real ones are still open (OPEN_QUESTIONS 2).
 *
 * @extends Factory<FeaturedPackage>
 */
final class FeaturedPackageFactory extends Factory
{
    #[\Override]
    protected $model = FeaturedPackage::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'تمييز أسبوع',
            'name_en' => 'Featured for a week',
            'applies_to' => FeaturableType::Property,
            'duration_days' => 7,
            'price' => Money::of(250, 'EGP'),
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
