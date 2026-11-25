<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use Laravel\Sanctum\PersonalAccessToken;

final readonly class SignOut
{
    /**
     * The device's push token goes with its access token (device_tokens cascades).
     */
    public function handle(PersonalAccessToken $accessToken): void
    {
        $accessToken->delete();
    }
}
