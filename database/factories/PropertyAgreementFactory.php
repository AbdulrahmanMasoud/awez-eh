<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Database\Factories\Support\ArabicNames;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyAgreement>
 */
final class PropertyAgreementFactory extends Factory
{
    #[\Override]
    protected $model = PropertyAgreement::class;

    public function definition(): array
    {
        $signedOn = CarbonImmutable::today()->subDays(fake()->numberBetween(1, 200));

        return [
            'property_id' => Property::factory()->brokered(),
            'owner_name' => ArabicNames::person($this->faker),
            'owner_phone' => PhoneNumber::from(SadatCity::mobileNumber($this->faker)),
            // A demo value only: the real rate is still open (OPEN_QUESTIONS 1).
            'commission_rate_basis_points' => fake()->randomElement([100, 150, 200, 250]),
            'commission_fixed' => null,
            'signed_on' => $signedOn,
            'ends_on' => $signedOn->addYear(),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
