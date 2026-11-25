<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Contracts\IdentityTokenVerifier;
use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Enums\IdentityProvider;

final readonly class GoogleIdentityTokenVerifier implements IdentityTokenVerifier
{
    /**
     * @param  list<string>  $clientIds  The Android, iOS and web client ids.
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

        return new VerifiedIdentity(
            provider: IdentityProvider::Google,
            subject: $claims->subject,
            email: $claims->string('email'),
            emailVerified: $claims->boolean('email_verified'),
            name: $claims->string('name'),
            avatarUrl: $claims->string('picture'),
        );
    }
}
