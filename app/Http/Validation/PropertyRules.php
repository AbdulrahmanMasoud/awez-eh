<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyType;
use Brick\Money\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/**
 * The rules a property's fields share between create and edit, and the conversions both need.
 * Prices arrive in pounds, up to two decimals, and become Money without a float (invariant 3).
 */
final class PropertyRules
{
    public const string CURRENCY = 'EGP';

    private const int MAX_TITLE = 120;

    private const int MAX_DESCRIPTION = 5000;

    private const int MAX_NOTE = 255;

    /** A bigint of piasters holds far more; this is a typo guard: a trillion pounds. */
    private const int MAX_PRICE_POUNDS = 999_999_999_999;

    private const int MAX_AREA = 1_000_000;

    private const int MAX_ROOMS = 50;

    private const int LOWEST_FLOOR = -3;

    private const int HIGHEST_FLOOR = 200;

    /**
     * @return array<string, list<string|Enum|Exists>>
     */
    public static function fields(): array
    {
        return [
            'type' => [Rule::enum(PropertyType::class)],
            'title' => ['string', 'min:5', 'max:'.self::MAX_TITLE],
            'description' => ['string', 'min:10', 'max:'.self::MAX_DESCRIPTION],
            'price' => self::pounds(),
            'area_m2' => ['integer', 'min:1', 'max:'.self::MAX_AREA],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_ROOMS],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_ROOMS],
            'floor' => ['nullable', 'integer', 'min:'.self::LOWEST_FLOOR, 'max:'.self::HIGHEST_FLOOR],
            'furnished' => ['boolean'],
            'finishing' => ['nullable', Rule::enum(Finishing::class)],
            'district_id' => ['uuid', Rule::exists('districts', 'id')],
            'address_note' => ['nullable', 'string', 'max:'.self::MAX_NOTE],
            'lat' => ['numeric', 'between:-90,90'],
            'lng' => ['numeric', 'between:-180,180'],
            'photos' => ['array'],
            'photos.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function pounds(): array
    {
        return ['decimal:0,2', 'min:1', 'max:'.self::MAX_PRICE_POUNDS];
    }

    public static function money(mixed $pounds): Money
    {
        return Money::of(is_numeric($pounds) ? (string) $pounds : '0', self::CURRENCY);
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        $names = __('real_estate.attributes');
        $attributes = [];

        foreach (is_array($names) ? $names : [] as $key => $name) {
            if (is_string($key) && is_string($name)) {
                $attributes[$key] = $name;
            }
        }

        return $attributes;
    }

    /**
     * The strings of a validated list (ids, enum values), in order.
     *
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    public static function strings(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
