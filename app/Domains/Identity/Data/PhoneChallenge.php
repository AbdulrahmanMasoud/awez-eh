<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\PhoneChallengeMethod;
use Carbon\CarbonImmutable;

/**
 * What the app must do to confirm the number: type a code of `length` digits, or open `link`
 * (a pre-filled WhatsApp message) and poll GET me.
 */
final readonly class PhoneChallenge
{
    private function __construct(
        public PhoneChallengeMethod $method,
        public CarbonImmutable $expiresAt,
        public ?int $length = null,
        public ?string $link = null,
    ) {}

    public static function code(int $length, CarbonImmutable $expiresAt): self
    {
        return new self(PhoneChallengeMethod::Code, $expiresAt, length: $length);
    }

    public static function whatsapp(string $link, CarbonImmutable $expiresAt): self
    {
        return new self(PhoneChallengeMethod::Whatsapp, $expiresAt, link: $link);
    }
}
