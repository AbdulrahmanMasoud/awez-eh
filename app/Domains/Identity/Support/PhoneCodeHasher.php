<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/**
 * Hashes verification codes with an HMAC keyed by the application key. A six-digit code has a
 * million values, so a plain hash of one is reversed in a moment by anyone who reads the table;
 * without the key, the table gives nothing away.
 */
final readonly class PhoneCodeHasher
{
    private const string ALGORITHM = 'sha256';

    public function __construct(#[\SensitiveParameter] private string $key) {}

    public function hash(#[\SensitiveParameter] string $code): string
    {
        return hash_hmac(self::ALGORITHM, $code, $this->key);
    }

    /**
     * In constant time, so the response time says nothing about how close a guess was.
     */
    public function matches(#[\SensitiveParameter] string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code));
    }
}
