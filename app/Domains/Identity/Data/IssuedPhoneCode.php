<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Models\PhoneVerification;

final readonly class IssuedPhoneCode
{
    public function __construct(
        public PhoneVerification $verification,
        #[\SensitiveParameter] public string $code,
        public int $length,
    ) {}
}
