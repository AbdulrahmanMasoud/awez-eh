<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CityGuide\Models\DutyPharmacy;
use App\Domains\Places\Models\Place;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tonight's duty: from 20:00 to 08:00 the next morning, Cairo time, stored in UTC.
 *
 * @extends Factory<DutyPharmacy>
 */
final class DutyPharmacyFactory extends Factory
{
    #[\Override]
    protected $model = DutyPharmacy::class;

    public function definition(): array
    {
        $night = CarbonImmutable::now('Africa/Cairo')->startOfDay();

        return [
            'place_id' => Place::factory()->published(),
            'duty_date' => $night->toDateString(),
            'starts_at' => $night->setTime(20, 0)->utc(),
            'ends_at' => $night->addDay()->setTime(8, 0)->utc(),
        ];
    }
}
