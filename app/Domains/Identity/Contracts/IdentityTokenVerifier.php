<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;

/**
 * Checks an ID token the mobile app got from Google or Apple natively, and says who it names.
 */
interface IdentityTokenVerifier
{
    /**
     * @param  string|null  $nonce  The raw nonce the app sent the provider (Apple requires one).
     *
     * @throws InvalidIdentityToken
     */
    public function verify(#[\SensitiveParameter] string $idToken, ?string $nonce): VerifiedIdentity;
}
