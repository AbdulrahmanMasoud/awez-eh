<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\IdentityProvider;

/**
 * Who a valid ID token names.
 */
final readonly class VerifiedIdentity
{
    public function __construct(
        public IdentityProvider $provider,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $avatarUrl = null,
    ) {}
}
