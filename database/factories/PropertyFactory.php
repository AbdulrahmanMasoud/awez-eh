<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Models\Property;
use Brick\Money\Money;
use Database\Factories\Concerns\HasModerationStates;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Property>
 */
final class PropertyFactory extends Factory
{
    use HasModerationStates;

    #[\Override]
    protected $model = Property::class;

    private const array HOMES = [PropertyType::Apartment, PropertyType::Villa, PropertyType::Duplex, PropertyType::House];

    private const array TYPE_NAMES = [
        'apartment' => 'شقة', 'villa' => 'فيلا', 'duplex' => 'دوبلكس', 'house' => 'بيت', 'land' => 'أرض',
        'shop' => 'محل', 'office' => 'مكتب إداري', 'warehouse' => 'مخزن', 'factory' => 'مصنع',
    ];

    public function definition(): array
    {
        $purpose = Random::element(fake(), PropertyPurpose::cases());
        // Nobody rents out land or a factory floor in the demo.
        $type = Random::element(fake(), $purpose === PropertyPurpose::Rent
            ? [PropertyType::Apartment, PropertyType::Apartment, PropertyType::Duplex, PropertyType::Shop, PropertyType::Office, PropertyType::Warehouse]
            : PropertyType::cases());
        $home = in_array($type, self::HOMES, true);

        return [
            'user_id' => User::factory(),
            'purpose' => $purpose,
            'type' => $type,
            'area_m2' => $this->area($type),
            'bedrooms' => $home ? fake()->numberBetween(1, 5) : null,
            'bathrooms' => $home ? fake()->numberBetween(1, 3) : null,
            'floor' => in_array($type, [PropertyType::Apartment, PropertyType::Duplex, PropertyType::Office], true) ? fake()->numberBetween(0, 6) : null,
            'furnished' => $home && fake()->boolean(20),
            'finishing' => $type === PropertyType::Land ? null : fake()->randomElement(Finishing::cases()),
            'title' => $this->title(...),
            'description' => $this->description(...),
            'price' => $this->price(...),
            'rent_period' => fn (array $attributes): ?RentPeriod => $this->purposeOf($attributes) === PropertyPurpose::Rent ? RentPeriod::Monthly : null,
            'city_id' => City::factory(),
            'district_id' => fn (array $attributes): DistrictFactory => District::factory()->state(['city_id' => $attributes['city_id']]),
            'address_note' => fake()->optional(0.6)->randomElement([
                'بجوار مسجد الرحمن', 'خلف السوق التجاري', 'على الشارع الرئيسي', 'أمام مدرسة السادات الرسمية',
                'قريب من موقف الأتوبيس', 'بجوار المستشفى', 'ناصية شارع الجامعة',
            ]),
            'location' => SadatCity::randomPoint($this->faker),
            'brokered' => false,
            'views_count' => fake()->numberBetween(0, 400),
        ];
    }

    /** A listing the platform has a written agreement for (invariant 2). */
    public function brokered(): static
    {
        return $this->state(['brokered' => true]);
    }

    public function forRent(): static
    {
        return $this->state(['purpose' => PropertyPurpose::Rent, 'type' => PropertyType::Apartment]);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function purposeOf(array $attributes): PropertyPurpose
    {
        $purpose = $attributes['purpose'] ?? null;

        return match (true) {
            $purpose instanceof PropertyPurpose => $purpose,
            is_string($purpose) => PropertyPurpose::from($purpose),
            default => throw new LogicException('A property needs a purpose.'),
        };
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function typeOf(array $attributes): PropertyType
    {
        $type = $attributes['type'] ?? null;

        return match (true) {
            $type instanceof PropertyType => $type,
            is_string($type) => PropertyType::from($type),
            default => throw new LogicException('A property needs a type.'),
        };
    }

    private function area(PropertyType $type): int
    {
        return match ($type) {
            PropertyType::Apartment => fake()->numberBetween(9, 20) * 10,
            PropertyType::Duplex, PropertyType::House => fake()->numberBetween(18, 35) * 10,
            PropertyType::Villa => fake()->numberBetween(30, 60) * 10,
            PropertyType::Land, PropertyType::Factory => fake()->numberBetween(4, 40) * 100,
            PropertyType::Shop, PropertyType::Office => fake()->numberBetween(3, 15) * 10,
            PropertyType::Warehouse => fake()->numberBetween(10, 50) * 10,
        };
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function title(array $attributes): string
    {
        $type = $this->typeOf($attributes);
        $purpose = $this->purposeOf($attributes) === PropertyPurpose::Rent ? 'للإيجار' : 'للبيع';
        $name = self::TYPE_NAMES[$type->value];

        if (in_array($type, self::HOMES, true) && is_int($attributes['bedrooms'] ?? null)) {
            return "{$name} {$purpose} {$attributes['bedrooms']} غرف ".Random::element(fake(), [
                'بالقرب من الخدمات', 'على شارع رئيسي', 'تشطيب سوبر لوكس', 'واجهة بحري', 'في عمارة حديثة',
            ]);
        }

        $area = is_int($attributes['area_m2'] ?? null) ? " مساحة {$attributes['area_m2']} متر" : '';

        return "{$name} {$purpose}{$area} ".Random::element(fake(), ['موقع مميز', 'على شارع رئيسي', 'بسعر مناسب', 'بالمنطقة الصناعية']);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function description(array $attributes): string
    {
        $sentences = [
            'العقار في منطقة هادئة وقريب من المدارس والمواصلات.',
            'عدادات الكهرباء والمياه والغاز مركبة.',
            'العمارة فيها أسانسير وجراج.',
            'المعاينة بميعاد مسبق، والتواصل من خلال التطبيق.',
            'السعر قابل للتفاوض في حدود المعقول.',
            'الأوراق سليمة والعقد مسجل.',
        ];

        if ($this->purposeOf($attributes) === PropertyPurpose::Rent) {
            $sentences[] = 'مطلوب مقدم شهرين وتأمين شهر.';
            $sentences[] = 'يفضل للعائلات أو الطلبة.';
        }

        return implode(' ', Random::elements(fake(), $sentences, fake()->numberBetween(2, 4)));
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function price(array $attributes): Money
    {
        $area = is_int($attributes['area_m2'] ?? null) ? $attributes['area_m2'] : 100;

        if ($this->purposeOf($attributes) === PropertyPurpose::Rent) {
            return Money::of(fake()->numberBetween(25, 150) * 100, 'EGP');
        }

        // Pounds per square metre, rounded to a thousand pounds as listings write them.
        $perMetre = $this->typeOf($attributes) === PropertyType::Land ? fake()->numberBetween(1500, 4000) : fake()->numberBetween(8000, 18000);

        return Money::of(intdiv($area * $perMetre, 1000) * 1000, 'EGP');
    }
}
