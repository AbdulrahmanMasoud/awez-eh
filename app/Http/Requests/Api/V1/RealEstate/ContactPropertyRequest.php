<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\RealEstate;

use App\Domains\RealEstate\Models\Property;
use App\Http\SignedInRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class ContactPropertyRequest extends FormRequest
{
    use SignedInRequest;

    public function authorize(): bool
    {
        // Only a published property's owner is reachable; anything else answers 404.
        Gate::authorize('contact', $this->property());

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function property(): Property
    {
        $property = $this->route('property');

        if (! $property instanceof Property) {
            throw new LogicException('The property route was not bound.');
        }

        return $property;
    }

    /**
     * No body.
     *
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [];
    }
}
