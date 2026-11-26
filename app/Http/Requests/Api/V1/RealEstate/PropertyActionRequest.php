<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\RealEstate;

use App\Domains\RealEstate\Models\Property;
use App\Http\SignedInRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * The owner's moves on their property (submit, pause, resume, sold, rented, archive): no body,
 * only the owner. Whether the move is allowed now is the Action's (ContentStatusMachine).
 */
final class PropertyActionRequest extends FormRequest
{
    use SignedInRequest;

    public function authorize(): bool
    {
        Gate::authorize('update', $this->property());

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
