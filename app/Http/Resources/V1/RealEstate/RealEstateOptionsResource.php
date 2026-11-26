<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\RealEstate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The real estate filters' options: enums with both labels, and the districts.
 *
 * @property array<string, list<array<string, string>>> $resource
 */
final class RealEstateOptionsResource extends JsonResource
{
    /**
     * @return array<string, list<array<string, string>>>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
