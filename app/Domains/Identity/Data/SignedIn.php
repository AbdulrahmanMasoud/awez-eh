<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Models\User;
use Spatie\LaravelData\Data;

/**
 * A signed-in device: its new access token (shown once) and whether the account is new.
 */
final class SignedIn extends Data
{
    public function __construct(
        public readonly User $user,
        #[\SensitiveParameter] public readonly string $token,
        public readonly bool $isNewUser,
    ) {}
}
