<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\RealEstate;

use App\Domains\RealEstate\Data\PropertyFilters;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertySort;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Http\Validation\PropertyRules;
use App\Support\Geo\Point;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListPropertiesRequest extends FormRequest
{
    /** Without one, "near" means within 5 km. */
    public const int DEFAULT_RADIUS_METERS = 5_000;

    public const int MAX_RADIUS_METERS = 20_000;

    private const int MIN_RADIUS_METERS = 100;

    /** Query strings carry text: true and false are accepted as words as well as 1 and 0. */
    private const array BOOLEANS = ['true', 'false', '1', '0'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['nullable', Rule::enum(PropertyPurpose::class)],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::enum(PropertyType::class)],
            'district_id' => ['nullable', 'array'],
            'district_id.*' => ['uuid'],
            'price_min' => ['nullable', ...PropertyRules::pounds()],
            'price_max' => ['nullable', ...PropertyRules::pounds(), 'gte:price_min'],
            'area_min' => ['nullable', 'integer', 'min:1'],
            'area_max' => ['nullable', 'integer', 'min:1', 'gte:area_min'],
            'bedrooms_min' => ['nullable', 'integer', 'min:0'],
            'furnished' => ['nullable', Rule::in(self::BOOLEANS)],
            'finishing' => ['nullable', Rule::enum(Finishing::class)],
            'brokered' => ['nullable', Rule::in(self::BOOLEANS)],
            'lat' => ['nullable', 'required_with:lng', 'required_if:sort,nearest', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'required_if:sort,nearest', 'numeric', 'between:-180,180'],
            'radius_m' => ['nullable', 'integer', 'min:'.self::MIN_RADIUS_METERS, 'max:'.self::MAX_RADIUS_METERS],
            'sort' => ['nullable', Rule::enum(PropertySort::class)],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return PropertyRules::attributeNames();
    }

    public function toData(): PropertyFilters
    {
        $near = $this->filled('lat') ? new Point($this->float('lat'), $this->float('lng')) : null;

        return new PropertyFilters(
            purpose: $this->enum('purpose', PropertyPurpose::class),
            types: array_map(PropertyType::from(...), PropertyRules::strings($this->array('type'))),
            districtIds: PropertyRules::strings($this->array('district_id')),
            priceMin: $this->filled('price_min') ? PropertyRules::money($this->input('price_min')) : null,
            priceMax: $this->filled('price_max') ? PropertyRules::money($this->input('price_max')) : null,
            areaMin: $this->filled('area_min') ? $this->integer('area_min') : null,
            areaMax: $this->filled('area_max') ? $this->integer('area_max') : null,
            bedroomsMin: $this->filled('bedrooms_min') ? $this->integer('bedrooms_min') : null,
            furnished: $this->filled('furnished') ? $this->boolean('furnished') : null,
            finishing: $this->enum('finishing', Finishing::class),
            brokered: $this->filled('brokered') ? $this->boolean('brokered') : null,
            near: $near,
            radiusMeters: $near instanceof Point ? ($this->filled('radius_m') ? $this->integer('radius_m') : self::DEFAULT_RADIUS_METERS) : null,
            sort: $this->enum('sort', PropertySort::class) ?? PropertySort::Newest,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'purpose' => ['description' => '`sale` or `rent`.', 'example' => 'rent'],
            'type' => ['description' => 'One or more types: `type[]=apartment&type[]=duplex`.', 'example' => ['apartment']],
            'district_id' => ['description' => 'One or more districts: `district_id[]=…`.', 'example' => []],
            'price_min' => ['description' => 'In pounds.', 'example' => '3000'],
            'price_max' => ['description' => 'In pounds.', 'example' => '9000'],
            'area_min' => ['description' => 'Square metres.', 'example' => 90],
            'area_max' => ['description' => 'Square metres.', 'example' => 200],
            'bedrooms_min' => ['description' => 'At least this many bedrooms.', 'example' => 2],
            'furnished' => ['description' => '`true` or `false`.', 'example' => 'false'],
            'finishing' => ['description' => 'GET /real-estate/options.', 'example' => 'finished'],
            'brokered' => ['description' => '`true` for the platform\'s own listings.', 'example' => null],
            'lat' => ['description' => 'With `lng`: only properties within `radius_m` of this point.', 'example' => 30.3705],
            'lng' => ['description' => 'With `lat`.', 'example' => 30.5085],
            'radius_m' => ['description' => 'Metres, 100 to 20,000; 5,000 by default.', 'example' => 3000],
            'sort' => ['description' => '`newest` (default), `price_asc`, `price_desc`, or `nearest` (needs `lat` and `lng`).', 'example' => 'newest'],
            'cursor' => ['description' => 'The `next_cursor` of the previous page.', 'example' => null],
        ];
    }
}
