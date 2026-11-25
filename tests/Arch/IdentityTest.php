<?php

declare(strict_types=1);

use App\Domains\Identity\Contracts\IdentityTokenVerifier;
use App\Domains\Identity\Contracts\PhoneVerifier;
use App\Domains\Identity\Drivers\AppleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\FakeIdentityTokenVerifier;
use App\Domains\Identity\Drivers\GoogleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\LogPhoneVerifier;

arch('Identity contracts are interfaces')
    ->expect('App\Domains\Identity\Contracts')
    ->toBeInterfaces();

arch('ID token drivers implement the verifier contract')
    ->expect([
        GoogleIdentityTokenVerifier::class,
        AppleIdentityTokenVerifier::class,
        FakeIdentityTokenVerifier::class,
    ])
    ->toImplement(IdentityTokenVerifier::class);

arch('phone drivers implement the phone verifier contract')
    ->expect(LogPhoneVerifier::class)
    ->toImplement(PhoneVerifier::class);

// Tests sign their own tokens with it; the application verifies them only inside Identity.
arch('only the Identity domain uses Firebase\JWT')
    ->expect('Firebase\JWT')
    ->toOnlyBeUsedIn(['App\Domains\Identity', 'Tests']);
