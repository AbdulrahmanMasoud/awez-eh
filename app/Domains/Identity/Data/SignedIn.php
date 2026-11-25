<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Models\User;

/**
 * A signed-in device: its new access token (shown once) and whether the account is new.
 */
final readonly class SignedIn
{
    public function __construct(
        public User $user,
        #[\SensitiveParameter] public string $token,
        public bool $isNewUser,
    ) {}
}
