<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Identity;

use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account, for its own owner only: it shows the full phone number, which is
 * theirs. Nothing else the API returns ever shows another user's number (invariants 2 and 8).
 *
 * @property User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone?->e164(),
            'phone_verified_at' => $user->phone_verified_at?->toIso8601ZuluString(),
            'needs_phone' => $user->needsPhone(),
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale->value,
            'district_id' => $user->district_id,
            'is_advertiser' => $user->isAdvertiser(),
            'is_provider' => $user->isProvider(),
            'created_at' => $user->created_at?->toIso8601ZuluString(),
        ];
    }
}
