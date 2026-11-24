<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Geography\Models\City;
use App\Domains\Identity\Models\User;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Requests\Enums\RequestArea;
use App\Domains\Requests\Enums\RequestStatus;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Trade;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<ServiceRequest>
 */
final class ServiceRequestFactory extends Factory
{
    #[\Override]
    protected $model = ServiceRequest::class;

    /** @var array<string, non-empty-list<string>> */
    private const array BODIES = [
        'real_estate' => [
            'عايز شقة إيجار ٢ غرف قريبة من الجامعة، الميزانية لحد ٤ آلاف.',
            'مطلوب محل صغير للإيجار على شارع رئيسي.',
            'عايز أشتري أرض ٣٠٠ متر في السكنية التامنة.',
        ],
        'services' => [
            'محتاج سباك ضروري النهاردة، فيه تسريب في الحمام.',
            'عايز كهربائي يركب نجف وينقل فيشة.',
            'محتاج نقاش يدهن شقة ٣ غرف.',
            'مطلوب فني تكييف يعمل صيانة لتكييفين.',
        ],
        'places' => [
            'فيه صيدلية فاتحة دلوقتي قريبة من السابعة؟',
            'عايز مطعم بيوصل مشويات للمنطقة الصناعية.',
            'مين عنده كيك عيد ميلاد بالطلب؟',
        ],
        'marketplace' => [
            'عايز أشتري غسالة مستعملة حالتها كويسة.',
            'محتاج لابتوب مستعمل للمذاكرة، الميزانية ٨ آلاف.',
        ],
        'other' => [
            'حد يعرف مواعيد أتوبيس السادات للقاهرة؟',
            'محتاج حد يوصلني المطار الأسبوع الجاي.',
        ],
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'area' => fake()->randomElement(RequestArea::cases()),
            'trade_id' => fn (array $attributes): ?TradeFactory => $this->areaOf($attributes) === RequestArea::Services ? Trade::factory() : null,
            'place_category_id' => fn (array $attributes): ?PlaceCategoryFactory => $this->areaOf($attributes) === RequestArea::Places ? PlaceCategory::factory() : null,
            'body' => fn (array $attributes): string => Random::element(fake(), self::BODIES[$this->areaOf($attributes)->value]),
            'budget' => fake()->boolean(40) ? Money::of(fake()->numberBetween(2, 80) * 100, 'EGP') : null,
            'city_id' => City::factory(),
            'district_id' => null,
            'location' => fake()->boolean(60) ? SadatCity::randomPoint($this->faker) : null,
            'status' => RequestStatus::Open,
            'expires_at' => CarbonImmutable::now()->addDays(fake()->numberBetween(2, 14))->startOfMinute(),
            'replies_count' => 0,
            'views_count' => fake()->numberBetween(0, 120),
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => RequestStatus::Closed]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => RequestStatus::Expired, 'expires_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(1, 10))]);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function areaOf(array $attributes): RequestArea
    {
        $area = $attributes['area'] ?? null;

        return match (true) {
            $area instanceof RequestArea => $area,
            is_string($area) => RequestArea::from($area),
            default => throw new LogicException('A request needs an area.'),
        };
    }
}
