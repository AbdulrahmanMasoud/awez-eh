<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Identity;

use App\Domains\Identity\Data\SignedIn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * The device's new access token, shown this once, and the account: 201 when the account is new.
 *
 * @property SignedIn $resource
 */
final class SignInResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'user' => new UserResource($this->resource->user),
            'needs_phone' => $this->resource->user->needsPhone(),
        ];
    }

    #[\Override]
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode($this->resource->isNewUser ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
