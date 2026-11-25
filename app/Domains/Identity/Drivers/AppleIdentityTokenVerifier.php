<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Contracts\IdentityTokenVerifier;
use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Enums\IdentityProvider;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;

/**
 * Apple's token carries no name (Apple gives it to the app, on the first sign-in only) and
 * must carry the SHA-256 of the nonce the app generated, so a token taken from another sign-in
 * cannot be replayed here.
 */
final readonly class AppleIdentityTokenVerifier implements IdentityTokenVerifier
{
    /**
     * @param  list<string>  $clientIds  The app's bundle id.
     * @param  list<string>  $issuers
     */
    public function __construct(
        private IdTokenDecoder $decoder,
        private string $keysUrl,
        private array $clientIds,
        private array $issuers,
    ) {}

    public function verify(#[\SensitiveParameter] string $idToken, ?string $nonce): VerifiedIdentity
    {
        $claims = $this->decoder->decode($idToken, $this->keysUrl, $this->issuers, $this->clientIds);

        $nonceHash = $claims->string('nonce');

        if ($nonce === null || $nonce === '' || $nonceHash === null || ! hash_equals($nonceHash, hash('sha256', $nonce))) {
            throw new InvalidIdentityToken('nonce missing or wrong');
        }

        return new VerifiedIdentity(
            provider: IdentityProvider::Apple,
            subject: $claims->subject,
            email: $claims->string('email'),
            emailVerified: $claims->boolean('email_verified'),
            name: null,
        );
    }
}
