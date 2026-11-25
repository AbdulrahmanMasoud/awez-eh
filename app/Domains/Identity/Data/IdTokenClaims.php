<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use stdClass;

/**
 * The claims of an ID token whose signature, issuer, audience and times were checked.
 */
final readonly class IdTokenClaims
{
    public function __construct(
        public string $subject,
        private stdClass $claims,
    ) {}

    public function string(string $name): ?string
    {
        $value = $this->claims->{$name} ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Apple writes some booleans as strings ("true").
     */
    public function boolean(string $name): bool
    {
        $value = $this->claims->{$name} ?? false;

        return $value === true || $value === 'true';
    }
}
