<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Services\Enums\ProviderVerification;
use App\Domains\Services\Models\Provider;
use Carbon\CarbonImmutable;
use Database\Factories\Concerns\HasModerationStates;
use Database\Factories\Support\ArabicNames;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
final class ProviderFactory extends Factory
{
    use HasModerationStates;

    #[\Override]
    protected $model = Provider::class;

    public function definition(): array
    {
        $years = fake()->numberBetween(2, 30);

        return [
            'user_id' => User::factory(),
            'display_name' => 'الأسطى '.ArabicNames::craftsman($this->faker),
            'bio' => fake()->optional(0.85)->randomElement([
                "خبرة {$years} سنة في مدينة السادات والمدن القريبة. شغل نضيف ومواعيد مظبوطة.",
                "شغال في المهنة من {$years} سنة. المعاينة مجانًا داخل المدينة.",
                'متاح أيام الجمعة والإجازات. الضمان على الشغل شهر.',
                'بشتغل بخامات أصلية، والفاتورة بالتفصيل قبل البدء.',
            ]),
            'city_id' => City::factory(),
            'district_id' => fn (array $attributes): DistrictFactory => District::factory()->state(['city_id' => $attributes['city_id']]),
            'location' => SadatCity::randomPoint($this->faker),
            'service_radius_m' => fake()->numberBetween(3, 15) * 1000,
            'years_experience' => $years,
            'verification' => ProviderVerification::Unverified,
            'verified_at' => null,
            'is_available' => fake()->boolean(40),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification' => ProviderVerification::Verified,
            'verified_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(1, 120)),
        ]);
    }
}
