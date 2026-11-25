<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;

/**
 * A provider's published signing keys (its JWKS), cached for as long as its response says
 * (Cache-Control max-age), or `awez.sign_in.keys_cache_seconds` without one.
 */
final readonly class JsonWebKeys
{
    private const string CACHE_PREFIX = 'identity:jwks:';

    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private Http $http,
        private Cache $cache,
        private int $defaultCacheSeconds,
    ) {}

    /**
     * The JWKS document as the provider published it: {"keys": [...]}.
     *
     * @return array<mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function of(string $url): array
    {
        $cached = $this->cache->get($this->cacheKey($url));

        return is_array($cached) ? $cached : $this->refresh($url);
    }

    /**
     * Fetches the keys again: a token signed with a key we do not know may come from a key the
     * provider has just rotated in.
     *
     * @return array<mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function refresh(string $url): array
    {
        $response = $this->http->timeout(self::TIMEOUT_SECONDS)->acceptJson()->get($url)->throw();

        $keys = $response->json('keys');
        $keySet = ['keys' => is_array($keys) ? array_values($keys) : []];

        $this->cache->put($this->cacheKey($url), $keySet, $this->maxAge($response->header('Cache-Control')) ?? $this->defaultCacheSeconds);

        return $keySet;
    }

    private function maxAge(string $cacheControl): ?int
    {
        return preg_match('/(?:^|[,\s])max-age=(\d+)/i', $cacheControl, $matches) === 1 && (int) $matches[1] > 0
            ? (int) $matches[1]
            : null;
    }

    private function cacheKey(string $url): string
    {
        return self::CACHE_PREFIX.hash('sha256', $url);
    }
}
