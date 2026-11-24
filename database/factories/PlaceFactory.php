<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceCategory;
use App\Support\PhoneNumber;
use Database\Factories\Concerns\HasModerationStates;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
final class PlaceFactory extends Factory
{
    use HasModerationStates;

    #[\Override]
    protected $model = Place::class;

    public function definition(): array
    {
        $phone = PhoneNumber::from(SadatCity::mobileNumber($this->faker));

        return [
            'user_id' => User::factory(),
            'place_category_id' => PlaceCategory::factory(),
            'name' => 'محل '.Random::element(fake(), ['النور', 'السلام', 'الأمل', 'الرحمة', 'الهدى', 'الصفا', 'الوفاء', 'الريان']),
            'description' => fake()->optional(0.7)->randomElement([
                'خدمة ممتازة وأسعار مناسبة.',
                'توصيل لكل مناطق المدينة.',
                'مفتوح طول أيام الأسبوع.',
            ]),
            'phone' => $phone,
            'whatsapp' => fake()->boolean(70) ? $phone : null,
            'city_id' => City::factory(),
            'district_id' => fn (array $attributes): DistrictFactory => District::factory()->state(['city_id' => $attributes['city_id']]),
            'address_note' => fake()->optional(0.7)->randomElement(['في السوق التجاري', 'على الشارع الرئيسي', 'بجوار البنك الأهلي']),
            'location' => SadatCity::randomPoint($this->faker),
            'delivers' => fake()->boolean(50),
        ];
    }

    /** A place an admin added, not claimed by anyone yet. */
    public function unclaimed(): static
    {
        return $this->state(['user_id' => null]);
    }
}
