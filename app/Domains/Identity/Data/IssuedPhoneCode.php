<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Models\PhoneVerification;
use Spatie\LaravelData\Data;

/**
 * An opened confirmation and its code, which only the driver sees: the table holds its HMAC.
 */
final class IssuedPhoneCode extends Data
{
    public function __construct(
        public readonly PhoneVerification $verification,
        #[\SensitiveParameter] public readonly string $code,
        public readonly int $length,
    ) {}
}
