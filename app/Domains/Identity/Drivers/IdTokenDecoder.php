<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Data\IdTokenClaims;
use App\Domains\Identity\Exceptions\InvalidIdentityToken;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Date;
use stdClass;
use Throwable;

/**
 * What both providers' tokens share: a signature by one of the provider's keys, an issuer, an
 * audience among our client ids, and an expiry and issue time, both required, within a leeway.
 */
final readonly class IdTokenDecoder
{
    /** The only algorithm Google and Apple sign ID tokens with; any other is refused. */
    private const string ALGORITHM = 'RS256';

    private const int PARTS = 3;

    public function __construct(
        private JsonWebKeys $keys,
        private int $leewaySeconds,
    ) {}

    /**
     * @param  list<string>  $issuers
     * @param  list<string>  $audiences  Our client ids.
     *
     * @throws InvalidIdentityToken
     */
    public function decode(#[\SensitiveParameter] string $idToken, string $keysUrl, array $issuers, array $audiences): IdTokenClaims
    {
        $keyId = $this->keyId($idToken);
        $claims = $this->verifySignature($idToken, $keysUrl, $keyId);

        if (! isset($claims->exp, $claims->iat)) {
            throw new InvalidIdentityToken('exp or iat missing');
        }

        if (! is_string($claims->iss ?? null) || ! in_array($claims->iss, $issuers, true)) {
            throw new InvalidIdentityToken('unknown issuer');
        }

        $audience = $claims->aud ?? null;
        $tokenAudiences = array_filter(is_array($audience) ? $audience : [$audience], is_string(...));

        if ($audiences === [] || array_intersect($tokenAudiences, $audiences) === []) {
            throw new InvalidIdentityToken('unknown audience');
        }

        $subject = $claims->sub ?? null;

        if (! is_string($subject) || $subject === '') {
            throw new InvalidIdentityToken('subject missing');
        }

        return new IdTokenClaims($subject, $claims);
    }

    private function keyId(string $idToken): string
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== self::PARTS) {
            throw new InvalidIdentityToken('not a JWT');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
        } catch (Throwable $exception) {
            throw new InvalidIdentityToken('unreadable header', $exception);
        }

        if (! $header instanceof stdClass || ! is_string($header->kid ?? null)) {
            throw new InvalidIdentityToken('kid missing');
        }

        return $header->kid;
    }

    /**
     * An unknown key id refreshes the cached keys once, then fails.
     */
    private function verifySignature(string $idToken, string $keysUrl, string $keyId): stdClass
    {
        try {
            $keySet = JWK::parseKeySet($this->keys->of($keysUrl), self::ALGORITHM);

            if (! array_key_exists($keyId, $keySet)) {
                $keySet = JWK::parseKeySet($this->keys->refresh($keysUrl), self::ALGORITHM);
            }
        } catch (HttpClientException $exception) {
            throw new InvalidIdentityToken('keys unavailable', $exception);
        }

        if (! array_key_exists($keyId, $keySet)) {
            throw new InvalidIdentityToken('unknown kid');
        }

        // php-jwt keeps its leeway and its clock in static properties. Its clock is set to
        // Laravel's, so a token's times are judged by the same now as everything else.
        JWT::$leeway = $this->leewaySeconds;
        JWT::$timestamp = Date::now()->getTimestamp();

        try {
            return JWT::decode($idToken, $keySet);
        } catch (Throwable $exception) {
            // A bad signature, an expired token, one not valid yet, or a wrong algorithm.
            throw new InvalidIdentityToken($exception::class.': '.$exception->getMessage(), $exception);
        } finally {
            JWT::$timestamp = null;
        }
    }
}
