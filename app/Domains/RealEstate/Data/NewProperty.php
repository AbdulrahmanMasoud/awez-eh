<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Data;

use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Spatie\LaravelData\Data;

/**
 * A property a resident lists through POST /api/v1/properties, built from the validated request.
 * `brokered` is never here: only an admin sets it, by recording an agreement.
 */
final class NewProperty extends Data
{
    /**
     * @param  list<string>  $photos  Pending upload ids, in the order the resident chose.
     */
    public function __construct(
        public readonly PropertyPurpose $purpose,
        public readonly PropertyType $type,
        public readonly string $title,
        public readonly string $description,
        public readonly Money $price,
        public readonly ?RentPeriod $rentPeriod,
        public readonly int $areaM2,
        public readonly ?int $bedrooms,
        public readonly ?int $bathrooms,
        public readonly ?int $floor,
        public readonly bool $furnished,
        public readonly ?Finishing $finishing,
        public readonly string $districtId,
        public readonly ?string $addressNote,
        public readonly Point $location,
        public readonly array $photos,
        public readonly bool $submit,
    ) {}

    /**
     * The property's own columns (not its owner, city, status or photos).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'purpose' => $this->purpose,
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
        ];
    }
}
