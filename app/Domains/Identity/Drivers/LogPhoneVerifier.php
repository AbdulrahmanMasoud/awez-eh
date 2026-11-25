<?php

declare(strict_types=1);

namespace App\Domains\Identity\Drivers;

use App\Domains\Identity\Contracts\PhoneVerifier;
use App\Domains\Identity\Data\PhoneChallenge;
use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Models\User;
use App\Support\PhoneNumber;
use Psr\Log\LoggerInterface;

/**
 * For local development and tests: the code goes to the `phone_verification` log channel and
 * nowhere else, the one exception to "never log a code" (invariant 8, DECISIONS 71). The app
 * refuses to boot with this driver outside local and testing (IdentityServiceProvider).
 */
final readonly class LogPhoneVerifier implements PhoneVerifier
{
    public function __construct(
        private PhoneCodeIssuer $codes,
        private LoggerInterface $log,
    ) {}

    public function driver(): PhoneVerificationDriver
    {
        return PhoneVerificationDriver::Log;
    }

    public function start(User $user, PhoneNumber $phone, string $ipAddress): PhoneChallenge
    {
        $issued = $this->codes->issue($user, $phone, $this->driver(), $ipAddress);

        // The number is masked, as everywhere; the code is what a developer reads here.
        $this->log->info('Phone verification code {code} for {phone}', [
            'code' => $issued->code,
            'phone' => $phone->mask(),
            'user_id' => $user->id,
            'expires_at' => $issued->verification->expires_at->toIso8601ZuluString(),
        ]);

        return PhoneChallenge::code($issued->length, $issued->verification->expires_at);
    }
}
