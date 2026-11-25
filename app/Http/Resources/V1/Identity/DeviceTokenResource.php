<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Identity;

use App\Domains\Identity\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The push token is the device's own; it is not sent back.
 *
 * @property DeviceToken $resource
 */
final class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->resource->platform->value,
            'app_version' => $this->resource->app_version,
            'last_used_at' => $this->resource->last_used_at->toIso8601ZuluString(),
        ];
    }
}
