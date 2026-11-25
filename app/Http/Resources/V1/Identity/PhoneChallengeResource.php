<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Identity;

use App\Domains\Identity\Data\PhoneChallenge;
use App\Domains\Identity\Enums\PhoneChallengeMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the app does next: `code` (ask for `length` digits) or `whatsapp` (open `link`, then
 * poll GET me).
 *
 * @property PhoneChallenge $resource
 */
final class PhoneChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $challenge = $this->resource;

        return [
            'method' => $challenge->method->value,
            'expires_at' => $challenge->expiresAt->toIso8601ZuluString(),
            ...match ($challenge->method) {
                PhoneChallengeMethod::Code => ['length' => $challenge->length],
                PhoneChallengeMethod::Whatsapp => ['link' => $challenge->link],
            },
        ];
    }

    /**
     * A confirmation was opened.
     */
    #[\Override]
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_CREATED);
    }
}
