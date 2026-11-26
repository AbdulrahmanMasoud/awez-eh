<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Actions;

use App\Domains\Geography\Models\District;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use BackedEnum;
use Illuminate\Support\Facades\Cache;

/**
 * What the app's real estate filters offer: each enum with its Arabic and English labels, and the
 * districts. Cached until an admin changes a district (RealEstateServiceProvider forgets it).
 */
final readonly class ShowRealEstateOptions
{
    public const string CACHE_KEY = 'real-estate:options';

    /**
     * @return array<string, list<array<string, string>>>
     */
    public function handle(): array
    {
        /** @var array<string, list<array<string, string>>> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => [
            'purposes' => $this->labelled('purposes', PropertyPurpose::cases()),
            'types' => $this->labelled('types', PropertyType::cases()),
            'finishings' => $this->labelled('finishings', Finishing::cases()),
            'rent_periods' => $this->labelled('rent_periods', RentPeriod::cases()),
            'districts' => District::query()
                ->orderBy('city_id')
                ->orderBy('sort_order')
                ->get(['id', 'city_id', 'name_ar', 'name_en'])
                ->map(fn (District $district): array => [
                    'id' => $district->id,
                    'city_id' => $district->city_id,
                    'name_ar' => $district->name_ar,
                    'name_en' => $district->name_en,
                ])
                ->values()
                ->all(),
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<array<string, string>>
     */
    private function labelled(string $group, array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => [
            'value' => (string) $case->value,
            'label_ar' => (string) __("real_estate.{$group}.{$case->value}", [], 'ar'),
            'label_en' => (string) __("real_estate.{$group}.{$case->value}", [], 'en'),
        ], $cases);
    }
}
