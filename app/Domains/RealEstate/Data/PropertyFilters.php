<?php

declare(strict_types=1);

namespace App\Domains\RealEstate\Data;

use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertySort;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Support\Geo\Point;
use Brick\Money\Money;
use Spatie\LaravelData\Data;

/**
 * The public list's filters and order (GET /api/v1/properties), built from the validated query.
 * Null means "any".
 */
final class PropertyFilters extends Data
{
    /**
     * @param  list<PropertyType>  $types
     * @param  list<string>  $districtIds
     */
    public function __construct(
        public readonly ?PropertyPurpose $purpose = null,
        public readonly array $types = [],
        public readonly array $districtIds = [],
        public readonly ?Money $priceMin = null,
        public readonly ?Money $priceMax = null,
        public readonly ?int $areaMin = null,
        public readonly ?int $areaMax = null,
        public readonly ?int $bedroomsMin = null,
        public readonly ?bool $furnished = null,
        public readonly ?Finishing $finishing = null,
        public readonly ?bool $brokered = null,
        public readonly ?Point $near = null,
        public readonly ?int $radiusMeters = null,
        public readonly PropertySort $sort = PropertySort::Newest,
    ) {}
}
