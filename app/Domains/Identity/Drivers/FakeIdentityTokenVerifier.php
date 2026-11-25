<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Contracts\IdentityTokenVerifier;
use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Enums\IdentityProvider;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;
use Illuminate\Support\Str;

/**
 * For real runs before the Google and Apple clients exist (BUILD_PLAN phase 0): `fake:<email>`
 * signs in as that verified email. Only with AWEZ_SIGN_IN_FAKE=true, which the app refuses to
 * boot with outside local and testing (IdentityServiceProvider).
 */
final readonly class FakeIdentityTokenVerifier implements IdentityTokenVerifier
{
    private const string PREFIX = 'fake:';

    public function __construct(private IdentityProvider $provider) {}

    public function verify(#[\SensitiveParameter] string $idToken, ?string $nonce): VerifiedIdentity
    {
        $email = Str::lower(Str::after($idToken, self::PREFIX));

        if (! Str::startsWith($idToken, self::PREFIX) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidIdentityToken('not a fake:<email> token');
        }

        return new VerifiedIdentity(
            provider: $this->provider,
            subject: self::PREFIX.$email,
            email: $email,
            emailVerified: true,
            name: Str::before($email, '@'),
        );
    }
}
