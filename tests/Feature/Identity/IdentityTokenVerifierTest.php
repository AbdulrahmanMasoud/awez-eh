<?php

declare(strict_types=1);

use App\Domains\Identity\Data\VerifiedIdentity;
use App\Domains\Identity\Drivers\AppleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\GoogleIdentityTokenVerifier;
use App\Domains\Identity\Drivers\IdentityTokenVerifiers;
use App\Domains\Identity\Enums\IdentityProvider;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Support\IdTokens;

beforeEach(function (): void {
    Date::setTestNow('2026-11-25 18:00:00');
    $this->tokens = new IdTokens()->publish();
});

function google(): GoogleIdentityTokenVerifier
{
    return app(GoogleIdentityTokenVerifier::class);
}

function apple(): AppleIdentityTokenVerifier
{
    return app(AppleIdentityTokenVerifier::class);
}

/**
 * The reason an invalid token was refused, which only the log sees.
 */
function refusalReason(Closure $verify): string
{
    try {
        $verify();
    } catch (InvalidIdentityToken $exception) {
        expect($exception->errorCode)->toBe('invalid_identity_token')
            ->and($exception->status)->toBe(401);

        return (string) $exception->context()['reason'];
    }

    throw new RuntimeException('The token was accepted.');
}

it('accepts a valid Google token and says who it names', function (): void {
    $identity = google()->verify($this->tokens->google(), null);

    expect($identity->provider)->toBe(IdentityProvider::Google)
        ->and($identity->subject)->toBe('110248495921238986420')
        ->and($identity->email)->toBe('ahmed.elsayed@gmail.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->name)->toBe('أحمد السيد')
        ->and($identity->avatarUrl)->toBe('https://lh3.googleusercontent.com/a/ahmed');
});

it('accepts both of Google\'s issuers and any of our client ids', function (string $issuer, string $audience): void {
    expect(google()->verify($this->tokens->google(['iss' => $issuer, 'aud' => $audience]), null)->subject)->toBe('110248495921238986420');
})->with([
    ['accounts.google.com', IdTokens::GOOGLE_CLIENT_ID],
    ['https://accounts.google.com', 'ios-client.apps.googleusercontent.com'],
]);

it('reads an unverified Google email as unverified', function (): void {
    expect(google()->verify($this->tokens->google(['email_verified' => false]), null)->emailVerified)->toBeFalse();
});

it('refuses a Google token that is wrong in any way', function (Closure $token, string $reason): void {
    expect(refusalReason(fn (): VerifiedIdentity => google()->verify($token($this->tokens), null)))->toContain($reason);
})->with([
    'signed by another key' => [fn (IdTokens $tokens): string => $tokens->forged(), 'SignatureInvalidException'],
    'an unknown kid' => [fn (IdTokens $tokens): string => $tokens->google(keyId: 'key-unknown'), 'unknown kid'],
    'another issuer' => [fn (IdTokens $tokens): string => $tokens->google(['iss' => 'https://evil.example']), 'unknown issuer'],
    'another audience' => [fn (IdTokens $tokens): string => $tokens->google(['aud' => 'someone-else.apps.googleusercontent.com']), 'unknown audience'],
    'expired' => [fn (IdTokens $tokens): string => $tokens->google(['exp' => Date::now()->subMinutes(2)->getTimestamp()]), 'ExpiredException'],
    'issued in the future' => [fn (IdTokens $tokens): string => $tokens->google(['iat' => Date::now()->addMinutes(5)->getTimestamp()]), 'BeforeValidException'],
    'not valid yet' => [fn (IdTokens $tokens): string => $tokens->google(['nbf' => Date::now()->addMinutes(5)->getTimestamp()]), 'BeforeValidException'],
    'without an expiry' => [fn (IdTokens $tokens): string => $tokens->google(['exp' => null]), 'exp or iat missing'],
    'without a subject' => [fn (IdTokens $tokens): string => $tokens->google(['sub' => null]), 'subject missing'],
    'not a JWT' => [fn (): string => 'not-a-token', 'not a JWT'],
    'a header without kid' => [fn (): string => 'eyJhbGciOiJSUzI1NiJ9.e30.c2ln', 'kid missing'],
]);

it('allows a minute of clock difference', function (): void {
    $justExpired = $this->tokens->google(['exp' => Date::now()->subSeconds(30)->getTimestamp()]);

    expect(google()->verify($justExpired, null)->subject)->toBe('110248495921238986420');
});

it('accepts a valid Apple token with the hash of the app\'s nonce', function (): void {
    $identity = apple()->verify($this->tokens->apple('raw-nonce-from-the-app'), 'raw-nonce-from-the-app');

    expect($identity->provider)->toBe(IdentityProvider::Apple)
        ->and($identity->subject)->toBe('001234.5f8e2b7c9d1a4e6f8b0c2d4e6f8a0b1c.1234')
        ->and($identity->email)->toBe('x7k2p9@privaterelay.appleid.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->name)->toBeNull();
});

it('refuses an Apple token whose nonce is missing or wrong', function (?string $sentNonce, array $claims): void {
    expect(refusalReason(fn (): VerifiedIdentity => apple()->verify($this->tokens->apple('raw-nonce-from-the-app', $claims), $sentNonce)))
        ->toBe('nonce missing or wrong');
})->with([
    'no nonce sent' => [null, []],
    'an empty nonce sent' => ['', []],
    'another nonce sent' => ['another-nonce', []],
    'no nonce in the token' => ['raw-nonce-from-the-app', ['nonce' => null]],
    'the raw nonce in the token' => ['raw-nonce-from-the-app', ['nonce' => 'raw-nonce-from-the-app']],
]);

it('refuses an Apple token for another app or from another issuer', function (array $claims, string $reason): void {
    expect(refusalReason(fn (): VerifiedIdentity => apple()->verify($this->tokens->apple(claims: $claims), 'raw-nonce-from-the-app')))->toBe($reason);
})->with([
    [['aud' => 'com.other.app'], 'unknown audience'],
    [['iss' => 'https://accounts.google.com'], 'unknown issuer'],
]);

it('refuses Google\'s tokens at Apple and Apple\'s at Google', function (): void {
    expect(refusalReason(fn (): VerifiedIdentity => apple()->verify($this->tokens->google(), 'raw-nonce-from-the-app')))->toBe('unknown issuer')
        ->and(refusalReason(fn (): VerifiedIdentity => google()->verify($this->tokens->apple(), null)))->toBe('unknown issuer');
});

it('refuses every token while no client id is configured', function (): void {
    config(['awez.sign_in.google.client_ids' => []]);

    expect(refusalReason(fn (): VerifiedIdentity => google()->verify($this->tokens->google(), null)))->toBe('unknown audience');
});

it('caches the keys for the max-age the provider gives', function (): void {
    google()->verify($this->tokens->google(), null);
    google()->verify($this->tokens->google(), null);

    Http::assertSentCount(1);

    Date::setTestNow(Date::now()->addSeconds(21_601));
    google()->verify($this->tokens->google(['iat' => Date::now()->getTimestamp(), 'exp' => Date::now()->addHour()->getTimestamp()]), null);

    Http::assertSentCount(2);
});

it('caches the keys for an hour when the provider gives no max-age', function (): void {
    $this->tokens->publish(headers: []);

    google()->verify($this->tokens->google(), null);
    Date::setTestNow(Date::now()->addMinutes(59));
    google()->verify($this->tokens->google(['iat' => Date::now()->getTimestamp(), 'exp' => Date::now()->addHour()->getTimestamp()]), null);
    Http::assertSentCount(1);

    Date::setTestNow(Date::now()->addMinutes(2));
    google()->verify($this->tokens->google(['iat' => Date::now()->getTimestamp(), 'exp' => Date::now()->addHour()->getTimestamp()]), null);
    Http::assertSentCount(2);
});

it('refreshes the cached keys once for an unknown kid, which a rotation brings', function (): void {
    google()->verify($this->tokens->google(), null);

    // Google rotates in key-2; the cache still holds key-1 alone.
    $this->tokens->publish(['key-1', 'key-2']);

    expect(google()->verify($this->tokens->google(keyId: 'key-2'), null)->subject)->toBe('110248495921238986420');
    Http::assertSent(fn (Request $request): bool => $request->url() === IdTokens::GOOGLE_KEYS_URL);
});

it('asks for the keys once more, not again and again, for a kid nobody published', function (): void {
    google()->verify($this->tokens->google(), null);

    expect(refusalReason(fn (): VerifiedIdentity => google()->verify($this->tokens->google(keyId: 'key-unknown'), null)))->toBe('unknown kid');

    Http::assertSentCount(2);
});

it('refuses the token, rather than failing, when the keys cannot be fetched', function (): void {
    $this->tokens->unavailable();

    expect(refusalReason(fn (): VerifiedIdentity => google()->verify($this->tokens->google(), null)))->toBe('keys unavailable');
});

it('uses the fake verifier only when it is on', function (): void {
    expect(app(IdentityTokenVerifiers::class)->for(IdentityProvider::Google))->toBeInstanceOf(GoogleIdentityTokenVerifier::class)
        ->and(app(IdentityTokenVerifiers::class)->for(IdentityProvider::Apple))->toBeInstanceOf(AppleIdentityTokenVerifier::class);

    config(['awez.sign_in.fake' => true]);
    $identity = app(IdentityTokenVerifiers::class)->for(IdentityProvider::Google)->verify('fake:Mona@Example.com', null);

    expect($identity->subject)->toBe('fake:mona@example.com')
        ->and($identity->email)->toBe('mona@example.com')
        ->and($identity->emailVerified)->toBeTrue()
        ->and($identity->name)->toBe('mona')
        ->and(refusalReason(fn () => app(IdentityTokenVerifiers::class)->for(IdentityProvider::Google)->verify($this->tokens->google(), null)))
        ->toBe('not a fake:<email> token')
        ->and(refusalReason(fn () => app(IdentityTokenVerifiers::class)->for(IdentityProvider::Google)->verify('fake:not-an-email', null)))
        ->toBe('not a fake:<email> token');
});
