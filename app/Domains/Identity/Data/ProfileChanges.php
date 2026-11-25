<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\UserLocale;

/**
 * What PATCH me changes: only the fields sent. A district sent as null clears it.
 */
final readonly class ProfileChanges
{
    public function __construct(
        public ?string $name = null,
        public ?UserLocale $locale = null,
        public bool $changesDistrict = false,
        public ?string $districtId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'locale' => $this->locale,
        ], fn (mixed $value): bool => $value !== null) + ($this->changesDistrict ? ['district_id' => $this->districtId] : []);
    }
}
