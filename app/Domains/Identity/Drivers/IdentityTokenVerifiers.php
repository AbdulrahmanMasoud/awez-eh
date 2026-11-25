<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Contracts\IdentityTokenVerifier;
use App\Domains\Identity\Enums\IdentityProvider;
use Illuminate\Contracts\Container\Container;

/**
 * The verifier for a provider: the real one, or the fake one when AWEZ_SIGN_IN_FAKE is on.
 */
final readonly class IdentityTokenVerifiers
{
    public function __construct(
        private Container $container,
        private bool $fake,
    ) {}

    public function for(IdentityProvider $provider): IdentityTokenVerifier
    {
        if ($this->fake) {
            return new FakeIdentityTokenVerifier($provider);
        }

        return match ($provider) {
            IdentityProvider::Google => $this->container->make(GoogleIdentityTokenVerifier::class),
            IdentityProvider::Apple => $this->container->make(AppleIdentityTokenVerifier::class),
        };
    }
}
