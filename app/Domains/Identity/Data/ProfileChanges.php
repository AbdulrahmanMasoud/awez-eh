<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\UserLocale;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

/**
 * What PATCH me changes: only the fields sent. A field left out is Optional; a district sent as
 * null clears it. Built from the validated request (`district_id` maps to `districtId`).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class ProfileChanges extends Data
{
    public function __construct(
        public readonly string|Optional $name,
        public readonly UserLocale|Optional $locale,
        public readonly string|Optional|null $districtId,
    ) {}

    /**
     * The user's columns to change.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return array_filter(
            ['name' => $this->name, 'locale' => $this->locale, 'district_id' => $this->districtId],
            fn (mixed $value): bool => ! $value instanceof Optional,
        );
    }
}
