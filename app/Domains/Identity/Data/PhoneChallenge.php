<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

use App\Domains\Identity\Enums\PhoneChallengeMethod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * What the app must do to confirm the number: type a code of `length` digits, or open `link`
 * (a pre-filled WhatsApp message) and poll GET me. Made with code() or whatsapp().
 */
final class PhoneChallenge extends Data
{
    public function __construct(
        public readonly PhoneChallengeMethod $method,
        public readonly CarbonImmutable $expiresAt,
        public readonly ?int $length = null,
        public readonly ?string $link = null,
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
