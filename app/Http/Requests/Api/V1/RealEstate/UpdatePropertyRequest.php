<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\RealEstate;

use App\Domains\RealEstate\Data\PropertyChanges;
use App\Domains\RealEstate\Enums\Finishing;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Enums\PropertyType;
use App\Domains\RealEstate\Enums\RentPeriod;
use App\Domains\RealEstate\Models\Property;
use App\Http\SignedInRequest;
use App\Http\Validation\PropertyRules;
use App\Support\Geo\Point;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;
use Spatie\LaravelData\Optional;

final class UpdatePropertyRequest extends FormRequest
{
    use SignedInRequest;

    public function authorize(): bool
    {
        // Someone else's draft answers 404, a public one 403 (PropertyPolicy).
        Gate::authorize('update', $this->property());

        return true;
    }

    /**
     * Only the fields sent are checked and changed. A rent keeps a period; a sale has none.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $fields = array_map(fn (array $rules): array => ['sometimes', ...$rules], PropertyRules::fields());
        $rent = $this->property()->purpose === PropertyPurpose::Rent;

        return [
            ...$fields,
            'type' => ['sometimes', 'required', Rule::enum(PropertyType::class)],
            'district_id' => ['sometimes', 'required', ...PropertyRules::fields()['district_id']],
            'rent_period' => $rent ? ['sometimes', 'required', Rule::enum(RentPeriod::class)] : ['prohibited'],
            'lat' => ['required_with:lng', ...$fields['lat']],
            'lng' => ['required_with:lat', ...$fields['lng']],
        ];
    }

    public function attributes(): array
    {
        return PropertyRules::attributeNames();
    }

    public function toData(): PropertyChanges
    {
        /** @var array<string, mixed> $valid */
        $valid = $this->validated();
        $sent = fn (string $key): bool => array_key_exists($key, $valid);
        $optional = Optional::create();

        return new PropertyChanges(
            type: $sent('type') ? PropertyType::from($this->string('type')->toString()) : $optional,
            title: $sent('title') ? trim($this->string('title')->toString()) : $optional,
            description: $sent('description') ? trim($this->string('description')->toString()) : $optional,
            price: $sent('price') ? PropertyRules::money($valid['price']) : $optional,
            rentPeriod: $sent('rent_period') ? $this->enum('rent_period', RentPeriod::class) : $optional,
            areaM2: $sent('area_m2') ? $this->integer('area_m2') : $optional,
            bedrooms: $sent('bedrooms') ? ($this->filled('bedrooms') ? $this->integer('bedrooms') : null) : $optional,
            bathrooms: $sent('bathrooms') ? ($this->filled('bathrooms') ? $this->integer('bathrooms') : null) : $optional,
            floor: $sent('floor') ? ($this->filled('floor') ? $this->integer('floor') : null) : $optional,
            furnished: $sent('furnished') ? $this->boolean('furnished') : $optional,
            finishing: $sent('finishing') ? $this->enum('finishing', Finishing::class) : $optional,
            districtId: $sent('district_id') ? $this->string('district_id')->toString() : $optional,
            addressNote: $sent('address_note') ? ($this->filled('address_note') ? trim($this->string('address_note')->toString()) : null) : $optional,
            location: $sent('lat') ? new Point($this->float('lat'), $this->float('lng')) : $optional,
            photos: $sent('photos') ? PropertyRules::strings($this->array('photos')) : $optional,
        );
    }

    public function property(): Property
    {
        $property = $this->route('property');

        // The route binds {property} before this request is resolved.
        if (! $property instanceof Property) {
            throw new LogicException('The property route was not bound.');
        }

        return $property;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Any field of POST /properties but `purpose` and `submit`; only those sent change.', 'example' => 'شقة للإيجار 3 غرف بجوار الجامعة'],
            'price' => ['description' => 'In pounds. A change of price alone keeps a published property public.', 'example' => '7000'],
            'photos' => ['description' => 'The whole ordered list: ids of photos it keeps and of uploads to add. A photo left out is removed. A new photo sends a published property back to review.', 'example' => ['019ac0d2-4b20-71aa-9d3c-0e1f2a3b4c5d', '019ac0d2-4b1e-7c3a-8f21-5d6e7a8b9c01']],
        ];
    }
}
