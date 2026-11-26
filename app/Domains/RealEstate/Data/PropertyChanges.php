<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Data;

use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * What PATCH /api/v1/properties/{id} changes: only the fields sent. The purpose is not here: a
 * sale does not become a rent (list it again instead). `photos`, when sent, is the whole ordered
 * list: media ids it keeps and upload ids it adds.
 */
final class PropertyChanges extends Data
{
    /**
     * @param  list<string>|Optional  $photos
     */
    public function __construct(
        public readonly PropertyType|Optional $type,
        public readonly string|Optional $title,
        public readonly string|Optional $description,
        public readonly Money|Optional $price,
        public readonly RentPeriod|Optional|null $rentPeriod,
        public readonly int|Optional $areaM2,
        public readonly int|Optional|null $bedrooms,
        public readonly int|Optional|null $bathrooms,
        public readonly int|Optional|null $floor,
        public readonly bool|Optional $furnished,
        public readonly Finishing|Optional|null $finishing,
        public readonly string|Optional $districtId,
        public readonly string|Optional|null $addressNote,
        public readonly Point|Optional $location,
        public readonly array|Optional $photos,
    ) {}

    /**
     * The columns to change.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'rent_period' => $this->rentPeriod,
            'area_m2' => $this->areaM2,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'floor' => $this->floor,
            'furnished' => $this->furnished,
            'finishing' => $this->finishing,
            'district_id' => $this->districtId,
            'address_note' => $this->addressNote,
            'location' => $this->location,
        ], fn (mixed $value): bool => ! $value instanceof Optional);
    }
}
