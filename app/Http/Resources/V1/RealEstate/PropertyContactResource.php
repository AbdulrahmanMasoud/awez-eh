<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\RealEstate;

use App\Domains\RealEstate\Data\PropertyContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How to reach a property's owner, for a property the platform does not broker.
 *
 * @property PropertyContact $resource
 */
final class PropertyContactResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'phone' => $this->resource->phone->e164(),
            'whatsapp_url' => $this->resource->whatsappUrl(),
        ];
    }
}
