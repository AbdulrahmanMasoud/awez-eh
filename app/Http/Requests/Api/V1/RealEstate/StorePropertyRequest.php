<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\RealEstate;

use App\Domains\RealEstate\Data\NewProperty;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Http\SignedInRequest;
use App\Http\Validation\PropertyRules;
use App\Support\Geo\Point;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePropertyRequest extends FormRequest
{
    use SignedInRequest;

    /** Every signed-in resident may list; the Action checks the number and the limits. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $fields = PropertyRules::fields();
        $required = ['type', 'title', 'description', 'price', 'area_m2', 'district_id', 'lat', 'lng'];

        foreach ($required as $name) {
            $fields[$name] = ['required', ...$fields[$name]];
        }

        return [
            'purpose' => ['required', Rule::enum(PropertyPurpose::class)],
            'rent_period' => ['required_if:purpose,rent', 'prohibited_unless:purpose,rent', 'nullable', Rule::enum(RentPeriod::class)],
            ...$fields,
            'submit' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return PropertyRules::attributeNames();
    }

    public function toData(): NewProperty
    {
        /** @var array<string, mixed> $valid */
        $valid = $this->validated();

        return new NewProperty(
            purpose: PropertyPurpose::from($this->string('purpose')->toString()),
            type: PropertyType::from($this->string('type')->toString()),
            title: trim($this->string('title')->toString()),
            description: trim($this->string('description')->toString()),
            price: PropertyRules::money($valid['price']),
            rentPeriod: $this->enum('rent_period', RentPeriod::class),
            areaM2: $this->integer('area_m2'),
            bedrooms: $this->filled('bedrooms') ? $this->integer('bedrooms') : null,
            bathrooms: $this->filled('bathrooms') ? $this->integer('bathrooms') : null,
            floor: $this->filled('floor') ? $this->integer('floor') : null,
            furnished: $this->boolean('furnished'),
            finishing: $this->enum('finishing', Finishing::class),
            districtId: $this->string('district_id')->toString(),
            addressNote: $this->filled('address_note') ? trim($this->string('address_note')->toString()) : null,
            location: new Point($this->float('lat'), $this->float('lng')),
            photos: PropertyRules::strings($this->array('photos')),
            submit: $this->boolean('submit'),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'purpose' => ['description' => '`sale` or `rent`.', 'example' => 'rent'],
            'type' => ['description' => 'The kind of property (GET /real-estate/options).', 'example' => 'apartment'],
            'title' => ['description' => 'A short title, 5 to 120 characters.', 'example' => 'شقة للإيجار 3 غرف في الحي الثاني'],
            'description' => ['description' => 'The details, 10 to 5,000 characters.', 'example' => 'الشقة في الدور التالت بأسانسير، قريبة من المدارس والمواصلات.'],
            'price' => ['description' => 'In Egyptian pounds, up to two decimals. For a rent, the price of one period.', 'example' => '6500'],
            'rent_period' => ['description' => '`monthly` or `yearly`: required for a rent, refused for a sale.', 'example' => 'monthly'],
            'area_m2' => ['description' => 'Square metres.', 'example' => 140],
            'bedrooms' => ['description' => 'Optional.', 'example' => 3],
            'bathrooms' => ['description' => 'Optional.', 'example' => 2],
            'floor' => ['description' => 'Optional; 0 is the ground floor.', 'example' => 3],
            'furnished' => ['description' => 'Defaults to false.', 'example' => false],
            'finishing' => ['description' => 'Optional (GET /real-estate/options).', 'example' => 'finished'],
            'district_id' => ['description' => 'One of the districts (GET /real-estate/options).', 'example' => '019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01'],
            'address_note' => ['description' => 'Optional landmark for visitors.', 'example' => 'بجوار مسجد الرحمن'],
            'lat' => ['description' => 'The pin on the map. Residents see it rounded to about 100 m.', 'example' => 30.3705],
            'lng' => ['description' => 'The pin on the map.', 'example' => 30.5085],
            'photos' => ['description' => 'Ids of the resident\'s uploads (POST /uploads), in order. Up to the photo limit.', 'example' => ['019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01']],
            'submit' => ['description' => 'true sends it to review now; otherwise it is a draft.', 'example' => true],
        ];
    }
}
