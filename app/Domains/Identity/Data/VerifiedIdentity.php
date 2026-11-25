<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\IdentityProvider;
use Spatie\LaravelData\Data;

/**
 * Who a valid ID token names.
 */
final class VerifiedIdentity extends Data
{
    public function __construct(
        public readonly IdentityProvider $provider,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $avatarUrl = null,
    ) {}
}
