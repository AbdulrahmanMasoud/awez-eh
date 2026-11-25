<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Alerts\Enums\AlertFrequency;
use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Identity\Models\User;
use App\Domains\Requests\Enums\RequestArea;
use Database\Factories\Support\Random;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchAlert>
 */
final class SearchAlertFactory extends Factory
{
    #[\Override]
    protected $model = SearchAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'area' => RequestArea::RealEstate,
            'query' => Random::element(fake(), ['شقة إيجار ٣ غرف', 'دوبلكس للبيع', 'محل على شارع رئيسي', 'أرض في السكنية الثامنة']),
            'filters' => [],
            'district_id' => null,
            'location' => null,
            'radius_m' => null,
            'frequency' => Random::element(fake(), AlertFrequency::cases()),
            'is_active' => true,
        ];
    }
}
