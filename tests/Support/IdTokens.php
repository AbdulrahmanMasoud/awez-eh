<?php

declare(strict_types=1);

namespace Tests\Support;

use Firebase\JWT\JWT;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Signs ID tokens as Google or Apple would, with a key pair made for the test, and fakes the
 * provider's published keys (JWKS). Nothing reaches the real providers.
 */
final class IdTokens
{
    public const string GOOGLE_KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public const string APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public const string GOOGLE_CLIENT_ID = 'android-client.apps.googleusercontent.com';

    public const string APPLE_CLIENT_ID = 'eg.awez-eh.app';

    /** @var array<string, OpenSSLAsymmetricKey> */
    private array $keys = [];

    public function __construct()
    {
        config([
            'awez.sign_in.fake' => false,
            'awez.sign_in.google.client_ids' => [self::GOOGLE_CLIENT_ID, 'ios-client.apps.googleusercontent.com'],
            'awez.sign_in.apple.client_ids' => [self::APPLE_CLIENT_ID],
        ]);
    }

    /** @var list<string> */
    private array $publishedKeyIds = [];

    /** @var array<string, string> */
    private array $publishedHeaders = [];

    private int $publishedStatus = 200;

    private bool $faked = false;

    /**
     * Publishes these key ids at both providers' JWKS URLs. Calling it again changes what they
     * publish, as a key rotation does.
     *
     * @param  list<string>  $keyIds
     * @param  array<string, string>  $headers
     */
    public function publish(array $keyIds = ['key-1'], array $headers = ['Cache-Control' => 'public, max-age=21600']): self
    {
        $this->publishedKeyIds = $keyIds;
        $this->publishedHeaders = $headers;
        $this->publishedStatus = 200;

        // Once: Http::fake() adds stubs, and the first one that matches answers.
        if (! $this->faked) {
            $this->faked = true;
            $respond = fn (): PromiseInterface => $this->publishedStatus === 200
                ? Http::response(['keys' => array_map($this->publicJwk(...), $this->publishedKeyIds)], 200, $this->publishedHeaders)
                : Http::response('', $this->publishedStatus);

            Http::fake([self::GOOGLE_KEYS_URL => $respond, self::APPLE_KEYS_URL => $respond]);
        }

        return $this;
    }

    /**
     * The providers' key URLs answer 503.
     */
    public function unavailable(): self
    {
        $this->publishedStatus = 503;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $claims  Override or add claims; a null value removes one.
     */
    public function google(array $claims = [], string $keyId = 'key-1'): string
    {
        return $this->sign([
            'iss' => 'https://accounts.google.com',
            'aud' => self::GOOGLE_CLIENT_ID,
            'sub' => '110248495921238986420',
            'email' => 'ahmed.elsayed@gmail.com',
            'email_verified' => true,
            'name' => 'أحمد السيد',
            'picture' => 'https://lh3.googleusercontent.com/a/ahmed',
            'iat' => Date::now()->getTimestamp(),
            'exp' => Date::now()->addHour()->getTimestamp(),
            ...$claims,
        ], $keyId);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function apple(string $nonce = 'raw-nonce-from-the-app', array $claims = [], string $keyId = 'key-1'): string
    {
        return $this->sign([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::APPLE_CLIENT_ID,
            'sub' => '001234.5f8e2b7c9d1a4e6f8b0c2d4e6f8a0b1c.1234',
            'email' => 'x7k2p9@privaterelay.appleid.com',
            'email_verified' => 'true',
            'is_private_email' => 'true',
            'nonce' => hash('sha256', $nonce),
            'nonce_supported' => true,
            'iat' => Date::now()->getTimestamp(),
            'exp' => Date::now()->addMinutes(10)->getTimestamp(),
            ...$claims,
        ], $keyId);
    }

    /**
     * Signed with a key that is not the published one under that id.
     */
    public function forged(string $keyId = 'key-1'): string
    {
        $impostor = $this->newKey();

        return JWT::encode($this->googleClaims(), $impostor, 'RS256', $keyId);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sign(array $claims, string $keyId): string
    {
        return JWT::encode(array_filter($claims, fn (mixed $value): bool => $value !== null), $this->key($keyId), 'RS256', $keyId);
    }

    /**
     * @return array<string, mixed>
     */
    private function googleClaims(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => self::GOOGLE_CLIENT_ID,
            'sub' => '110248495921238986420',
            'iat' => Date::now()->getTimestamp(),
            'exp' => Date::now()->addHour()->getTimestamp(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function publicJwk(string $keyId): array
    {
        $details = openssl_pkey_get_details($this->key($keyId));

        if ($details === false) {
            throw new RuntimeException('Cannot read the test key.');
        }

        return [
            'kty' => 'RSA',
            'kid' => $keyId,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
    }

    private function key(string $keyId): OpenSSLAsymmetricKey
    {
        return $this->keys[$keyId] ??= $this->newKey();
    }

    private function newKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('Cannot make a test key.');
        }

        return $key;
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
